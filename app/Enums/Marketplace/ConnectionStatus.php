<?php

namespace App\Enums\Marketplace;

enum ConnectionStatus: string
{
    case Pending = 'pending';
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case Error = 'error';
    case Expired = 'expired';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu otorisasi',
            self::Connected => 'Terhubung',
            self::Disconnected => 'Tidak terhubung',
            self::Error => 'Bermasalah',
            self::Expired => 'Izin kedaluwarsa',
        };
    }
}
