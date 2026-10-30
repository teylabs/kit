@props(['manage' => true])
{{ config('mail.brand.identity', config('app.name')) }}
@if (filled(config('mail.brand.sender')))
{{ config('mail.brand.sender') }}
@endif
@if ($manage)
{{ __('Manage notifications') }}: {{ config('mail.brand.manage_url') }}
@endif
