@if (session('status'))
    <div role="status">{{ $slot }}</div>
@endif
