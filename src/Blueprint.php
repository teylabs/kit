<?php

namespace Tey\StarterBlueprint;

use RuntimeException;

final readonly class Blueprint
{
    private function __construct(public array $manifest, private string $directory) {}

    public static function release(string $release): self
    {
        if (! in_array($release, ['A', 'B'], true)) {
            throw new RuntimeException('Unknown blueprint release.');
        }
        $directory = dirname(__DIR__).'/resources/releases/'.$release;
        $manifest = json_decode(file_get_contents($directory.'/blueprint.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach (['format', 'id', 'version', 'modules', 'requirements', 'generated', 'host_owned', 'migration_order', 'verification'] as $field) {
            if (! array_key_exists($field, $manifest)) {
                throw new RuntimeException('Missing blueprint field: '.$field);
            }
        }

        return new self($manifest, $directory);
    }

    public function sourceReference(): string
    {
        $resources = [];
        foreach ($this->manifest['generated'] as $path => $entry) {
            $resources[$path] = hash('sha256', $this->stub($entry['stub']));
        }
        ksort($resources);

        return 'sha256:'.hash('sha256', json_encode([$this->manifest, $resources], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
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
