# Live props

Inertia's live props (inertia-laravel 3.7, Inertia client 3.11) reload a page's props when a broadcast arrives, so a page doesn't have to poll. The kit adds the parts a host would otherwise write by hand:

- a server helper that makes view-model props live;
- an Echo transport;
- a fallback poll for when the socket is down or missing;
- a way for a component's own realtime reader to step aside when the prop it reloads is live.

Everything is opt-in. A host on an older Inertia keeps building, because:

- the kit never imports Inertia's live API;
- `LiveProps::on` returns the props unchanged when inertia-laravel has no live props.

## Server

```php
use Tey\Kit\Features\Core\Live\LiveProps;

public function toArray(): array
{
    return LiveProps::on(parent::toArray(), ['tiles', 'activity'], new BuildChanged($this->talk->key), when: $liveBuildOn);
}
```

- **`when`:** use this for a capability or feature flag. When it's false, the props are plain and the page's poll carries them.
- **`throttle`:** passed through to Inertia (milliseconds between refreshes; Inertia's default is one per second).
- **The notice must be `ShouldBroadcastNow`.** A queued broadcast needs an always-on worker, which a scale-to-zero queue doesn't have. `LiveProps::on` throws for a queued notice even with `when` false, so the mistake shows up before the flag is turned on.
- **Channels:** they come from the notice's `broadcastOn()`. Keep them private and scoped to the workspace, and authorise them in `routes/channels.php`.

## Client

The host wires the transport into Inertia once (Inertia 3.11 or later):

```ts
import { configureLive } from '@inertiajs/core';
import { echoLiveTransport } from '@kit/ui';

configureLive(echoLiveTransport(() => (realtimeConfigured ? echo() : null)));
```

- **Lazy connect:** the `connect` function runs when the first page with a live prop subscribes. If it returns `null` (no broadcaster) or throws, subscriptions do nothing.
- **Shared channels:** two live props on one channel share it, and the channel is left only when the last one stops.
- **Event names:** by default the event name is handed to Echo with a leading dot, so Echo uses the broadcast name as is. Pass `eventName` to map it some other way, and `events` to allow only certain names.
- **Reconnects:** pass `onStatusChange` so that Inertia refreshes every live prop when a dropped connection comes back.
- **Unknown channel types:** a channel type the transport doesn't know fails closed, with no listener.

A host with its own realtime connector, one that already shares and reference-counts channels, uses `listenerLiveTransport` instead. It hands over the connector's listen function, and the kit adds lazy connect, the `events` allow-list and the stop-before-connect guard:

```ts
configureLive(listenerLiveTransport(async () => {
    const connector = await connectRealtime();
    return connector && ((channel, event, handler) => connector.listen({ name: channel.name, private: channel.type === 'private' }, { event: handler, subscribed: () => {} }));
}, { events: ['invalidated'] }));
```

On the page:

```ts
import { useIsLive, useLiveFallbackPoll } from '@kit/ui';

useLiveFallbackPoll(['tiles', 'activity'], { socket: realtimeConfigured }); // 30 s with a socket, 5 s without; visible tab only
const activityLive = useIsLive('activity'); // a component's own reader stands down while this is true
```

`useIsLive(prop)` is true when the server sent `prop` as a live prop on this page. A component that reloads that prop itself (a hand-wired realtime reader) should subscribe only while it is false. Otherwise one broadcast causes two requests.

Tests:

- `tests/LivePropsCheck.php` (`composer test:live-props`) covers the server helper against the installed inertia-laravel.
- `tests/js/live-props.test.mjs` runs the transport against a fake Echo.
- No test here runs a real broadcaster. The first host to adopt this proved it end to end against a local Reverb.
