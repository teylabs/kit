<?php

namespace Tey\Kit\Features\Deliver\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Deliver\Operations\DeliveryDesk;

/** The document as it is now, inline, never cached: for the preview's PDF frame. */
class AttachmentController
{
    use AnswersDesk;

    public function show(Request $request, int $workspace, string $definition, string $subject): mixed
    {
        $file = app(DeliveryDesk::class)->attachment($this->principal($request), $definition, $subject);

        return response($file->contents, 200, ['Content-Type' => $file->mime, 'Content-Disposition' => 'inline; filename="'.addcslashes($file->filename, '"\\').'"', 'Cache-Control' => 'no-store, private']);
    }
}
