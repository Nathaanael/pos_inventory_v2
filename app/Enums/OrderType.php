<?php

namespace App\Enums;

/**
 * Tipe pesanan untuk usaha FNB.
 */
enum OrderType: string
{
    case DineIn = 'dine_in';
    case TakeAway = 'take_away';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'Makan di Tempat',
            self::TakeAway => 'Take Away',
        };
    }
}
