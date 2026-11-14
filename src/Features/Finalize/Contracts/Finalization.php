<?php

namespace Tey\Kit\Features\Finalize\Contracts;

/**
 * One finalization of a subject: the model `kit.models.finalization` names, which uses Concerns\IsFinalization. The kit's own is Models\Finalization
 * (`kit_finalizations`); an app that adopted finalize has its own (`finalizations`).
 */
interface Finalization {}
