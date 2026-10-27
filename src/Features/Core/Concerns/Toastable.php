<?php

namespace Tey\Kit\Features\Core\Concerns;

use Tey\Kit\Features\Core\Toast;

/** For a controller: `$this->toast('Changes saved')->from('save')` flashes a success toast the shell's Toaster renders (see Toast for when to toast and the other types). */
trait Toastable
{
    protected function toast(string $message, ?string $description = null): Toast
    {
        return Toast::success($message, $description);
    }
}
