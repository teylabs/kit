@props(['url'])
@php($lockup = config('mail.brand.lockup'))
<tr>
<td class="header" align="center">
<a href="{{ $url }}" style="display: inline-block;">
@if (is_array($lockup) && is_string($lockup['file'] ?? null) && is_file($lockup['file']))
{{-- The kit swaps this placeholder for the lockup as an inline CID attachment when the message is sent: mail never links a remote image. --}}
<img src="kit-lockup:" width="{{ (int) $lockup['width'] }}" height="{{ (int) $lockup['height'] }}" alt="{{ config('app.name') }}" style="display: block; width: {{ (int) $lockup['width'] }}px; max-width: 100%; height: auto; border: 0; margin: 0 auto;">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
