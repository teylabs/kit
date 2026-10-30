<?php

namespace Tey\Kit\Mail;

use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Email;

/**
 * The brand's images reach mail as inline CID attachments, never as remote or data images: the header's `src="kit-lockup:"` (the lockup) and `src="kit-mark:"` (the mark a
 * host's own view may still use) become `cid:` references to the local PNGs in `mail.brand`, embedded in the message. A placeholder with no file stays out of the views.
 */
final class InlineBrandImages
{
    /** placeholder => [content id, the `mail.brand` key that holds its file] */
    private const array IMAGES = ['kit-lockup' => 'lockup', 'kit-mark' => 'mark'];

    public function handle(MessageSending $event): void
    {
        self::embed($event->message);
    }

    public static function embed(Email $message): void
    {
        foreach (self::IMAGES as $id => $key) {
            $html = $message->getHtmlBody();
            $file = config('mail.brand.'.$key);
            $file = is_array($file) ? ($file['file'] ?? null) : $file;
            if (is_string($html) && is_string($file) && is_file($file) && str_contains($html, 'src="'.$id.':"')) {
                $message->embedFromPath($file, $id, 'image/png');
                $message->html(str_replace('src="'.$id.':"', 'src="cid:'.$id.'"', $html));
            }
        }
    }
}
