<?php

// Tey\Kit\Features\Core\UploadLimit: an upload cap is the lowest of the kit's own, PHP's upload_max_filesize and post_max_size, and medialibrary's max_file_size; and kit:preflight's
// report names each kit cap the stack would cut down. PHP's upload limits can't be changed at runtime, so each case runs in a child PHP with them set by -d. No HTTP or host.

require $argv[1] ?? dirname(__DIR__).'/vendor/autoload.php';

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Tey\Kit\Features\Core\UploadLimit;

function limitCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo "PASS {$message}\n";
}

/** Runs `$code` (returning a JSON-able value) in a child PHP with these ini values and medialibrary's max_file_size. */
function underLimits(string $upload, string $post, ?int $media, string $code): mixed
{
    $script = '<?php require '.var_export(dirname(__DIR__).'/vendor/autoload.php', true).';'
        .'$c = new Illuminate\Container\Container; Illuminate\Container\Container::setInstance($c);'
        .'$c->instance("config", new Illuminate\Config\Repository(["media-library" => ["max_file_size" => '.var_export($media, true).']]));'
        .'echo json_encode((function () { '.$code.' })());';
    $file = tempnam(sys_get_temp_dir(), 'kit-upload-limit-');
    file_put_contents($file, $script);
    try {
        $output = shell_exec(escapeshellarg(PHP_BINARY).' -d upload_max_filesize='.escapeshellarg($upload).' -d post_max_size='.escapeshellarg($post).' '.escapeshellarg($file));
    } finally {
        unlink($file);
    }

    return json_decode((string) $output, true, flags: JSON_THROW_ON_ERROR);
}

try {
    $container = new Container;
    Container::setInstance($container);
    $container->instance('config', new Repository([]));
    limitCheck(UploadLimit::bytes('100M') === 100 * 1024 ** 2 && UploadLimit::bytes('2G') === 2 * 1024 ** 3 && UploadLimit::bytes('512k') === 512 * 1024 && UploadLimit::bytes('4096') === 4096, 'ini sizes read as bytes (M, G, lower-case k, plain)');
    limitCheck(UploadLimit::bytes('0') === null && UploadLimit::bytes('') === null && UploadLimit::bytes('-1') === null, 'an ini size of 0, empty or -1 is no limit, never a zero cap');
    limitCheck(UploadLimit::media() === null, 'with no medialibrary config, medialibrary sets no limit');

    $mb = 1024 ** 2;
    limitCheck(underLimits('100M', '100M', null, 'return Tey\Kit\Features\Core\UploadLimit::cap(10 * 1024 ** 2);') === 10 * $mb, 'a kit cap under every stack limit is kept as it is');
    limitCheck(underLimits('2M', '8M', null, 'return Tey\Kit\Features\Core\UploadLimit::cap(10 * 1024 ** 2);') === 2 * $mb, 'upload_max_filesize below the kit cap wins');
    limitCheck(underLimits('20M', '8M', null, 'return Tey\Kit\Features\Core\UploadLimit::cap(10 * 1024 ** 2);') === 8 * $mb, 'post_max_size below the kit cap wins (a browser upload over it never arrives)');
    limitCheck(underLimits('100M', '100M', 5 * $mb, 'return Tey\Kit\Features\Core\UploadLimit::cap(10 * 1024 ** 2);') === 5 * $mb, 'medialibrary\'s max_file_size below the kit cap wins');
    limitCheck(underLimits('0', '0', null, 'return Tey\Kit\Features\Core\UploadLimit::cap(10 * 1024 ** 2);') === 10 * $mb, 'unlimited PHP sizes leave the kit cap');
    limitCheck(underLimits('2M', '8M', null, 'return Tey\Kit\Features\Core\UploadLimit::capKb(10240);') === 2048, 'capKb gives the same cap in kilobytes, for the max: rule');

    $report = underLimits('2M', '8M', 5 * $mb, 'return Tey\Kit\Features\Core\UploadLimit::shortfalls(["notes attachments" => 10 * 1024 ** 2, "avatar" => 1024 ** 2]);');
    limitCheck($report === [
        'notes attachments: 10.0 MB, but PHP\'s upload_max_filesize allows 2.0 MB',
        'notes attachments: 10.0 MB, but PHP\'s post_max_size allows 8.0 MB',
        'notes attachments: 10.0 MB, but medialibrary\'s max_file_size allows 5.0 MB',
    ], 'the preflight report names each cap the stack cuts down, by setting, and leaves out a cap that fits (avatar 1 MB)');
    limitCheck(underLimits('100M', '100M', null, 'return Tey\Kit\Features\Core\UploadLimit::shortfalls(["notes attachments" => 10 * 1024 ** 2]);') === [], 'nothing to report when every limit is above the kit caps');

    echo "Upload limits: UploadLimit in child PHP processes with real ini values; no HTTP, host or browser (the Notes page and its 422 are in NotesCheck).\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
