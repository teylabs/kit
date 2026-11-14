<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Account\SignedInDevices;

/**
 * Once after upgrading to 0.22, on the database session driver: a `signed_in_devices` row for each signed-in session in the sessions table that has not
 * expired, so the Security page lists the devices signed in before the upgrade. It reads the sessions table once and changes nothing in it. A second run
 * adds nothing (each session's row is keyed by its HMAC). Other drivers can't list a person's sessions: their devices get a row on their next request.
 */
final class BackfillDevicesCommand extends Command
{
    protected $signature = 'kit:devices:backfill';

    protected $description = 'Add a signed-in device for each session signed in before 0.22 (the database session driver only; run once after migrate)';

    public function handle(): int
    {
        $driver = (string) config('session.driver');
        if ($driver !== 'database') {
            $this->error('kit:devices:backfill reads the database session table, and SESSION_DRIVER is '.$driver.'. Other drivers can\'t list a person\'s sessions: their devices appear on their next request. Nothing was added.');

            return self::FAILURE;
        }
        $connection = DB::connection(config('session.connection'));
        $table = (string) config('session.table', 'sessions');
        foreach ([[$connection->getSchemaBuilder(), $table], [Schema::getFacadeRoot(), SignedInDevices::TABLE]] as [$schema, $needed]) {
            if (! $schema->hasTable($needed)) {
                $this->error('The '.$needed.' table is missing: run php artisan migrate first. Nothing was added.');

                return self::FAILURE;
            }
        }
        $model = Users::model();
        $key = (new $model)->getKeyName();
        [$sessions, $added] = [0, 0];
        $connection->table($table)->whereNotNull('user_id')->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime', 120))->getTimestamp())
            ->orderBy('id')->chunk(500, function ($rows) use ($model, $key, &$sessions, &$added): void {
                // Only sessions of people who still have an account: a session left behind by a deleted user gets no row.
                $people = array_flip(array_map('strval', $model::query()->whereKey($rows->pluck('user_id')->unique()->all())->pluck($key)->all()));
                foreach ($rows as $row) {
                    if (! isset($people[(string) $row->user_id])) {
                        continue;
                    }
                    $sessions++;
                    $added += (int) SignedInDevices::adopt((string) $row->id, $row->user_id, $row->user_agent ?? null, $row->ip_address ?? null, now()->setTimestamp((int) $row->last_activity));
                }
            });
        $this->info('Signed-in devices: '.$added.' added for '.$sessions.' signed-in '.($sessions === 1 ? 'session' : 'sessions').' ('.($sessions - $added).' had one already).');

        return self::SUCCESS;
    }
}
