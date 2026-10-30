<?php

namespace Tey\Kit\Features\Mcp\Console;

use Illuminate\Console\Command;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

/**
 * The dynamically registered clients: public authorization-code clients with no owner, no secret and no provider, as `POST /oauth/register` makes them (and as
 * laravel/mcp's registration made them before). Lists them with their last token; `--revoke=<id>` revokes one and every token it holds; `--prune-unused=<days>`
 * revokes those with no token issued in that many days (an assistant registers a new client per fresh connection, so they accumulate). Nothing is deleted:
 * a revoked client stays as the record its tokens point to.
 */
final class ClientsCommand extends Command
{
    protected $signature = 'kit:mcp:clients {--revoke= : Revoke this client id and its tokens} {--prune-unused= : Revoke registered clients with no token issued in this many days} {--dry-run : Show what --prune-unused would revoke}';

    protected $description = 'List, revoke or prune dynamically registered MCP clients';

    public function handle(ClientRepository $clients): int
    {
        $query = Passport::client()->newQuery()->whereNull('owner_id')->whereNull('secret')->whereNull('provider')->where('revoked', false)->orderBy('created_at');
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
