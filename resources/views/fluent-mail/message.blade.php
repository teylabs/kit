<x-mail::message :manage="! ($security ?? false)" :brand="$kitBrand ?? null">
@if (filled($preheader))
<x-mail::preheader>{{ $preheader }}</x-mail::preheader>
@endif

@foreach ($blocks as $block)
@switch($block['type'])
@case('button')
<x-mail::button :url="$block['url']" :color="$block['color']" :brand="$kitBrand ?? null">
{{ $block['content'] }}
</x-mail::button>
@break
@case('panel')
<x-mail::panel>
<x-mail::fluent-text :markdown="$block['content']" :plain="$block['plain'] ?? $block['content']" />
</x-mail::panel>
@break
@case('table')
<x-mail::table>
<x-mail::fluent-text :markdown="$block['content']" :plain="$block['plain'] ?? $block['content']" />
</x-mail::table>
@break
@case('view')
@include($block['content'], $block['data'])
@break
@default
<x-mail::fluent-text :markdown="$block['content']" :plain="$block['plain'] ?? $block['content']" />
@endswitch

@endforeach

{{-- Subcopy: Laravel's standard fallback for the primary action, with Laravel's lang key so translations apply. The label goes through the same
     escaping as every other block (markdown-escaped in the html part, verbatim in the text part), unlike @lang's raw replacement. --}}
{{-- Only for a link the reader can follow: a preview or test send whose button has no real URL yet ("#", empty) shows no fallback line. --}}
@if (filled($actionText ?? null) && preg_match('#^https?://#i', (string) ($actionUrl ?? '')) === 1)
@php
    $subcopyLine = static fn (string $label): string => __("If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\n".'into your web browser:', ['actionText' => $label]);
    $subcopyHref = str_replace([' ', '<', '>'], ['%20', '%3C', '%3E'], (string) $actionUrl);
@endphp
<x-slot:subcopy>
<x-mail::fluent-text :markdown="$subcopyLine(\Tey\Kit\Mail\FluentMailMessage::escape((string) $actionText)).' <span class=\'break-all\'>['.\Tey\Kit\Mail\FluentMailMessage::escape((string) $displayableActionUrl).'](<'.$subcopyHref.'>)</span>'" :plain="$subcopyLine((string) $actionText).' '.$actionUrl" />
</x-slot:subcopy>
@endif
</x-mail::message>
