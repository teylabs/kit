@props(['manage' => true, 'brand' => null])
@php($brand ??= config('mail.brand', []))
<tr>
<td>
<table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center">
<p style="color: #71717a;">{{ $brand['footer_identity'] ?? $brand['identity'] ?? config('app.name') }}</p>
@if (filled($brand['sender'] ?? null))
<p style="color: #71717a;">{{ $brand['sender'] }}</p>
@endif
@if ($manage)
<p style="color: #71717a;"><a style="color: #71717a;" href="{{ $brand['manage_url'] ?? Tey\Kit\Mail\MailUrl::to('/') }}">{{ __('Manage notifications') }}</a></p>
@endif
</td>
</tr>
</table>
</td>
</tr>
