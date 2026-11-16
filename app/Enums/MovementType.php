<?php

namespace App\Enums;

enum MovementType: string
{
    case INBOUND = 'inbound';
    case OUTBOUND = 'outbound';
    case RETURN_IN = 'return_in';
    case RETURN_OUT = 'return_out';
    case ADJUSTMENT = 'adjustment';
    case INITIAL = 'initial';
    case VOID = 'void';
}
