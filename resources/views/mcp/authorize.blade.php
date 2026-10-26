<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Authorize client</title>
<style>button[aria-busy="true"]{cursor:progress;opacity:.7}button[aria-busy="true"]::before{content:"";display:inline-block;width:.8em;height:.8em;margin-right:.4em;vertical-align:-.1em;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:kit-spin .7s linear infinite}@keyframes kit-spin{to{transform:rotate(360deg)}}@media (prefers-reduced-motion:reduce){button[aria-busy="true"]::before{animation:none}}</style><main>
<h1>Authorize {{ $client->name }}</h1><p>This client requests:</p><ul>@foreach ($scopes as $scope)<li>{{ $scope->description }}</li>@endforeach</ul>
<p>Workspace approval is a separate step. This authorization alone grants no workspace access.</p>
<form method="post" action="{{ route('passport.authorizations.approve') }}" data-kit-consent>@csrf<input type="hidden" name="auth_token" value="{{ $authToken }}"><button data-busy-label="Connecting…">Authorize</button></form>
<form method="post" action="{{ route('passport.authorizations.deny') }}" data-kit-consent>@csrf @method('DELETE')<input type="hidden" name="auth_token" value="{{ $authToken }}"><button data-busy-label="Cancelling…">Deny</button></form>
</main>
{{-- The approve and deny posts end in a redirect to the external client, so the page never gets a response to react to: show the busy state on submit, block a second submit, and reset it when the browser restores this page from the back-forward cache. --}}
<script>
(() => {
    const buttons = () => document.querySelectorAll('form[data-kit-consent] button');
    const reset = () => buttons().forEach((button) => { if (button.dataset.label) button.textContent = button.dataset.label; button.disabled = false; button.removeAttribute('aria-busy'); });
    document.querySelectorAll('form[data-kit-consent]').forEach((form) => form.addEventListener('submit', (event) => {
        if (form.dataset.sent === '1') return event.preventDefault();
        form.dataset.sent = '1';
        const button = form.querySelector('button');
        button.dataset.label = button.textContent;
        button.textContent = button.dataset.busyLabel;
        button.setAttribute('aria-busy', 'true');
        // Disable after the submit has been taken, so the button still submits; disable both so the other choice can't race it.
        setTimeout(() => buttons().forEach((other) => { other.disabled = true; }));
    }));
    window.addEventListener('pageshow', (event) => { if (event.persisted) { document.querySelectorAll('form[data-kit-consent]').forEach((form) => delete form.dataset.sent); reset(); } });
})();
</script>
</html>
