<?php

namespace Tey\Kit\Features\Deliver\Http;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tey\Kit\Features\Deliver\Operations\DeliveryDesk;

/**
 * The customer's signed link. A bad signature, an unknown delivery or a wrong token is the same bare 404, never a reason. A link that is right but
 * expired or revoked is a 410 (or the definition's expiredPage). Nothing here is ever cached.
 */
class CustomerLinkController
{
    public function show(Request $request, int $delivery, string $token): Response
    {
        // The signature is checked apart from its expiry, so an expired link is told from a tampered one.
        $signed = URL::hasCorrectSignature($request);
        $expired = ! URL::signatureHasNotExpired($request);
        try {
            return app(DeliveryDesk::class)->open($delivery, $token, $signed, $expired, $request->user());
        } catch (ModelNotFoundException) {
            throw $this->notFound();
        } catch (HttpExceptionInterface $error) {
            throw $error->getStatusCode() === 404 ? $this->notFound() : $error;
        }
    }

    private function notFound(): NotFoundHttpException
    {
        return new NotFoundHttpException('', null, 0, ['Cache-Control' => 'no-store, private', 'X-Robots-Tag' => 'noindex, nofollow', 'Referrer-Policy' => 'no-referrer']);
    }
}
