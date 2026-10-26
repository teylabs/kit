<?php

namespace Tey\Kit\Features\SessionUi\Data;

use Tey\Kit\Features\Shell\Data\ShellKind as Moved;

// ShellKind moved to the shell feature (Tey\Kit\Features\Shell\Data); the old name stays an alias.
class_alias(Moved::class, ShellKind::class);
