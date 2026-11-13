<?php

namespace Tey\Kit\Features\Finalize\Models;

use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Features\Finalize\Concerns\IsFinalization;
use Tey\Kit\Features\Finalize\Contracts\Finalization as FinalizationContract;

/**
 * The kit's finalization model, on `kit_finalizations`: the default for `kit.models.finalization` until the app adopts finalize
 * (`kit:scaffold finalize --adopt`), which gives it its own model on `finalizations`. Deprecated since 0.19 for new code: reach the finalization
 * model through `Tey\Kit\Features\Core\Kit::model('finalization')`. The behaviour is Concerns\IsFinalization.
 */
class Finalization extends Model implements FinalizationContract
{
    use IsFinalization;

    protected $table = 'kit_finalizations';
}
