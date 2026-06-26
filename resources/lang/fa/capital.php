<?php

declare(strict_types=1);

/** @var array<string, mixed> $main */
$main = require __DIR__.'/accounting.php';

return isset($main['capital']) && is_array($main['capital']) ? $main['capital'] : [];
