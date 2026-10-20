@props(['url', 'message' => null])
@php($brand = config('mail.brand', []))
<tr>
<td class="header">
<table class="header-inner" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation" style="border-bottom: 2px solid {{ $brand['accent'] ?? '#4f46e5' }};">
<tr>
<td align="left" style="padding: 24px 0 16px;">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
@if ($message && ! empty($brand['mark']))
<img src="{{ $message->embed($brand['mark']) }}" width="40" height="40" alt="{{ trim(strip_tags((string) $slot)) }}" style="vertical-align: middle; border: 0;">
@endif
<span style="vertical-align: middle; font-size: 19px; font-weight: bold;">{!! $slot !!}</span>
</a>
</td>
</tr>
</table>
</td>
</tr>
