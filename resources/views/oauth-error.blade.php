<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $title }}</title>
<style>body{margin:0;padding:2rem 1rem;color:#1a1a1a;background:#fff}main{max-width:36rem;margin:0 auto}h1{line-height:1.3;margin:0 0 .75rem}@media (prefers-color-scheme:dark){body{color:#eee;background:#111}}</style><main>
<h1>{{ $title }}</h1>
<p>The app that sent you here asked for something this server cannot do safely, so you were not sent back to it. Go back to the app and try connecting again; if it keeps happening, the app's maker needs to fix its connection settings.</p>
<p><small>Error <code>{{ $error }}</code>@if ($description): {{ $description }}@endif</small></p>
</main></html>
