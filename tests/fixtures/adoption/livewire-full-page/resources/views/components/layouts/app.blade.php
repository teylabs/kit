<!DOCTYPE html>
<html lang="en">
<body>
    <nav>
        <a href="{{ route('settings.profile') }}" wire:navigate>Profile</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Log out</button>
        </form>
    </nav>
    <main>{{ $slot }}</main>
</body>
</html>
