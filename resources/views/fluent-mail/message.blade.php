<x-mail::message>
@if (filled($preheader))
<x-mail::preheader>{{ $preheader }}</x-mail::preheader>
@endif

@foreach ($blocks as $block)
@switch($block['type'])
@case('button')
<x-mail::button :url="$block['url']" :color="$block['color']">
{{ $block['content'] }}
</x-mail::button>
@break
@case('panel')
<x-mail::panel>
{!! $block['content'] !!}
</x-mail::panel>
@break
@case('table')
<x-mail::table>
{!! $block['content'] !!}
</x-mail::table>
@break
@case('view')
@include($block['content'], $block['data'])
@break
@default
{!! $block['content'] !!}
@endswitch

@endforeach
</x-mail::message>
