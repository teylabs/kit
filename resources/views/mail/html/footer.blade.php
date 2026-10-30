@props(['manage' => true])
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
@if ($manage)
<p><a href="{{ $brand['manage_url'] ?? Tey\Kit\Mail\MailUrl::to('/') }}">{{ __('Manage notifications') }}</a></p>
@endif
</td>
</tr>
</table>
</td>
</tr>
