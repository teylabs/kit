<?php

namespace Tey\Kit\Features\Finalize\Models;

use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Features\Finalize\Concerns\IsConfirmation;
use Tey\Kit\Features\Finalize\Contracts\Confirmation as ConfirmationContract;

/**
 * The kit's finalization confirmation model, on `kit_finalize_confirmations`: the default for `kit.models.finalization_confirmation` until the app
 * adopts finalize. Deprecated since 0.19 for new code: reach it through `Tey\Kit\Features\Core\Kit::model('finalization_confirmation')`. The behaviour is
 * Concerns\IsConfirmation.
 */
class Confirmation extends Model implements ConfirmationContract
{
    use IsConfirmation;

    protected $table = 'kit_finalize_confirmations';
}
