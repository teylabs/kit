@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
@php($accent = config('mail.brand.accent', '#52525b'))
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
<a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener" style="background-color: {{ $accent }}; border-top: 10px solid {{ $accent }}; border-right: 18px solid {{ $accent }}; border-bottom: 10px solid {{ $accent }}; border-left: 18px solid {{ $accent }};">{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
