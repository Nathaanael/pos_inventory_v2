<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\InboundController;
use App\Http\Controllers\Inventory\OutboundController;
use App\Http\Controllers\Inventory\ReturnTransactionController;
use App\Http\Controllers\Inventory\StockOpnameController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\Master\BrandController;
use App\Http\Controllers\Master\CategoryController;
use App\Http\Controllers\Master\DiningTableController;
use App\Http\Controllers\Master\SupplierController;
use App\Http\Controllers\Master\UnitController;
use App\Http\Controllers\Master\UserController;
use App\Http\Controllers\OutboundPrintController;
use App\Http\Controllers\ReturnTransactionPdfController;
use App\Http\Controllers\StockOpnamePdfController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Rute hasil konversi dari Filament (stock-opname-filament)
|--------------------------------------------------------------------------
| URL disamakan dengan panel Filament lama:
|   Panel admin  → path ''        (/, /items, /categories, ...)
|   Panel kasir  → path 'kasir'   (/kasir/...)   — tahap berikutnya
*/

// ----------------------------------------------------------------- Auth
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'store.setup'])->group(function () {
    // ------------------------------------------------------- Panel admin
    Route::middleware('admin')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        // Manajemen Data
        Route::controller(CategoryController::class)->prefix('categories')->name('categories.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::delete('/bulk', 'bulkDestroy')->name('bulk-destroy');
            Route::put('/{category}', 'update')->name('update')->withTrashed();
            Route::delete('/{category}', 'destroy')->name('destroy');
            Route::post('/{category}/restore', 'restore')->name('restore')->withTrashed();
            Route::delete('/{category}/force', 'forceDelete')->name('force-delete')->withTrashed();
        });

        Route::controller(UnitController::class)->prefix('units')->name('units.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::delete('/bulk', 'bulkDestroy')->name('bulk-destroy');
            Route::put('/{unit}', 'update')->name('update')->withTrashed();
            Route::delete('/{unit}', 'destroy')->name('destroy');
            Route::post('/{unit}/restore', 'restore')->name('restore')->withTrashed();
            Route::delete('/{unit}/force', 'forceDelete')->name('force-delete')->withTrashed();
        });

        Route::controller(SupplierController::class)->prefix('suppliers')->name('suppliers.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::delete('/bulk', 'bulkDestroy')->name('bulk-destroy');
            Route::put('/{supplier}', 'update')->name('update');
            Route::delete('/{supplier}', 'destroy')->name('destroy');
        });

        Route::controller(BrandController::class)->prefix('brands')->name('brands.')->middleware('feature:merek')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::delete('/bulk', 'bulkDestroy')->name('bulk-destroy');
            Route::put('/{brand}', 'update')->name('update');
            Route::delete('/{brand}', 'destroy')->name('destroy');
        });

        Route::controller(DiningTableController::class)->prefix('meja')->name('meja.')->middleware('feature:meja')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::put('/{meja}', 'update')->name('update');
            Route::patch('/{meja}/toggle', 'toggle')->name('toggle');
            Route::delete('/{meja}', 'destroy')->name('destroy');
        });

        // Inventaris
        Route::controller(ItemController::class)->prefix('items')->name('items.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{item}/edit', 'edit')->name('edit')->withTrashed();
            Route::put('/{item}', 'update')->name('update')->withTrashed();
            Route::get('/{item}/history', 'history')->name('history')->withTrashed();
            Route::get('/{item}/detail', 'show')->name('show')->withTrashed();
        });

        Route::controller(InboundController::class)->prefix('inbounds')->name('inbounds.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::post('/bulk-approve', 'bulkApprove')->name('bulk-approve');
            Route::delete('/bulk', 'bulkDestroy')->name('bulk-destroy');
            Route::get('/{inbound}/edit', 'edit')->name('edit');
            Route::put('/{inbound}', 'update')->name('update');
            Route::get('/{inbound}/detail', 'show')->name('show');
            Route::post('/{inbound}/approve', 'approve')->name('approve');
            Route::post('/{inbound}/void', 'void')->name('void');
        });

        Route::controller(OutboundController::class)->prefix('outbounds')->name('outbounds.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::get('/pos-items', 'posItems')->name('pos-items');
            Route::post('/', 'store')->name('store');
            Route::delete('/bulk', 'bulkDestroy')->name('bulk-destroy');
            Route::get('/{outbound}/detail', 'show')->name('show');
            Route::post('/{outbound}/print-thermal', 'printThermal')->name('print-thermal');
            Route::post('/{outbound}/void', 'void')->name('void');
        });

        Route::controller(StockOpnameController::class)->prefix('stock-opnames')->name('stock-opnames.')->middleware('feature:stock_opname')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{stockOpname}/edit', 'edit')->name('edit');
            Route::put('/{stockOpname}', 'update')->name('update');
            Route::get('/{stockOpname}/detail', 'show')->name('show');
        });

        Route::controller(ReturnTransactionController::class)->prefix('return-transactions')->name('return-transactions.')->middleware('feature:retur')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::post('/bulk-approve', 'bulkApprove')->name('bulk-approve');
            Route::post('/bulk-reject', 'bulkReject')->name('bulk-reject');
            Route::get('/{returnTransaction}/edit', 'edit')->name('edit');
            Route::put('/{returnTransaction}', 'update')->name('update');
            Route::delete('/{returnTransaction}', 'destroy')->name('destroy');
            Route::get('/{returnTransaction}/detail', 'show')->name('show');
            Route::post('/{returnTransaction}/approve', 'approve')->name('approve');
            Route::post('/{returnTransaction}/reject', 'reject')->name('reject');
        });

        // Aplikasi
        Route::controller(UserController::class)->prefix('users')->name('users.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::put('/{user}', 'update')->name('update');
            Route::delete('/{user}', 'destroy')->name('destroy');
        });

        // Dokumen (sama seperti versi lama)
        Route::get('/stock-opname/{stockOpname}/pdf', [StockOpnamePdfController::class, 'download'])->name('stock-opname.pdf');
        Route::get('/return-transaction/{returnTransaction}/pdf', [ReturnTransactionPdfController::class, 'download'])->name('return-transaction.pdf');

        Route::get('/exports/download/{filename}', function (string $filename) {
            // Sanitize filename to prevent directory traversal
            $filename = basename($filename);
            $path = "exports/{$filename}";

            abort_unless(Storage::disk('local')->exists($path), 404, 'File tidak ditemukan.');

            return Storage::disk('local')->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        })->name('export.download');
    });

    // Struk bisa dicetak admin maupun kasir.
    Route::get('/outbound/{outbound}/print', [OutboundPrintController::class, 'print'])->name('outbound.print');
});
