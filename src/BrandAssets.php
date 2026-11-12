<?php

namespace Tey\Kit;

use Imagick;
use ImagickDraw;
use ImagickPixel;
use RuntimeException;

/**
 * The brand's derived assets, made from the icon SVG (`kit.brand.icon`, else the logomark) and the accent: `favicon.svg`, `favicon.ico` (16, 32 and 48px),
 * `apple-touch-icon.png` (180), `mail-mark.png` (the CID mark in mail) and `og-image.png` (1200x630: the logomark, product name, accent). A mark that is not square is padded to a centred square first, so nothing is stretched.
 * Rendering is Imagick with SVG support, else `rsvg-convert`; with neither it refuses, naming what to install. ImageMagick's own SVG
 * renderer (no librsvg delegate) draws no strokes, so a stroked logomark goes to `rsvg-convert` instead, or is refused with how to
 * outline the strokes; it is never rasterised to a dot. The output is deterministic, so a second run changes nothing.
 */
final class BrandAssets
{
    public const FILES = ['favicon.svg', 'favicon.ico', 'apple-touch-icon.png', 'mail-mark.png', 'og-image.png'];

    private const FONTS = ['/System/Library/Fonts/Supplemental/Arial Bold.ttf', '/Library/Fonts/Arial Bold.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf', '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf'];

    /** The renderer this logomark is drawn with, chosen once per render(). */
    private ?string $engine = null;

    /** The square source for the favicons, the touch icon and the mail mark; the logomark when null. */
    private string $icon;

    public function __construct(private string $logomark, private string $accent, private string $name, private ?string $font = null, ?string $icon = null)
    {
        if (trim($accent) === '') {
            throw new RuntimeException('The brand assets need an accent colour, and it is empty: set \'accent\' in config/brand.php to a #rrggbb colour.');
        }
        $this->icon = $icon ?? $logomark;
    }

    public static function fromConfig(): self
    {
        $logomark = Brand::logomarkPath() ?? throw new RuntimeException('The brand has no logomark: set \'logomark\' in config/brand.php to an SVG file.');
        $icon = Brand::iconPath();
        if (Brand::get('icon') !== null && Brand::get('icon') !== '' && $icon === null) {
            throw new RuntimeException('kit.brand.icon (config/brand.php) is set but is not an SVG file this app can read.');
        }

        return new self((string) file_get_contents($logomark), self::configuredAccent(), Brand::name(), Brand::get('font'), $icon === null ? null : (string) file_get_contents($icon));
    }

    /**
     * The accent the assets are drawn in: `kit.brand.accent`, or, when that is empty or resolved unset (null in config/brand.php), the accent the theme resolves to (the light `--primary` in the host's
     * stylesheets, `ThemeAdvisory::primary()`). With neither it refuses, so no asset is drawn in an empty colour. A set accent reads as before (`Brand::accent()`).
     */
    public static function configuredAccent(?string $root = null): string
    {
        $accent = Brand::get('accent');
        // Resolved unset (config/brand.php set it to null or empty, so BrandConfig left it out of kit.brand): the theme, as an empty accent was.
        $unset = $accent === null && (BrandConfig::sources()['accent'] ?? null) === BrandConfig::SOURCE_CONFIG;
        if (! $unset && (! is_string($accent) || trim($accent) !== '')) {
            return Brand::accent();
        }

        return ThemeAdvisory::primary(HostRequirements::themeStylesheets($root ?? base_path()))
            ?? throw new RuntimeException('kit.brand.accent (config/brand.php) is '.($accent === null ? 'null' : 'empty').' and the theme resolves to no accent (no readable --primary in :root of resources/css/app.css or its imports). Set \'accent\' in config/brand.php to a #rrggbb colour, then run kit:brand again.');
    }

    /**
     * The output files the app made by hand (`kit.brand.own`): kit:brand leaves these alone. Each as a file name in public/ (a leading `/` or `public/` is dropped).
     *
     * @return list<string>
     */
    public static function owned(): array
    {
        $own = [];
        foreach ((array) Brand::get('own', []) as $path) {
            if (is_string($path) && ($path = (string) preg_replace('#\A/?(public/)?#', '', trim($path))) !== '') {
                $own[] = $path;
            }
        }

        return array_values(array_unique($own));
    }

    /** Whether this machine can render the assets, and if not what to install. */
    public static function renderer(): ?string
    {
        if (class_exists(Imagick::class) && in_array('SVG', Imagick::queryFormats('SVG'), true)) {
            return 'imagick';
        }

        return self::which('rsvg-convert') !== null ? 'rsvg-convert' : null;
    }

    /** Whether Imagick has librsvg behind it: the built-in SVG renderer draws fills but not strokes. */
    private static function imagickDrawsStrokes(): bool
    {
        return str_contains((string) (Imagick::getConfigureOptions()['DELEGATES'] ?? ''), 'rsvg');
    }

    /**
     * Whether the SVG paints anything with a stroke: a `stroke` attribute, a `stroke:` declaration in a `style` attribute or a `<style>` block, other
     * than `none`. Strokes inherit, so one on the root or a group counts.
     */
    public static function strokes(string $svg): bool
    {
        $svg = (string) preg_replace('/<!--.*?-->/s', '', $svg);
        if (preg_match('/\sstroke\s*=\s*(["\'])(?!\s*none\s*\1)[^"\']*\1/i', $svg) === 1) {
            return true;
        }
        preg_match_all('/\sstyle\s*=\s*(["\'])(.*?)\1|<style\b[^>]*>(.*?)<\/style>/is', $svg, $styles, PREG_SET_ORDER);
        foreach ($styles as $style) {
            $css = (string) preg_replace('/\/\*.*?\*\//s', '', ($style[2] ?? '').($style[3] ?? ''));
            if (preg_match('/(?:^|[;{\s])stroke\s*:\s*(?!none\b)[^;}\s]/i', $css) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * The renderer for a logomark: Imagick when it can draw it, `rsvg-convert` when a stroked mark needs it and Imagick has no librsvg, or null when
     * nothing can. Pure, so the choice is tested without either installed.
     */
    public static function engineFor(bool $strokes, bool $imagick, bool $imagickDrawsStrokes, bool $rsvg): ?string
    {
        if ($imagick && (! $strokes || $imagickDrawsStrokes)) {
            return 'imagick';
        }

        return $rsvg ? 'rsvg-convert' : null;
    }

    /** @return array<string, string> file name => bytes */
    public function render(): array
    {
        $imagick = self::renderer() === 'imagick';
        $rsvg = self::which('rsvg-convert') !== null;
        if (! $imagick && ! $rsvg) {
            throw new RuntimeException('No SVG renderer is available. Install the PHP imagick extension (with SVG support) or rsvg-convert, then run kit:brand again; the committed placeholders still work without one.');
        }
        $this->engine = self::engineFor(self::strokes($this->logomark) || self::strokes($this->icon), $imagick, $imagick && self::imagickDrawsStrokes(), $rsvg)
            ?? throw new RuntimeException('The '.(self::strokes($this->logomark) ? 'logomark' : 'icon (kit.brand.icon)').' is drawn with strokes, and this ImageMagick renders SVG without them (it has no librsvg), so the mark would come out as a dot. Outline the strokes in your editor (Inkscape: Path › Stroke to Path; Illustrator: Object › Path › Outline Stroke; Figma: Outline stroke) and save the SVG again, or install rsvg-convert (librsvg), then run kit:brand again.');
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
     * Writes only what changed, and never a file in `$own` (the app's own, `kit.brand.own`): those are reported as skipped.
     *
     * @param  list<string>  $own
     * @return array{written: list<string>, unchanged: list<string>, skipped: list<string>}
     */
    public function write(string $directory, array $own = []): array
    {
        $result = ['written' => [], 'unchanged' => [], 'skipped' => []];
        foreach ($this->render() as $file => $bytes) {
            if (in_array($file, $own, true)) {
                $result['skipped'][] = $file;

                continue;
            }
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

    /** The mark's markup and viewBox (padded to a centred square), its `currentColor` set to `$color`. The icon unless `$svg` is given. */
    private function inner(string $color, ?string $svg = null): array
    {
        $svg = trim(preg_replace('/<\?xml[^>]*\?>|<!--.*?-->/s', '', $svg ?? $this->icon));
        if (! preg_match('/\A<svg\b([^>]*)>(.*)<\/svg>\z/s', $svg, $parts)) {
            throw new RuntimeException('The logomark is not an SVG file.');
        }
        $viewBox = self::square(preg_match('/viewBox="([^"]+)"/', $parts[1], $box) ? $box[1] : '0 0 24 24');
        // The root's own paint (fill, stroke, and a style that sets them) is kept on a group, so the mark draws as it was drawn.
        $paint = '';
        foreach (['fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'style'] as $attribute) {
            if (preg_match('/\s'.$attribute.'="([^"]+)"/', $parts[1], $value)) {
                $paint .= ' '.$attribute.'="'.$value[1].'"';
            }
        }

        $markup = '<g'.$paint.'>'.preg_replace('/<title>.*?<\/title>/s', '', $parts[2]).'</g>';
        // One colour for the whole mark: currentColor and every painted fill or stroke take `$color`; `none` stays none.
        $markup = preg_replace('/\b(fill|stroke)="(?!none")[^"]*"/', '$1="'.$color.'"', str_replace('currentColor', $color, $markup));
        $markup = preg_replace('/(?<![\w-])(fill|stroke)\s*:\s*(?!none\b)[^;"\'}]+/i', '$1:'.$color, $markup);

        return [$markup, $viewBox];
    }

    /** A viewBox padded on its short side to a square around the same centre; a square one is returned as it was, byte for byte. */
    public static function square(string $viewBox): string
    {
        [$minX, $minY, $width, $height] = array_map('floatval', preg_split('/[\s,]+/', trim($viewBox)) + [0, 0, 24, 24]);
        if ($width === $height || $width <= 0 || $height <= 0) {
            return $viewBox;
        }
        $size = max($width, $height);
        $number = fn (float $value): string => rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');

        return implode(' ', array_map($number, [$minX - ($size - $width) / 2, $minY - ($size - $height) / 2, $size, $size]));
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

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="'.$rx.'" fill="'.$this->accent.'"/>'.$this->place($inner, $viewBox, 14, 14, 36).'</svg>';
    }

    /** The mark's markup scaled into a square at x, y with the given size: a transform, as nested SVG is not rendered everywhere. */
    private function place(string $inner, string $viewBox, float $x, float $y, float $size): string
    {
        [$minX, $minY, $width, $height] = array_map('floatval', preg_split('/[\s,]+/', trim($viewBox)) + [0, 0, 24, 24]);
        $scale = $size / max($width, $height);

        return sprintf('<g transform="translate(%s %s) scale(%s) translate(%s %s)">%s</g>', $x, $y, round($scale, 6), -$minX, -$minY, $inner);
    }

    private function png(string $svg, int $width, ?int $height = null): string
    {
        $height ??= $width;
        if ($this->engine === 'imagick') {
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
        [$inner, $viewBox] = $this->inner('#ffffff', $this->logomark);
        $mark = $this->place($inner, $viewBox, 120, 215, 200);
        $background = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630"><rect width="1200" height="630" fill="'.$accent.'"/>'.$mark.'</svg>';
        if ($this->engine !== 'imagick') {
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

        throw new RuntimeException('No TrueType font was found for the social image. Set \'font\' in config/brand.php (kit.brand.font) to a .ttf file.');
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
