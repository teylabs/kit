{{-- `manage`: false leaves out the manage-notifications link, for mail a person cannot opt out of (a password reset, an address check). --}}
@props(['manage' => true, 'brand' => null])
@php($brand ??= config('mail.brand', []))
<x-mail::layout :brand="$brand">
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')" :brand="$brand">
{{ $brand['identity'] ?? config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer :manage="$manage" :brand="$brand" />
</x-slot:footer>
</x-mail::layout>
