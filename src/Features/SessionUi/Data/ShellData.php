<?php

namespace Tey\Kit\Features\SessionUi\Data;

use Tey\Kit\Features\Shell\Data\ShellData as Moved;

// ShellData moved to the shell feature (Tey\Kit\Features\Shell\Data); the old name stays an alias.
class_alias(Moved::class, ShellData::class);
