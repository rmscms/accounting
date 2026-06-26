<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RMS\Accounting\Support\AuditActorContext;

final class AuditActorResolver
{
    private ?AuditActorContext $cachedContext = null;

    /**
     * @var array<string, bool>
     */
    private array $tablePresenceCache = [];

    public function __construct(private readonly AuthFactory $authFactory)
    {
    }

    public function resolve(bool $refresh = false): AuditActorContext
    {
        if (! $refresh && $this->cachedContext instanceof AuditActorContext) {
            return $this->cachedContext;
        }

        $adminId = $this->resolveAdminId();
        $userId = $adminId === null ? $this->resolveUserId() : null;

        $this->cachedContext = new AuditActorContext($adminId, $userId);

        return $this->cachedContext;
    }

    public function resolveAdminId(): ?int
    {
        $candidate = $this->resolveGuardId('admin');
        if ($candidate === null) {
            return null;
        }

        return $this->recordExists('admins', $candidate) ? $candidate : null;
    }

    public function resolveUserId(): ?int
    {
        $candidates = [
            $this->resolveGuardId(null),
            $this->resolveGuardId('web'),
            $this->resolveGuardId('api'),
            $this->resolveGuardId('sanctum'),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                continue;
            }

            if ($this->recordExists('users', $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveGuardId(?string $guard): ?int
    {
        try {
            $resolved = $guard === null
                ? $this->authFactory->guard()->id()
                : $this->authFactory->guard($guard)->id();
        } catch (\Throwable) {
            return null;
        }

        $id = is_numeric($resolved) ? (int) $resolved : 0;

        return $id > 0 ? $id : null;
    }

    private function recordExists(string $table, int $id): bool
    {
        if ($id <= 0 || ! $this->hasTable($table)) {
            return false;
        }

        try {
            return DB::table($table)->where('id', $id)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasTable(string $table): bool
    {
        if (array_key_exists($table, $this->tablePresenceCache)) {
            return $this->tablePresenceCache[$table];
        }

        try {
            $hasTable = Schema::hasTable($table);
        } catch (\Throwable) {
            $hasTable = false;
        }

        $this->tablePresenceCache[$table] = $hasTable;

        return $hasTable;
    }
}
