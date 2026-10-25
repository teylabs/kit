<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Http\Request;

/** The step a FocusShell page is: "Step n of m". A page's controller sets it; the shell only reports it. */
final class FocusStep
{
    public static function set(Request $request, int $current, int $total, string $label): void
    {
        $request->attributes->set('kit.focus_step', ['current' => $current, 'total' => $total, 'label' => $label]);
    }

    /** @return ?array{current: int, total: int, label: string} */
    public static function of(Request $request): ?array
    {
        return $request->attributes->get('kit.focus_step');
    }
}
