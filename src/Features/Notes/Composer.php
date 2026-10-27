<?php

namespace Tey\Kit\Features\Notes;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Notes\Data\NoteComposerData;
use Throwable;

/** Builds the composer for a request, with the app's hooks applied. A hook that throws adds nothing; the composer itself never fails on one. */
final class Composer
{
    /** @param  array{type: string, id: int}|null  $subject */
    public static function for(Request $request, int $workspace, ?array $subject = null): NoteComposerData
    {
        $media = (array) config('kit.notes.media', []);
        $options = [];
        $search = null;
        foreach (app(Registry::class)->noteComposers() as $hook) {
            try {
                $added = (array) $hook($request);
            } catch (Throwable) {
                continue;
            }
            foreach ((array) ($added['options'] ?? []) as $option) {
                if (is_array($option) && is_string($option['key'] ?? null) && is_string($option['label'] ?? null)) {
                    $options[$option['key']] = ['key' => $option['key'], 'label' => $option['label'], 'icon' => is_string($option['icon'] ?? null) ? $option['icon'] : null, 'url' => is_string($option['url'] ?? null) ? $option['url'] : null];
                }
            }
            $search = is_string($added['subject_search'] ?? null) ? $added['subject_search'] : $search;
        }

        return new NoteComposerData(
            new Action('Add note', NoteUrls::index($workspace), 'post', key: 'save'),
            (string) config('kit.notes.placeholder', 'Write a note…'),
            Colours::options(),
            Colours::default(),
            ['accept' => array_values((array) ($media['accept'] ?? ['image/png', 'image/jpeg', 'application/pdf'])), 'max_files' => (int) ($media['max_files'] ?? 5), 'max_kb' => (int) ($media['max_kb'] ?? 10240)],
            $subject,
            array_values($options),
            $search,
        );
    }
}
