<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case PENDING = 'pending';
    case RECEIVED = 'diterima';
    case COMPLETED = 'selesai';
    case CANCELLED = 'dibatalkan';
    case VOIDED = 'voided';
    case DRAFT = 'draft';
    case REJECTED = 'ditolak';
}
