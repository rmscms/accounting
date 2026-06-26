<?php

declare(strict_types=1);

namespace RMS\Accounting\Support;

final class AuditActor
{
    public static function context(bool $refresh = false): AuditActorContext
    {
        /** @var AuditColumnWriter $writer */
        $writer = app(AuditColumnWriter::class);

        return $writer->context($refresh);
    }

    public static function adminId(bool $refresh = false): ?int
    {
        return self::context($refresh)->adminId;
    }

    public static function userId(bool $refresh = false): ?int
    {
        return self::context($refresh)->userId;
    }

    public static function actorId(bool $refresh = false): ?int
    {
        return self::context($refresh)->actorId();
    }

    /**
     * @param array<string, mixed> $payload
     * @param string|array<int, string> $actions
     * @return array<string, mixed>
     */
    public static function stamp(array $payload, string $table, string|array $actions, bool $refresh = false): array
    {
        $actions = is_array($actions) ? $actions : [$actions];
        /** @var AuditColumnWriter $writer */
        $writer = app(AuditColumnWriter::class);

        return $writer->stampMany($payload, $table, $actions, self::context($refresh));
    }
}
