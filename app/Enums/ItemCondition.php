<?php

namespace App\Enums;

enum ItemCondition: string
{
    case GOOD = 'baik';
    case DAMAGED = 'rusak';
    case DEFECTIVE = 'cacat';
}
