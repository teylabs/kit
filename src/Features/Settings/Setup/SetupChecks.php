<?php

namespace Tey\Kit\Features\Settings\Setup;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Settings\SettingValues;
use Tey\Kit\Features\Workspaces\Rls;
use Tey\Kit\Features\Workspaces\Workspaces;
use Throwable;

/**
 * The kit's own rows of Settings → Setup (SettingsProvider registers them). A row that only matters in production is `na` while the app is local
 * (APP_ENV=local, or the profile is iterating), so a fresh local install (Herd: local mail and disk, no Cloud or bucket keys) has no row missing or
 * failing; in production each row's rules apply unchanged. Every check reads config, the database and the cache only: none calls a
 * provider. The Resend domain's row reads the status Email sending last stored; the bucket's row asks the disk itself, which is the app's own storage.
 */
final class SetupChecks
{
    public const DOCS = 'https://github.com/teylabs/kit/blob/main/docs/';

    public const QUEUE_DOCS = self::DOCS.'deploy.md#queues';

    /** Cache key the queue's JobProcessed listener writes: when the newest job finished. */
    public const LAST_FINISHED = 'kit.setup.queue.last_finished';

    public const FLEX_LIMIT = 85;

    public const STALE_MINUTES = 15;

    public static function register(Registry $registry): void
    {
        $registry
            ->setupRow('Mail', 'mail.mailer', self::mailer(...))
            ->setupRow('Mail', 'mail.allow-only', self::allowOnly(...))
            ->setupRow('Mail', 'mail.domain', self::mailDomain(...), 'workspace')
            ->setupRow('Media', 'media.disk', self::mediaDisk(...))
            ->setupRow('Media', 'media.bucket', self::bucket(...))
            ->setupRow('RLS', 'rls.enabled', self::rlsEnabled(...))
            ->setupRow('RLS', 'rls.role', self::rlsRole(...))
            ->setupRow('RLS', 'rls.unprotected', self::rlsUnprotected(...))
            ->setupRow('Queue', 'queue.connection', self::queueConnection(...))
            ->setupRow('Queue', 'queue.timeout', self::queueTimeout(...))
            ->setupRow('Queue', 'queue.failed', self::queueFailed(...))
            ->setupRow('Queue', 'queue.last-finished', self::queueLastFinished(...))
            ->setupRow('Queue', 'queue.prune-failed', self::queuePruneFailed(...))
            ->setupRow('Telescope', 'telescope.entries', self::telescope(...))
            ->setupRow('Settings', 'settings.registered', self::registered(...))
            ->setupRow('By hand', 'manual.dns', fn () => self::local() ? SetupRow::na('DNS records', 'Only needed once the app is deployed.') : SetupRow::of('DNS records', 'manual', 'Point the app\'s domain at its host.', 'Add the A or CNAME records your host gives you at your DNS provider.', SetupRow::link('Deploy guide', self::DOCS.'deploy.md')))
            ->setupRow('By hand', 'manual.mail-dns', fn () => self::local() ? SetupRow::na('Mail domain records', 'Only needed once the app is deployed.') : SetupRow::of('Mail domain records', 'manual', 'The sending domain\'s SPF, DKIM and return-path records.', 'Add the records Resend lists for the domain at your DNS provider.', SetupRow::link('Resend domains', 'https://resend.com/domains')))
            ->setupRow('By hand', 'manual.resend', fn () => self::local() ? SetupRow::na('Resend account', 'Only needed once the app is deployed.') : SetupRow::of('Resend account', 'manual', 'An account and an API key for sending.', 'Create an API key with sending access and set RESEND_API_KEY.', SetupRow::link('Resend API keys', 'https://resend.com/api-keys')))
            ->setupRow('By hand', 'manual.bucket', fn () => self::local() ? SetupRow::na('R2 or Cloud bucket', 'Only needed once the app is deployed.') : SetupRow::of('R2 or Cloud bucket', 'manual', 'A private bucket for uploads, with its keys.', 'Create the bucket and set its AWS_* variables, or attach a Laravel Cloud bucket.', SetupRow::link('Media guide', self::DOCS.'media.md')))
            ->setupRow('By hand', 'manual.cloud-plan', fn () => self::local() ? SetupRow::na('Cloud plan', 'Only needed once the app is deployed.') : SetupRow::of('Cloud plan', 'manual', 'The hosting plan the app runs on.', 'Check the plan covers the app\'s compute, database and queue.', SetupRow::link('Laravel Cloud', 'https://cloud.laravel.com')));
    }

    private static function production(): bool
    {
        return app()->environment('production');
    }

    /** APP_ENV=local, or the iterating profile: production-only rows are not applicable. */
    public static function local(): bool
    {
        return app()->environment('local') || self::profile() === 'iterating';
    }

    public static function mailer(): SetupRow
    {
        $mailer = (string) config('mail.default');
        $transport = (string) config('mail.mailers.'.$mailer.'.transport', $mailer);
        if (in_array($transport, ['log', 'array'], true)) {
            return self::production()
                ? SetupRow::of('Mailer', 'failing', 'Mail goes to the '.$transport.' mailer, so nobody receives it.', 'Set MAIL_MAILER to resend (or another real mailer).', SetupRow::link('Mail guide', self::DOCS.'mail.md'))
                : SetupRow::of('Mailer', 'info', 'Mail goes to the '.$transport.' mailer while you build.');
        }

        return SetupRow::of('Mailer', 'ok', 'Mail goes through '.$mailer.'.');
    }

    public static function allowOnly(): SetupRow
    {
        $raw = (string) (config('mail.allow_only') ?? Env::get('MAIL_ALLOW_ONLY') ?? '');
        $addresses = array_values(array_filter(array_map('trim', explode(',', $raw)), fn (string $a): bool => $a !== ''));
        if (self::local()) {
            return SetupRow::na('Recipients', 'Only matters in production'.($addresses === [] ? '.' : ': MAIL_ALLOW_ONLY lists '.count($addresses).'.'));
        }
        if ($addresses === []) {
            return SetupRow::of('Recipients', 'ok', 'Mail goes to anyone.');
        }
        $count = count($addresses);

        return SetupRow::of('Recipients', 'held', 'MAIL_ALLOW_ONLY holds mail to '.$count.' '.($count === 1 ? 'address' : 'addresses').'.', 'Remove MAIL_ALLOW_ONLY when the app is ready to mail everyone.');
    }

    public static function mailDomain(): ?SetupRow
    {
        $workspace = Workspaces::current();
        if ($workspace === null || ! Schema::hasTable('workspace_mail_identities')) {
            return null;
        }
        if (self::local()) {
            return SetupRow::na('Sending domain', 'Only matters in production.');
        }
        $row = DB::table('workspace_mail_identities')->where('workspace_id', $workspace->getKey())->first();
        $link = SetupRow::link('Email sending', 'settings/email');
        if ($row === null || $row->from_mode !== 'domain' || blank($row->domain)) {
            return SetupRow::of('Sending domain', 'info', 'Mail goes from the platform address.', null, $link);
        }

        return match ((string) $row->domain_status) {
            'verified' => SetupRow::of('Sending domain', 'ok', $row->domain.' is verified.'),
            'failed' => SetupRow::of('Sending domain', 'failing', $row->domain.' failed verification.', 'Check its DNS records, then Check now.', $link),
            default => SetupRow::of('Sending domain', 'held', $row->domain.' is waiting for DNS; mail keeps the platform address until then.', 'Add its DNS records, then Check now.', $link),
        };
    }

    public static function mediaDiskName(): string
    {
        return (string) (config('media-library.disk_name') ?? config('filesystems.default', 'local'));
    }

    public static function mediaDisk(): SetupRow
    {
        $disk = self::mediaDiskName();
        $driver = (string) config('filesystems.disks.'.$disk.'.driver', 'local');
        if ($driver === 'local' && self::local()) {
            return SetupRow::na('Disk', 'Uploads go to the '.$disk.' disk, on this machine. A deployed app needs a bucket.');
        }
        if ($driver === 'local' && self::production()) {
            return SetupRow::of('Disk', 'failing', 'Uploads go to the '.$disk.' disk, on the server\'s own drive, which a deploy replaces.', 'Set MEDIA_DISK (or FILESYSTEM_DISK) to a bucket disk.', SetupRow::link('Media guide', self::DOCS.'media.md'));
        }

        return SetupRow::of('Disk', 'ok', 'Uploads go to the '.$disk.' disk ('.$driver.').');
    }

    public static function bucket(): ?SetupRow
    {
        $disk = self::mediaDiskName();
        if ((string) config('filesystems.disks.'.$disk.'.driver', 'local') === 'local') {
            return null;
        }
        if (self::local()) {
            return SetupRow::na('Bucket', 'Only checked in production.');
        }
        try {
            Storage::disk($disk)->exists('.kit-setup-probe');
        } catch (Throwable $error) {
            return SetupRow::of('Bucket', 'failing', 'The '.$disk.' bucket can\'t be reached ('.class_basename($error).').', 'Check the bucket name, endpoint and keys.', SetupRow::link('Media guide', self::DOCS.'media.md'));
        }

        return SetupRow::of('Bucket', 'ok', 'The '.$disk.' bucket answers.');
    }

    public static function rlsEnabled(): SetupRow
    {
        $link = SetupRow::link('RLS guide', self::DOCS.'rls.md');
        if (! Rls::enabled() && self::local()) {
            return SetupRow::na('Row-level security', 'Off. Turn it on for a deployed app on PostgreSQL.');
        }
        if (! Rls::enabled()) {
            return SetupRow::of('Row-level security', 'missing', 'Off: only the app\'s own scoping keeps workspaces apart.', 'On PostgreSQL, set KIT_RLS=true after the setup steps.', $link);
        }

        return SetupRow::of('Row-level security', 'ok', 'On.');
    }

    public static function rlsRole(): ?SetupRow
    {
        if (! Rls::enabled()) {
            return null;
        }
        $problems = array_values(array_filter(Rls::problems(), fn (string $p): bool => ! str_starts_with($p, 'workspace tables without')));

        return $problems === []
            ? SetupRow::of('Runtime role', 'ok', 'The app\'s database role is not a superuser, has no BYPASSRLS and owns no table.')
            : SetupRow::of('Runtime role', 'failing', ucfirst(implode('; ', $problems)).'.', 'Run kit:rls:role and point the default connection at it.', SetupRow::link('RLS guide', self::DOCS.'rls.md'));
    }

    public static function rlsUnprotected(): ?SetupRow
    {
        if (! Rls::enabled()) {
            return null;
        }
        try {
            $tables = Rls::unprotected();
        } catch (Throwable) {
            return SetupRow::of('Unprotected tables', 'failing', 'The runtime connection can\'t be checked.', 'Run php artisan kit:preflight.');
        }
        $count = count($tables);

        return $count === 0
            ? SetupRow::of('Unprotected tables', 'ok', 'Every table with a workspace_id is protected or listed in except.')
            : SetupRow::of('Unprotected tables', 'warning', $count.' '.($count === 1 ? 'table has' : 'tables have').' a workspace_id but no policy: '.implode(', ', $tables).'.', 'Protect each with Rls::protect() in a migration, or list it in kit.tenancy.rls.except.', SetupRow::link('RLS guide', self::DOCS.'rls.md'));
    }

    /** @return array{name: string, driver: string, config: array<string, mixed>} */
    private static function queue(): array
    {
        $name = (string) config('queue.default', 'sync');
        $config = (array) config('queue.connections.'.$name, []);

        return ['name' => $name, 'driver' => (string) ($config['driver'] ?? $name), 'config' => $config];
    }

    private static function sync(): bool
    {
        return self::queue()['driver'] === 'sync';
    }

    public static function profile(): string
    {
        return (string) (Env::get('APP_PROFILE') ?? (self::production() ? 'production' : 'iterating'));
    }

    public static function queueConnection(): SetupRow
    {
        $queue = self::queue();
        $detail = ucfirst($queue['driver']).' queue ('.$queue['name'].'), profile '.self::profile().'.';
        if ($queue['driver'] === 'sync') {
            return SetupRow::of('Connection', 'info', $detail.' Jobs run inline, in the request that dispatches them. Fine for a new app.', 'Move to the database or cloud queue once a job is slow.', SetupRow::link('Queues', self::QUEUE_DOCS));
        }

        return SetupRow::of('Connection', 'ok', $detail);
    }

    public static function queueTimeout(): ?SetupRow
    {
        if (self::sync()) {
            return null;
        }
        $queue = self::queue();
        $timeout = (int) config('kit.setup.queue.worker_timeout', 60);
        $flex = (bool) config('kit.setup.queue.flex', false);
        $limitName = isset($queue['config']['visibility_timeout']) ? 'visibility timeout' : 'retry_after';
        $limit = (int) ($queue['config']['visibility_timeout'] ?? $queue['config']['retry_after'] ?? 90);
        $detail = 'Worker timeout '.$timeout.'s, '.$limitName.' '.$limit.'s'.($flex ? ', on a Flex queue' : '').'.';
        $link = SetupRow::link('Queues', self::QUEUE_DOCS);
        if ($timeout >= $limit) {
            return SetupRow::of('Timeout', 'failing', $detail.' A job still running at '.$limitName.' is handed to a second worker.', 'Lower the worker timeout below '.$limitName.', or raise '.$limitName.'.', $link);
        }
        if ($flex && $timeout > self::FLEX_LIMIT && ! self::local()) {
            return SetupRow::of('Timeout', 'failing', $detail.' A Flex queue stops a job after '.self::FLEX_LIMIT.'s.', 'Lower the worker timeout to '.self::FLEX_LIMIT.'s or less.', $link);
        }

        return SetupRow::of('Timeout', 'ok', $detail);
    }

    /** Failed jobs in the last 24 hours: the failed_jobs table, plus what `kit.setup.queue.failed_count` (a callable: Cloud's store) adds. Null when neither can be read. */
    public static function failedCount(): ?int
    {
        $count = null;
        $table = (string) config('queue.failed.table', 'failed_jobs');
        if (Schema::hasTable($table)) {
            $count = DB::table($table)->where('failed_at', '>=', now()->subDay())->count();
        }
        $extra = config('kit.setup.queue.failed_count');
        if (is_callable($extra) && ! self::local()) {
            $count = ($count ?? 0) + (int) $extra();
        }

        return $count;
    }

    public static function queueFailed(): ?SetupRow
    {
        if (self::sync()) {
            return null;
        }
        $count = self::failedCount();
        $link = SetupRow::link('Failed jobs', (string) (config('kit.setup.queue.failed_url') ?? self::QUEUE_DOCS));
        if ($count === null) {
            return SetupRow::of('Failed jobs', 'missing', 'No failed-jobs store to read.', 'Create the failed_jobs table (php artisan make:queue-failed-table).', SetupRow::link('Queues', self::QUEUE_DOCS));
        }
        $detail = $count.' failed '.($count === 1 ? 'job' : 'jobs').' in the last 24 hours.';

        return match (true) {
            $count === 0 => SetupRow::of('Failed jobs', 'ok', $detail),
            $count > 25 => SetupRow::of('Failed jobs', 'failing', $detail, 'Read the failures, fix the cause, then retry or forget them. See Queues in the deploy guide.', $link),
            default => SetupRow::of('Failed jobs', 'warning', $detail, 'Read the failures and retry or forget them.', $link),
        };
    }

    private static function waiting(): int
    {
        $queue = self::queue();
        if ($queue['driver'] !== 'database') {
            return 0;
        }
        $table = (string) ($queue['config']['table'] ?? 'jobs');

        return Schema::hasTable($table) ? DB::table($table)->whereNull('reserved_at')->where('available_at', '<=', now()->getTimestamp())->count() : 0;
    }

    public static function queueLastFinished(): ?SetupRow
    {
        if (self::sync()) {
            return null;
        }
        $last = Cache::get(self::LAST_FINISHED);
        $waiting = self::waiting();
        $detail = ($last === null ? 'No job has finished yet.' : 'The last job finished at '.date('Y-m-d H:i', (int) $last).' UTC.').($waiting > 0 ? ' '.$waiting.' waiting.' : '');
        if ($waiting > 0 && ($last === null || (int) $last < now()->subMinutes(self::STALE_MINUTES)->getTimestamp())) {
            return SetupRow::of('Last job', 'warning', $detail.' None has finished in '.self::STALE_MINUTES.' minutes.', 'Check a worker is running (php artisan queue:work).', SetupRow::link('Queues', self::QUEUE_DOCS));
        }

        return SetupRow::of('Last job', 'ok', $detail.' (Read from a mark each finished job leaves in the cache; not yet confirmed on Laravel Cloud\'s managed queue.)');
    }

    public static function queuePruneFailed(): ?SetupRow
    {
        if (self::sync()) {
            return null;
        }
        if (self::local()) {
            return SetupRow::na('Prune failed jobs', 'Only matters in production.');
        }
        // A web request never loads routes/console.php, where the schedule is defined: the console kernel loads it (once) for this read.
        if (app()->hasBeenBootstrapped()) {
            app(ConsoleKernel::class)->all();
        }
        $scheduled = collect(app(Schedule::class)->events())->contains(fn ($event): bool => str_contains((string) $event->command, 'queue:prune-failed'));

        return $scheduled
            ? SetupRow::of('Prune failed jobs', 'ok', 'queue:prune-failed is scheduled.')
            : SetupRow::of('Prune failed jobs', 'failing', 'queue:prune-failed is not scheduled, so failed jobs pile up.', 'Schedule::command(\'queue:prune-failed --hours=168\')->daily() in routes/console.php.', SetupRow::link('Queues', self::QUEUE_DOCS));
    }

    public static function telescope(): ?SetupRow
    {
        if (! class_exists('Laravel\\Telescope\\Telescope') || ! Schema::hasTable('telescope_entries')) {
            return null;
        }
        $count = DB::table('telescope_entries')->count();
        if (self::local()) {
            return SetupRow::na('Entries', number_format($count).' entries.');
        }

        return $count > 100_000
            ? SetupRow::of('Entries', 'warning', number_format($count).' entries.', 'Schedule telescope:prune.')
            : SetupRow::of('Entries', 'ok', number_format($count).' entries.');
    }

    /** One row for every registered setting; never shows an encrypted value. */
    public static function registered(): ?SetupRow
    {
        $settings = app(Registry::class)->settings();
        if ($settings === []) {
            return null;
        }
        $values = app(SettingValues::class);
        $bad = [];
        $overridden = [];
        foreach ($settings as $key => $setting) {
            $resolved = $values->resolve($key);
            $why = $setting['check'] !== null ? ($setting['check'])($resolved['value']) : null;
            if ($why !== null) {
                $bad[] = $key.': '.$why;
            } elseif ($resolved['source'] === 'env') {
                $overridden[] = $key.' ('.$setting['env'].')';
            }
        }

        return match (true) {
            $bad !== [] => SetupRow::of('Registered settings', 'failing', implode('; ', $bad).'.'),
            $overridden !== [] => SetupRow::of('Registered settings', 'overridden', 'Set by the environment: '.implode(', ', $overridden).'.'),
            default => SetupRow::of('Registered settings', 'ok', count($settings).' '.(count($settings) === 1 ? 'setting' : 'settings').', each fine.'),
        };
    }
}
