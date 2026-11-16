<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasSequentialCode
{
    /**
     * Generate a sequential transaction code.
     * Format: PREFIX-YYYY-MMDD-NNNN
     * 
     * @param string $prefix
     * @param string $column
     * @return string
     */
    public static function generateTransactionCode(string $prefix, string $column = 'transaction_code'): string
    {
        $today = now();
        $datePrefix = $prefix . '-' . $today->format('Y') . '-' . $today->format('md');
        
        $lastTransaction = self::query()
            ->where($column, 'like', $datePrefix . '-%')
            ->orderByDesc($column)
            ->lockForUpdate()
            ->first();
        
        if ($lastTransaction) {
            $lastNumber = (int) Str::afterLast($lastTransaction->{$column}, '-');
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        
        return $datePrefix . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
