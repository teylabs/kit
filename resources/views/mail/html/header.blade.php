@props(['url'])
@php($brand = config('mail.brand', []))
<tr>
<td class="header">
<table class="header-inner" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation" style="border-bottom: 2px solid {{ $brand['accent'] ?? '#52525b' }};">
<tr>
<td align="left" style="padding: 24px 0 16px;">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
@if (! empty($brand['mark']))
{{-- The kit swaps this placeholder for the mark as an inline CID attachment when the message is sent. --}}
<img src="kit-mark:" width="40" height="40" alt="{{ trim(strip_tags((string) $slot)) }}" style="vertical-align: middle; border: 0;">
@endif
<strong style="vertical-align: middle;">{!! $slot !!}</strong>
</a>
</td>
</tr>
</table>
</td>
</tr>
