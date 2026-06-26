<?php

declare(strict_types=1);

namespace RMS\Accounting\Support;

trait InteractsWithAuditActor
{
    protected function auditWriter(): AuditColumnWriter
    {
        /** @var AuditColumnWriter $writer */
        $writer = app(AuditColumnWriter::class);

        return $writer;
    }

    protected function auditContext(bool $refresh = false): AuditActorContext
    {
        return $this->auditWriter()->context($refresh);
    }

    /**
     * @param array<string, mixed> $payload
     * @param string|array<int, string> $actions
     * @return array<string, mixed>
     */
    protected function stampAudit(array $payload, string $table, string|array $actions): array
    {
        $actions = is_array($actions) ? $actions : [$actions];

        return $this->auditWriter()->stampMany($payload, $table, $actions, $this->auditContext());
    }
}
