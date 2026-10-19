<?php

namespace App\Domain\Billing;

// Host code the kit never owns.
final readonly class Invoice
{
    public function __construct(public int $cents) {}
}
