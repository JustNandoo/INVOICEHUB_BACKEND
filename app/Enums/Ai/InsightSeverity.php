<?php

namespace App\Enums\Ai;

enum InsightSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Maps onto the tone vocabulary the dashboard cards already use. */
    public function tone(): string
    {
        return match ($this) {
            self::Critical => 'pink',
            self::Warning => 'yellow',
            self::Info => 'blue',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::Critical => 3,
            self::Warning => 2,
            self::Info => 1,
        };
    }
}
