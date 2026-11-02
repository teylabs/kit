<?php

namespace Tey\Kit\Features\SessionUi\Contracts;

use Illuminate\Http\Request;

/**
 * Where Cancel goes on the confirm-password page (Fortify's password.confirm). The kit binds ConfirmPasswordCancel; a host binds its own to send Cancel
 * elsewhere. The URL is rendered as a link as it is given, so a host's resolver keeps it on the app (SafeRedirect::sameOrigin) itself.
 */
interface ConfirmPasswordCancelUrl
{
    public function resolve(Request $request): string;
}
