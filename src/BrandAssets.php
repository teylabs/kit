<?php

namespace Tey\Kit;

use Imagick;
use ImagickDraw;
use ImagickPixel;
use RuntimeException;

/**
 * The brand's derived assets, made from the one logomark SVG and the accent: `favicon.svg`, `favicon.ico` (16, 32 and 48px),
 * `apple-touch-icon.png` (180), `mail-mark.png` (the CID mark in mail) and `og-image.png` (1200x630: mark, product name, accent).
 * Rendering is Imagick with SVG support, else `rsvg-convert`; with neither it refuses, naming what to install. The output is
 * deterministic, so a second run changes nothing.
 */
final class BrandAssets
{
    public const FILES = ['favicon.svg', 'favicon.ico', 'apple-touch-icon.png', 'mail-mark.png', 'og-image.png'];

    private const FONTS = ['/System/Library/Fonts/Supplemental/Arial Bold.ttf', '/Library/Fonts/Arial Bold.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf', '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf'];

    public function __construct(private string $logomark, private string $accent, private string $name, private ?string $font = null) {}

    public static function fromConfig(): self
    {
        $logomark = Brand::logomarkPath() ?? throw new RuntimeException('The brand has no logomark: set kit.brand.logomark (KIT_BRAND_LOGOMARK) to an SVG file.');

        return new self((string) file_get_contents($logomark), Brand::accent(), Brand::name(), Brand::get('font'));
    }

    /** Whether this machine can render the assets, and if not what to install. */
    public static function renderer(): ?string
    {
        if (class_exists(Imagick::class) && in_array('SVG', Imagick::queryFormats('SVG'), true)) {
            return 'imagick';
        }

        return self::which('rsvg-convert') !== null ? 'rsvg-convert' : null;
    }

    /** @return array<string, string> file name => bytes */
    public function render(): array
    {
        if (self::renderer() === null) {
            throw new RuntimeException('No SVG renderer is available. Install the PHP imagick extension (with SVG support) or rsvg-convert, then run kit:brand again; the committed placeholders still work without one.');
        }
        $tile = $this->tile(rx: 14);
        $png16 = $this->png($tile, 16);
        $png32 = $this->png($tile, 32);
        $png48 = $this->png($tile, 48);

        return [
            'favicon.svg' => $tile."\n",
            'favicon.ico' => $this->ico([[16, $png16], [32, $png32], [48, $png48]]),
            'apple-touch-icon.png' => $this->png($this->tile(rx: 0), 180),
            'mail-mark.png' => $this->png($this->mark($this->accent), 96),
            'og-image.png' => $this->og(),
        ];
    }

    /**
     * Writes only what changed.
     *
     * @return array{written: list<string>, unchanged: list<string>}
     */
    public function write(string $directory): array
    {
        $result = ['written' => [], 'unchanged' => []];
        foreach ($this->render() as $file => $bytes) {
            $path = rtrim($directory, '/').'/'.$file;
            if (is_file($path) && hash_equals(hash('sha256', (string) file_get_contents($path)), hash('sha256', $bytes))) {
                $result['unchanged'][] = $file;

                continue;
            }
            file_put_contents($path, $bytes);
            $result['written'][] = $file;
        }

        return $result;
    }

    /** The mark's markup and viewBox, its `currentColor` set to `$color`. */
    private function inner(string $color): array
    {
        $svg = trim(preg_replace('/<\?xml[^>]*\?>|<!--.*?-->/s', '', $this->logomark));
        if (! preg_match('/\A<svg\b([^>]*)>(.*)<\/svg>\z/s', $svg, $parts)) {
            throw new RuntimeException('The logomark is not an SVG file.');
        }
        $viewBox = preg_match('/viewBox="([^"]+)"/', $parts[1], $box) ? $box[1] : '0 0 24 24';
        // The root's own paint (fill, stroke) is kept on a group, so the mark draws as it was drawn.
        $paint = '';
        foreach (['fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin'] as $attribute) {
            if (preg_match('/\s'.$attribute.'="([^"]+)"/', $parts[1], $value)) {
                $paint .= ' '.$attribute.'="'.$value[1].'"';
            }
        }

        return [str_replace('currentColor', $color, '<g'.$paint.'>'.preg_replace('/<title>.*?<\/title>/s', '', $parts[2]).'</g>'), $viewBox];
    }

    /** The mark alone, in one colour, on transparency. */
    private function mark(string $color): string
    {
        [$inner, $viewBox] = $this->inner($color);

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="'.$viewBox.'" width="96" height="96">'.$inner.'</svg>';
    }

    /** The mark in white on an accent tile. */
    private function tile(int $rx): string
    {
        [$inner, $viewBox] = $this->inner('#ffffff');

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="'.$rx.'" fill="'.$this->accent.'"/><svg x="14" y="14" width="36" height="36" viewBox="'.$viewBox.'">'.$inner.'</svg></svg>';
    }

    private function png(string $svg, int $width, ?int $height = null): string
    {
        $height ??= $width;
        if (self::renderer() === 'imagick') {
            $image = new Imagick;
            $image->setBackgroundColor(new ImagickPixel('transparent'));
            $image->setResolution(max(72, $width * 4), max(72, $height * 4));
            $image->readImageBlob($svg);

            return $this->finish($image, $width, $height);
        }

        return $this->rsvg($svg, $width, $height);
    }

    private function og(): string
    {
        $accent = $this->accent;
        [$inner, $viewBox] = $this->inner('#ffffff');
        $mark = '<svg x="120" y="215" width="200" height="200" viewBox="'.$viewBox.'">'.$inner.'</svg>';
        $background = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630"><rect width="1200" height="630" fill="'.$accent.'"/>'.$mark.'</svg>';
        if (self::renderer() !== 'imagick') {
            $name = htmlspecialchars($this->name, ENT_XML1);
            $text = '<text x="360" y="345" font-family="sans-serif" font-size="84" font-weight="700" fill="#ffffff">'.$name.'</text>';

            return $this->rsvg(str_replace('</svg>', $text.'</svg>', $background), 1200, 630);
        }
        $image = new Imagick;
        $image->setBackgroundColor(new ImagickPixel($accent));
        $image->readImageBlob($background);
        $draw = new ImagickDraw;
        $draw->setFillColor(new ImagickPixel('#ffffff'));
        $draw->setFont($this->fontFile());
        $draw->setFontSize(84);
        $draw->setGravity(Imagick::GRAVITY_WEST);
        $image->annotateImage($draw, 360, 0, 0, $this->name);

        return $this->finish($image, 1200, 630);
    }

    private function finish(Imagick $image, int $width, int $height): string
    {
        $image->setImageFormat('png32');
        if ($image->getImageWidth() !== $width || $image->getImageHeight() !== $height) {
            $image->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);
        }
        // No dates or software notes: the same brand renders the same bytes.
        $image->stripImage();
        $image->setOption('png:exclude-chunk', 'all');
        $image->setOption('png:compression-level', '9');

        return $image->getImageBlob();
    }

    private function rsvg(string $svg, int $width, int $height): string
    {
        $binary = self::which('rsvg-convert') ?? throw new RuntimeException('rsvg-convert is not available.');
        $process = proc_open([$binary, '-w', (string) $width, '-h', (string) $height, '-f', 'png'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        fwrite($pipes[0], $svg);
        fclose($pipes[0]);
        $png = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0 || $png === '') {
            throw new RuntimeException('rsvg-convert could not render the brand SVG.');
        }

        return $png;
    }

    /** An ICO holding PNG images, which every current browser reads. @param list<array{0: int, 1: string}> $images */
    private function ico(array $images): string
    {
        $header = pack('vvv', 0, 1, count($images));
        $entries = '';
        $data = '';
        $offset = 6 + 16 * count($images);
        foreach ($images as [$size, $png]) {
            $entries .= pack('CCCCvvVV', $size, $size, 0, 0, 1, 32, strlen($png), $offset + strlen($data));
            $data .= $png;
        }

        return $header.$entries.$data;
    }

    private function fontFile(): string
    {
        foreach (array_filter([$this->font, ...self::FONTS]) as $font) {
            if (is_file((string) $font)) {
                return (string) $font;
            }
        }

        throw new RuntimeException('No TrueType font was found for the social image. Set KIT_BRAND_FONT (kit.brand.font) to a .ttf file.');
    }

    private static function which(string $binary): ?string
    {
        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $directory) {
            if ($directory !== '' && is_executable($directory.'/'.$binary)) {
                return $directory.'/'.$binary;
            }
        }

        return null;
    }
}
