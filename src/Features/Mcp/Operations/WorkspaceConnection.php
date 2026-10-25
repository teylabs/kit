<?php

namespace Tey\Kit\Features\Mcp\Operations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Passport\Token;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Audit;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Workspaces\Models\Workspace;

class WorkspaceConnection
{
    use AsAction;

    public function approve(Principal $principal, array $input): array
    {
        if ($principal->connectionId !== null || array_diff(array_keys($input), ['token_id', 'scopes', 'intent_key'])) {
            throw new RuntimeException('unknown_arguments');
        }
        $grantable = self::grantableScopes();
        $data = Validator::make($input, ['token_id' => ['required', 'string', 'max:100'], 'scopes' => ['required', 'array', 'min:1', 'max:'.max(1, count($grantable))], 'scopes.*' => ['required', 'string', 'distinct', Rule::in($grantable)], 'intent_key' => ['required', 'string', 'max:120']])->validate();
        sort($data['scopes']);
        if (! in_array(self::baseAbility(), $data['scopes'], true)) {
            throw new RuntimeException('connection_denied');
        }

        return DB::transaction(function () use ($principal, $data): array {
            foreach ($data['scopes'] as $scope) {
                $workspace = app(Access::class)->authorize($principal, $scope);
            }
            Workspace::query()->whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $token = Token::query()->whereKey($data['token_id'])->where('user_id', $principal->actorId)->where('revoked', false)->where('expires_at', '>', now())->first();
            if (! $token || ! $token->client || $token->client->revoked || ! $token->client->hasGrantType('authorization_code') || ! $token->can('mcp:use') || array_diff($data['scopes'], $token->scopes)) {
                throw new RuntimeException('connection_denied');
            }
            $fingerprint = hash('sha256', json_encode([$workspace->id, $data['token_id'], $data['scopes']], JSON_THROW_ON_ERROR));
            $previous = DB::table('connections')->where('actor_id', $principal->actorId)->where('intent_key', $data['intent_key'])->first();
            if ($previous) {
                if ($previous->fingerprint !== $fingerprint) {
                    throw new RuntimeException('connection_intent_conflict');
                }
                if ($previous->revoked_at !== null) {
                    throw new RuntimeException('connection_revoked');
                }

                return ['id' => $previous->id, 'created' => false];
            }
            if (DB::table('connections')->where('workspace_id', $workspace->id)->where('actor_id', $principal->actorId)->where('oauth_token_id', $token->id)->whereNull('revoked_at')->where('expires_at', '>', now())->exists()) {
                throw new RuntimeException('connection_active');
            }
            $id = DB::table('connections')->insertGetId(['workspace_id' => $workspace->id, 'actor_id' => $principal->actorId, 'oauth_token_id' => $token->id, 'scopes' => json_encode($data['scopes'], JSON_THROW_ON_ERROR), 'intent_key' => $data['intent_key'], 'fingerprint' => $fingerprint, 'expires_at' => $token->expires_at]);

            app(Audit::class)->write($principal->actorId, $workspace->id, 'workspace.connection.approved', ['connection_id' => $id, 'client_id' => $token->client_id, 'scopes' => $data['scopes']]);

            return ['id' => $id, 'created' => true];
        });
    }

    /** The ability every connection must hold, and that opening or managing a connection checks. */
    public static function baseAbility(): string
    {
        return (string) config('kit.mcp.connection_ability', 'settings.read');
    }

    /** @return list<string> Workspace abilities a connection can be granted: the adopted features' token scopes. */
    public static function grantableScopes(): array
    {
        $abilities = app(Registry::class)->abilityMap();

        return array_values(array_filter(array_keys(app(Registry::class)->tokenScopes()), fn (string $scope): bool => isset($abilities[$scope])));
    }

    /** The connection guard Access calls for a delegated principal: the connection must be live, in this workspace, and granted the ability. */
    public static function guard(Principal $principal, object $workspace, string $ability): void
    {
        $connection = DB::table('connections')->where('id', $principal->connectionId)->where('actor_id', $principal->actorId)->where('workspace_id', $workspace->id)->whereNull('revoked_at')->first();
        if (! $connection || ! in_array($ability, json_decode($connection->scopes, true), true)) {
            throw new RuntimeException('connection_denied');
        }
    }

    public function revoke(Principal $principal, int $connectionId): array
    {
        return DB::transaction(function () use ($principal, $connectionId): array {
            $workspace = app(Access::class)->authorize($principal, self::baseAbility());
            Workspace::query()->whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $connection = DB::table('connections')->where('id', $connectionId)->where('workspace_id', $workspace->id)->where('actor_id', $principal->actorId)->first();
            abort_unless($connection, 404);
            if ($connection->revoked_at === null) {
                DB::table('connections')->where('id', $connectionId)->update(['revoked_at' => now()]);
                app(Audit::class)->write($principal->actorId, $workspace->id, 'workspace.connection.revoked', ['connection_id' => $connectionId]);
            }

            return ['id' => $connectionId, 'revoked' => true];
        });
    }
}
