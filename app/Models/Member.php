<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Member / pelanggan terdaftar (nama, nomor telepon, tanggal lahir). */
class Member extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Simpan nomor telepon tanpa spasi, titik, tanda hubung, atau kurung: "0812-3456 789" → "08123456789". */
    public static function normalizePhone(?string $phone): string
    {
        return preg_replace('/[\s.\-()]/', '', (string) $phone);
    }

    /** "0812 3456 7890" untuk tampilan. */
    public function formattedPhone(): string
    {
        return trim(chunk_split($this->phone, 4, ' '));
    }
}
