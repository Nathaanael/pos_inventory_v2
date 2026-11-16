<?php

namespace App\Support;

use Illuminate\Support\Arr;

/**
 * Data untuk membuka kembali modal tambah/ubah setelah redirect
 * (validasi gagal, atau tombol "Simpan & buat lagi"). Dipakai bersama crudPage() di JS.
 */
class CrudPage
{
    public static function reopen(): ?array
    {
        $errors = session('errors');

        if ($errors && $errors->any() && old('_mode')) {
            return [
                'mode' => old('_mode') === 'edit' ? 'edit' : 'create',
                'id' => old('_id'),
                'form' => Arr::except(old(), ['_token', '_method', '_mode', '_id', 'create_another']),
            ];
        }

        if (session('create_another')) {
            return ['mode' => 'create', 'id' => null, 'form' => []];
        }

        return null;
    }
}
