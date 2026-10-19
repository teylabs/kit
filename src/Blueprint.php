<?php

namespace Tey\StarterBlueprint;

use RuntimeException;

final readonly class Blueprint
{
    private const REFERENCES = [
        'A' => 'sha256:015b8df75a46c4f04ec1d59f6b7a2473eaea8a283037727db7d6f3a38e8e3cd1',
        'B' => 'sha256:281b86bdd27b38c1ea0b1d29e65dd1a3ff951010e254cd6e86bbbc4819dd7c37',
        'C' => 'sha256:35ae38351f6a43b75717f13cafcc31b1a87a6f3c9f39426fced300f6717eca27',
        'D' => 'sha256:1cf152e05cf3c20d634accc941136fb8b7c8d865e1ddbf3ef51952a6a7554177',
    ];

    private function __construct(public array $manifest, private string $directory, private string $release) {}

    public static function release(string $release): self
    {
        if (! in_array($release, ['A', 'B', 'C', 'D'], true)) {
            throw new RuntimeException('Unknown blueprint release.');
        }
        $directory = dirname(__DIR__).'/resources/releases/'.$release;
        $manifest = self::manifestAt($directory);
        foreach (['format', 'id', 'version', 'modules', 'requirements', 'generated', 'host_owned', 'migration_order', 'verification'] as $field) {
            if (! array_key_exists($field, $manifest)) {
                throw new RuntimeException('Missing blueprint field: '.$field);
            }
        }

        $blueprint = new self($manifest, $directory, $release);
        $blueprint->sourceReference();

        return $blueprint;
    }

    public function sourceReference(): string
    {
        return $this->reference($this->resources());
    }

    /** @return array{source_reference: string, manifest: array, files: array<string, string>} */
    public function snapshot(): array
    {
        $resources = $this->resources();
        $reference = $this->reference($resources);
        $files = [];
        foreach ($resources as $path => $bytes) {
            $namespace = 'App\\'.str_replace('/', '\\', substr(dirname($path), 4));
            $rendered = str_replace(['{{ namespace }}', '{{ class }}'], [$namespace, pathinfo($path, PATHINFO_FILENAME)], $bytes);
            if (preg_match('/\{\{.*?\}\}/s', $rendered)) {
                throw new RuntimeException('Unsupported blueprint placeholder.');
            }
            $files[$path] = $rendered;
        }

        return ['source_reference' => $reference, 'manifest' => $this->manifest, 'files' => $files];
    }

    private function resources(): array
    {
        if (self::manifestAt($this->directory) !== $this->manifest) {
            throw new RuntimeException('Immutable blueprint manifest changed.');
        }
        $resources = [];
        foreach ($this->manifest['generated'] as $path => $entry) {
            $resources[$path] = $this->stub($entry['stub']);
        }

        return $resources;
    }

    private function reference(array $resources): string
    {
        $hashes = array_map(fn (string $bytes): string => hash('sha256', $bytes), $resources);
        ksort($hashes);
        $reference = 'sha256:'.hash('sha256', json_encode([$this->manifest, $hashes], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        if ($reference !== self::REFERENCES[$this->release]) {
            throw new RuntimeException('Immutable blueprint resource identity mismatch.');
        }

        return $reference;
    }

    private static function manifestAt(string $directory): array
    {
        $path = $directory.'/blueprint.json';
        if (! is_file($path) || is_link($path)) {
            throw new RuntimeException('Missing or linked blueprint manifest.');
        }

        return json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    public function stubPath(string $stub): string
    {
        if (! preg_match('/\A[a-z0-9-]+\.php\.stub\z/', $stub)) {
            throw new RuntimeException('Invalid blueprint resource.');
        }
        $path = $this->directory.'/'.$stub;
        if (! is_file($path) || is_link($path)) {
            throw new RuntimeException('Missing or linked blueprint resource.');
        }

        return $path;
    }

    public function stub(string $stub): string
    {
        return file_get_contents($this->stubPath($stub));
    }
}
