<?php

namespace Tey\Kit\Features\Notes\Models;

use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Features\Notes\Concerns\IsNote;
use Tey\Kit\Features\Notes\Contracts\Note as NoteContract;

/**
 * The kit's note model, on `kit_notes`: the default for `kit.models.note` until the app adopts notes (`kit:scaffold notes --adopt`), which gives it
 * its own model on `notes`. Deprecated since 0.19 for new code: reach the note model through `Tey\Kit\Kit::model('note')`. The behaviour is
 * Concerns\IsNote.
 */
class Note extends Model implements NoteContract
{
    use IsNote;

    protected $table = 'kit_notes';
}
