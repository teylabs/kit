<?php

use Laravel\Fortify\Features;

// The kit's fortify defaults, laid over the package's own; a published config/fortify.php replaces them. The account pages under /me use Fortify's features and
// actions: profile information, passwords, two-factor authentication (confirmed with a code), email verification, and password reset by emailed link (the kit's
// Auth/ForgotPassword and Auth/ResetPassword pages). Registration stays off: accounts come from invitations.

return ['guard' => 'web', 'middleware' => ['web'], 'username' => 'email', 'email' => 'email', 'home' => '/workspaces', 'views' => true, 'features' => [Features::resetPasswords(), Features::emailVerification(), Features::updateProfileInformation(), Features::updatePasswords(), Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true])]];
