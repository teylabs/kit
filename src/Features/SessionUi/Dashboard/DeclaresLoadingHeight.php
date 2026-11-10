<?php

namespace Tey\Kit\Features\SessionUi\Dashboard;

/**
 * Opt-in for a dashboard widget: the card body's minimum height while it is ready, so the space a deferred list or chart fills is held before it arrives
 * and the cards below do not shift. Only a widget that implements this interface is read for it, so a host widget's own method of the same name is never
 * touched. A plain CSS length in `px`, `rem` or `em` (`'18rem'`, `'.5rem'`; up to 32 characters, no `calc()`); null leaves the body its natural height.
 * Any other value turns the widget into an error card.
 */
interface DeclaresLoadingHeight
{
    public function loadingHeight(): ?string;
}
