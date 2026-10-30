<?php

namespace Tey\Kit\Features\Mcp\Console;

use Illuminate\Console\Command;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tey\Kit\Features\Mcp\Registration\Registrations;

/**
 * The dynamically registered clients: those `POST /oauth/register` recorded in `kit_mcp_registrations`, never a host's own public clients, however alike their rows.
 * Lists them with their last token; `--revoke=<id>` revokes one and every token it holds; `--prune-unused=<days>` revokes those with no token issued in that many
 * days (an assistant registers a new client per fresh connection, so they accumulate); `--adopt=<id>` records a client registered before the kit kept this record
 * (laravel/mcp's registration made the same public, owner-less, secret-less rows), one at a time and by hand. Nothing is deleted: a revoked client stays as the record
 * its tokens point to.
 */
final class ClientsCommand extends Command
{
    protected $signature = 'kit:mcp:clients {--revoke= : Revoke this registered client id and its tokens} {--prune-unused= : Revoke registered clients with no token issued in this many days} {--dry-run : Show what --prune-unused would revoke} {--adopt= : Record a public client made by an earlier registration as a registered client}';

    protected $description = 'List, revoke or prune dynamically registered MCP clients';

    public function handle(ClientRepository $clients): int
    {
        if (! Registrations::available()) {
            $this->error('The kit_mcp_registrations table is missing: run the mcp feature\'s migrations.');

            return self::FAILURE;
        }
        $registered = Registrations::query()->pluck('oauth_client_id')->all();
        $query = Passport::client()->newQuery()->whereIn('id', $registered)->where('revoked', false)->orderBy('created_at');
        if (($id = $this->option('adopt')) !== null) {
            $client = Passport::client()->newQuery()->find($id);
            if ($client === null || $client->owner_id !== null || $client->secret !== null || $client->provider !== null || $client->confidential()) {
                $this->error('No public, owner-less, secret-less client '.$id.' to adopt.');

                return self::FAILURE;
            }
            if (in_array((string) $client->getKey(), $registered, true)) {
                $this->info($client->getKey().' is already a registered client.');

                return self::SUCCESS;
            }
            Registrations::record((string) $client->getKey(), 'web', null, adopted: true);
            $this->info('Adopted '.$client->getKey().' ('.$client->name.') as a registered client.');

            return self::SUCCESS;
        }
        if (($id = $this->option('revoke')) !== null) {
            $client = (clone $query)->find($id);
            if ($client === null) {
                $this->error('No live registered client '.$id.'.');

                return self::FAILURE;
            }
            $clients->delete($client);
            $this->info('Revoked '.$client->getKey().' ('.$client->name.') and its tokens.');

            return self::SUCCESS;
        }
        $days = $this->option('prune-unused');
        if ($days !== null) {
            if (! ctype_digit((string) $days) || (int) $days < 1) {
                $this->error('--prune-unused takes a whole number of days, at least 1.');

                return self::FAILURE;
            }
            $cutoff = now()->subDays((int) $days);
            $pruned = 0;
            foreach ((clone $query)->where('created_at', '<', $cutoff)->get() as $client) {
                if ($client->tokens()->where('created_at', '>=', $cutoff)->exists()) {
                    continue;
                }
                $pruned++;
                $this->line(($this->option('dry-run') ? 'Would revoke ' : 'Revoked ').$client->getKey().' ('.$client->name.')');
                if (! $this->option('dry-run')) {
                    $clients->delete($client);
                }
            }
            $this->info(($this->option('dry-run') ? 'Would revoke ' : 'Revoked ').$pruned.' client(s) unused for '.$days.' days.');

            return self::SUCCESS;
        }
        $rows = [];
        foreach ($query->get() as $client) {
            $rows[] = [$client->getKey(), $client->name, implode(' ', $client->redirect_uris), $client->created_at?->toDateTimeString(), $client->tokens()->max('created_at') ?? '-'];
        }
        $this->table(['Client', 'Name', 'Redirect URIs', 'Registered', 'Last token'], $rows);

        return self::SUCCESS;
    }
}
