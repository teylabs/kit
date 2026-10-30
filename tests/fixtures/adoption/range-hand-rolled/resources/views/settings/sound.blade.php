<form method="post" action="/settings/sound">
    @csrf
    <label for="volume">Volume</label>
    <input id="volume" type="range" name="volume" min="0" max="100" value="40">
    <button type="submit">Save</button>
</form>
