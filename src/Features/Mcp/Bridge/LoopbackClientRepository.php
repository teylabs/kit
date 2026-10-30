<?php

namespace Tey\Kit\Features\Mcp\Bridge;

use Laravel\Passport\Bridge\Client;
use Laravel\Passport\Bridge\ClientRepository;
use Laravel\Passport\Client as ClientModel;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use Tey\Kit\Features\Mcp\Registration\RedirectUri;

/**
 * Passport's client bridge, with RFC 8252 §7.3 loopback matching for `localhost` as well as the IP literals: a native client (Claude Code) registers
 * `http://localhost/callback` and listens on a port it picks per session. league matches `127.0.0.1` and `[::1]` without the port but not `localhost`,
 * so when the request's redirect_uri is a loopback URI equal to a registered loopback URI but for its port, that exact URI joins the entity's list for this
 * request. Nothing else is widened: a non-loopback URI still has to match exactly.
 */
final class LoopbackClientRepository extends ClientRepository
{
    protected function fromClientModel(ClientModel $model): ClientEntityInterface
    {
        $asked = request()->input('redirect_uri');
        $registered = $model->redirect_uris;
        if (is_string($asked) && RedirectUri::isLoopback($asked) && ! in_array($asked, $registered, true)) {
            $wanted = RedirectUri::withoutPort($asked);
            foreach ($registered as $uri) {
                if (is_string($uri) && RedirectUri::isLoopback($uri) && $wanted !== null && RedirectUri::withoutPort($uri) === $wanted) {
                    return new Client($model->getKey(), $model->name, [...$registered, $asked], $model->confidential(), $model->provider, $model->grant_types);
                }
            }
        }

        return parent::fromClientModel($model);
    }
}
