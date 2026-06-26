<?php

declare(strict_types=1);

namespace RMS\Accounting\Support;

use Illuminate\Support\Facades\Schema;
use RMS\Accounting\Services\AuditActorResolver;

final class AuditColumnWriter
{
    /**
     * @var array<string, array<string, bool>>
     */
    private array $columnPresenceCache = [];

    public function __construct(private readonly AuditActorResolver $resolver)
    {
    }

    public function context(bool $refresh = false): AuditActorContext
    {
        return $this->resolver->resolve($refresh);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function stamp(array $payload, string $table, string $action, ?AuditActorContext $context = null): array
    {
        return $this->stampMany($payload, $table, [$action], $context);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $actions
     * @return array<string, mixed>
     */
    public function stampMany(array $payload, string $table, array $actions, ?AuditActorContext $context = null): array
    {
        $context ??= $this->resolver->resolve();

        foreach ($actions as $action) {
            $adminColumn = sprintf('%s_by_admin_id', $action);
            $userColumn = sprintf('%s_by_user_id', $action);

            if ($this->hasColumn($table, $adminColumn) && ! array_key_exists($adminColumn, $payload)) {
                $payload[$adminColumn] = $context->adminId;
            }

            if ($this->hasColumn($table, $userColumn) && ! array_key_exists($userColumn, $payload)) {
                $payload[$userColumn] = $context->userId;
            }
        }

        return $payload;
    }

    private function hasColumn(string $table, string $column): bool
    {
        if (isset($this->columnPresenceCache[$table][$column])) {
            return $this->columnPresenceCache[$table][$column];
        }

        try {
            $hasColumn = Schema::hasColumn($table, $column);
        } catch (\Throwable) {
            $hasColumn = false;
        }

        $this->columnPresenceCache[$table][$column] = $hasColumn;

        return $hasColumn;
    }
}
