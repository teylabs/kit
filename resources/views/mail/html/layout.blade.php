{{-- Laravel's base mail layout, with a dark scheme. `color-scheme: light dark` lets Apple Mail and iOS Mail apply the `prefers-color-scheme` rules below,
     and Outlook's dark mode (`[data-ogsc]` text, `[data-ogsb]` backgrounds) gets the same colours. The Gmail apps honour neither and recolour on their own;
     what reads there is what needs no help: the lockup on its own plate (Brand::plateMailLockup) and the button's light fill, inline (Brand::mailButton); its dark fill holds 3:1 on the dark card.
     Every text colour holds 4.5:1 on its surface in both schemes. --}}
@props(['brand' => null])
@php($button = Tey\Kit\Brand::mailButton($brand ?? config('mail.brand', [])))
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<title>{{ config('app.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<style>
:root {
color-scheme: light dark;
supported-color-schemes: light dark;
}

@media only screen and (max-width: 600px) {
.inner-body {
width: 100% !important;
}

.footer {
width: 100% !important;
}
}

@media only screen and (max-width: 500px) {
.button {
width: 100% !important;
}
}

@media (prefers-color-scheme: dark) {
body, .wrapper, .body {
background-color: #09090b !important;
border-color: #09090b !important;
}

.inner-body {
background-color: #18181b !important;
border-color: #27272a !important;
}

.panel-content {
background-color: #27272a !important;
}

.panel {
border-color: #a1a1aa !important;
}

.subcopy, .table th {
border-color: #3f3f46 !important;
}

body, p, li, td, th, blockquote, .panel-content, .panel-content p, .table td {
color: #d4d4d8 !important;
}

h1, h2, h3, .header a, .content-cell a:not(.button) {
color: #fafafa !important;
}

.footer p, .footer a {
color: #a1a1aa !important;
}

.button {
background-color: {{ $button['background_dark'] }} !important;
border-color: {{ $button['background_dark'] }} !important;
color: {{ $button['foreground_dark'] }} !important;
}
}

[data-ogsb] body, [data-ogsb] .wrapper, [data-ogsb] .body {
background-color: #09090b !important;
}

[data-ogsb] .inner-body {
background-color: #18181b !important;
}

[data-ogsb] .panel-content {
background-color: #27272a !important;
}

[data-ogsb] .button {
background-color: {{ $button['background_dark'] }} !important;
border-color: {{ $button['background_dark'] }} !important;
}

[data-ogsc] .button {
color: {{ $button['foreground_dark'] }} !important;
}

[data-ogsc] p, [data-ogsc] li, [data-ogsc] td, [data-ogsc] .panel-content p, [data-ogsc] .table td {
color: #d4d4d8 !important;
}

[data-ogsc] h1, [data-ogsc] h2, [data-ogsc] h3, [data-ogsc] .header a, [data-ogsc] .content-cell a:not(.button) {
color: #fafafa !important;
}

[data-ogsc] .footer p, [data-ogsc] .footer a {
color: #a1a1aa !important;
}
</style>
{!! $head ?? '' !!}
</head>
<body>

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
{!! $header ?? '' !!}

<!-- Email Body -->
<tr>
<td class="body" width="100%" cellpadding="0" cellspacing="0" style="border: hidden !important;">
<table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<!-- Body content -->
<tr>
<td class="content-cell">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>
</table>
</td>
</tr>

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>
