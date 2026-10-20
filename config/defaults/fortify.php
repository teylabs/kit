<?php

use Laravel\Fortify\Features;

// The kit's fortify defaults, laid over the package's own; a published config/fortify.php replaces them. The account pages under /me use Fortify's features and
// actions: profile information, passwords, two-factor authentication (confirmed with a code) and email verification. Registration and password reset stay off.

return ['guard' => 'web', 'middleware' => ['web'], 'username' => 'email', 'email' => 'email', 'home' => '/workspaces', 'views' => true, 'features' => [Features::emailVerification(), Features::updateProfileInformation(), Features::updatePasswords(), Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true])]];
