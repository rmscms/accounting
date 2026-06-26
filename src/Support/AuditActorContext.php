<?php

declare(strict_types=1);

namespace RMS\Accounting\Support;

final class AuditActorContext
{
    public function __construct(
        public readonly ?int $adminId,
        public readonly ?int $userId
    ) {
    }

    public function hasAdmin(): bool
    {
        return $this->adminId !== null && $this->adminId > 0;
    }

    public function hasUser(): bool
    {
        return $this->userId !== null && $this->userId > 0;
    }

    public function actorType(): ?string
    {
        if ($this->hasAdmin()) {
            return 'admin';
        }
        if ($this->hasUser()) {
            return 'user';
        }

        return null;
    }

    public function actorId(): ?int
    {
        if ($this->hasAdmin()) {
            return $this->adminId;
        }
        if ($this->hasUser()) {
            return $this->userId;
        }

        return null;
    }
}
