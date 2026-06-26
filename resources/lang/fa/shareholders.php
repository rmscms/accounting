<?php

declare(strict_types=1);

/** @var array<string, mixed> $main */
$main = require __DIR__.'/accounting.php';

return isset($main['shareholders']) && is_array($main['shareholders']) ? $main['shareholders'] : [];
