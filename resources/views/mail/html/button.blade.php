@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
    'brand' => null,
])
{{-- The fill and label (Brand::mailButton): `kit.brand.mail.button` or the brand's button, with white or near-black text at 4.5:1. The dark fill is the
     layout's prefers-color-scheme rule; inline is the light one, so a client that ignores media queries shows that. The label wraps only between words: the theme's
     `.inner-body a { word-break: break-all }` is for long URLs, and broke a label mid-word. Keep labels to a short verb phrase; a name goes in the body copy. --}}
@php($button = Tey\Kit\Brand::mailButton($brand ?? config('mail.brand', [])))
@php($accent = $button['background'])
@php($foreground = $button['foreground'])
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="max-width: 100%;">
<tr>
<td>
<a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener" style="color: {{ $foreground }}; background-color: {{ $accent }}; border-top: 10px solid {{ $accent }}; border-right: 18px solid {{ $accent }}; border-bottom: 10px solid {{ $accent }}; border-left: 18px solid {{ $accent }}; max-width: 100%; text-align: center; word-break: keep-all; overflow-wrap: normal; word-wrap: normal; hyphens: none; -webkit-hyphens: none;">{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
