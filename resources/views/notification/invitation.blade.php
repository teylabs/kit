<x-mail::message>
<x-mail::preheader>{{ $preheader }}</x-mail::preheader>

# {{ $headline }}

@if (filled($personal))
> {{ $personal }}
>
> {{ __('— :name', ['name' => $inviter]) }}

@endif
{{ $facts }}

<x-mail::button :url="$url">
{{ __('Join :workspace', ['workspace' => $workspace]) }}
</x-mail::button>

{{ __('If the button does not work, open this link: :url', ['url' => $url]) }}
</x-mail::message>
