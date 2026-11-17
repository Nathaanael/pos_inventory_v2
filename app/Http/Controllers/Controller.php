<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Respons sukses untuk form biasa maupun ajaxForm (resources/js/components/crud/ajax-form.js).
     *
     * @param  string|array{0: string, 1?: string}  $message  judul toast, atau [judul, keterangan]
     * @param  string|null  $redirect  halaman tujuan; kosong = kembali ke halaman sebelumnya / tetap di halaman (AJAX)
     */
    protected function respond(Request $request, string|array $message, ?string $redirect = null): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            [$title, $body] = is_array($message) ? [$message[0], $message[1] ?? null] : [$message, null];

            // Pindah halaman setelah AJAX: toast ikut tampil di halaman tujuan lewat session flash.
            if ($redirect) {
                session()->flash('success', $message);
            }

            return response()->json(array_filter(['message' => $title, 'body' => $body, 'redirect' => $redirect]));
        }

        return ($redirect ? redirect()->to($redirect) : back())->with('success', $message);
    }

    /**
     * Respons gagal (bukan error validasi) untuk form biasa maupun ajaxForm.
     *
     * @param  string|array{0: string, 1?: string}  $message
     */
    protected function respondError(Request $request, string|array $message, int $status = 409): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            [$title, $body] = is_array($message) ? [$message[0], $message[1] ?? null] : [$message, null];

            return response()->json(array_filter(['message' => $title, 'body' => $body]), $status);
        }

        return back()->with('error', $message);
    }
}
