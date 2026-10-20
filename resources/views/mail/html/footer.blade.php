@php($brand = config('mail.brand', []))
<tr>
<td>
<table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center">
<p>{{ $brand['identity'] ?? config('app.name') }}</p>
@if (filled($brand['sender'] ?? null))
<p>{{ $brand['sender'] }}</p>
@endif
<p><a href="{{ $brand['manage_url'] ?? url('/') }}">{{ __('Manage notifications') }}</a></p>
</td>
</tr>
</table>
</td>
</tr>
