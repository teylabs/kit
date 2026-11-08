@props(['manage' => true, 'brand' => null])
@php($brand ??= config('mail.brand', []))
{{ $brand['footer_identity'] ?? $brand['identity'] ?? config('app.name') }}
@if (filled($brand['sender'] ?? null))
{{ $brand['sender'] }}
@endif
@if ($manage)
{{ __('Manage notifications') }}: {{ $brand['manage_url'] ?? '' }}
@endif
