<?php

use App\Http\Controllers\App\ExportController;
use App\Http\Controllers\App\NotificationController;
use App\Http\Controllers\App\SetupController;
use App\Http\Controllers\App\ShiftManagementController;
use App\Http\Controllers\App\StoreSettingsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\InboundController;
use App\Http\Controllers\Inventory\OutboundController;
use App\Http\Controllers\Inventory\ReturnTransactionController;
use App\Http\Controllers\Inventory\CashCountController;
use App\Http\Controllers\Inventory\StockOpnameController;
use App\Http\Controllers\Inventory\WasteLogController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\Kasir\KasirController;
use App\Http\Controllers\Kasir\KdsController;
use App\Http\Controllers\Kasir\ShiftController;
use App\Http\Controllers\Master\BrandController;
use App\Http\Controllers\Master\CategoryController;
use App\Http\Controllers\Master\DiningTableController;
use App\Http\Controllers\Master\MemberController;
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
|   Panel kasir  → path 'kasir'   (/kasir/pos, /kasir/meja, /kasir/riwayat)
*/

// ----------------------------------------------------------------- Auth
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'store.setup'])->group(function () {
    // ------------------------------------------------------- Panel kasir (admin & kasir)
    Route::controller(KasirController::class)->prefix('kasir')->name('kasir.')->group(function () {
        Route::redirect('/', '/kasir/pos')->name('home');
        Route::get('/pos', 'pos')->name('pos');
        Route::post('/pos/checkout', 'checkout')->name('checkout');
        Route::post('/orders/{outbound}/add-items', 'addOrderItems')->name('orders.add-items');
        Route::post('/orders/{outbound}/pay', 'payOrder')->name('orders.pay');
        Route::post('/orders/{outbound}/void-item/{outboundItem}', 'voidOrderItem')->name('orders.void-item');
        Route::post('/orders/{outbound}/print-kot', 'printKot')->name('orders.print-kot');
        Route::get('/meja', 'meja')->name('meja')->middleware('feature:meja');
        Route::get('/meja/data', 'mejaData')->name('meja.data')->middleware('feature:meja');
        Route::post('/meja/{table}/release', 'release')->name('meja.release')->middleware('feature:meja');
        Route::post('/meja/{table}/move', 'moveTable')->name('meja.move')->middleware('feature:meja');
        Route::get('/riwayat', 'riwayat')->name('riwayat');
        Route::post('/riwayat/{outbound}/print-thermal', 'printThermal')->name('print-thermal');
        Route::get('/belum-siap', 'notReady')->name('belum-siap');
    });

    // Member dari panel kasir: tambah & ubah saja (hapus hanya di panel admin).
    Route::controller(MemberController::class)->prefix('kasir/member')->name('kasir.members.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/search', 'search')->name('search'); // kolom "Nama pelanggan" di POS
        Route::post('/', 'store')->name('store');
        Route::put('/{member}', 'update')->name('update');
    });

    Route::controller(ShiftController::class)->prefix('kasir/shift')->name('kasir.shift.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/open', 'open')->name('open');
        Route::post('/cash-movement', 'storeCashMovement')->name('cash-movement');
        Route::post('/close', 'close')->name('close');
        Route::post('/{shift}/print-report', 'printReport')->name('print-report');
    });

    Route::controller(KdsController::class)->prefix('kasir/kds')->name('kasir.kds.')->middleware('module:modifiers')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/data', 'data')->name('data');
        Route::post('/items/{outboundItem}/status', 'updateItemStatus')->name('item-status');
    });

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

        // Modul FNB tidak memakai supplier.
        Route::controller(SupplierController::class)->prefix('suppliers')->name('suppliers.')->middleware('module:suppliers')->group(function () {
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

        Route::controller(MemberController::class)->prefix('members')->name('members.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::put('/{member}', 'update')->name('update');
            Route::delete('/{member}', 'destroy')->name('destroy');
        });

        Route::controller(DiningTableController::class)->prefix('meja')->name('meja.')->middleware('feature:meja')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::put('/{meja}', 'update')->name('update');
            Route::patch('/{meja}/toggle', 'toggle')->name('toggle');
            Route::delete('/{meja}', 'destroy')->name('destroy');
        });

        // Pajak & Biaya Layanan (modul FNB)
        Route::controller(\App\Http\Controllers\Master\TaxController::class)->prefix('pajak')->name('taxes.')->middleware('module:taxes')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::put('/{tax}', 'update')->name('update');
            Route::patch('/{tax}/toggle', 'toggle')->name('toggle');
            Route::delete('/{tax}', 'destroy')->name('destroy');
        });

        // Modifier & Topping (modul FNB)
        Route::controller(\App\Http\Controllers\Master\ModifierGroupController::class)->prefix('modifiers')->name('modifiers.')->middleware('module:modifiers')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::put('/{modifierGroup}', 'update')->name('update');
            Route::patch('/{modifierGroup}/toggle', 'toggle')->name('toggle');
            Route::delete('/{modifierGroup}', 'destroy')->name('destroy');
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

        // Dipakai semua modul (FNB juga, untuk barang/bahan baku); FNB memakai tampilan default via ModuleView.
        Route::controller(StockOpnameController::class)->prefix('stock-opnames')->name('stock-opnames.')->middleware('feature:stock_opname')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{stockOpname}/edit', 'edit')->name('edit');
            Route::put('/{stockOpname}', 'update')->name('update');
            Route::get('/{stockOpname}/detail', 'show')->name('show');
        });

        Route::controller(CashCountController::class)->prefix('hitung-kas')->name('cash-counts.')->middleware('module:cash_count')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/shift/{shift}', 'create')->name('create');
            Route::post('/shift/{shift}', 'store')->name('store');
            Route::get('/{cashCount}', 'show')->name('show');
            Route::post('/{cashCount}/approve', 'approve')->name('approve');
        });

        Route::controller(WasteLogController::class)->prefix('waste-logs')->name('waste-logs.')->middleware('module:recipes')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
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
        Route::get('/setup', [SetupController::class, 'show'])->name('setup');
        Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');
        Route::post('/setup/ulang', [SetupController::class, 'restart'])->name('setup.restart');
        Route::post('/setup/batal', [SetupController::class, 'cancel'])->name('setup.cancel');

        // Shift Kasir (back office): satu shift berisi banyak kasir, dibuat & dikelola admin.
        Route::controller(ShiftManagementController::class)->prefix('shifts')->name('shifts.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('/{shift}', 'show')->name('show');
            Route::post('/{shift}/members', 'addMember')->name('members.add');
            Route::delete('/{shift}/members/{user}', 'removeMember')->name('members.remove');
            Route::put('/{shift}/supervisor', 'setSupervisor')->name('supervisor');
            Route::post('/{shift}/close', 'close')->name('close');
            Route::delete('/{shift}', 'destroy')->name('destroy');
        });

        Route::get('/pengaturan-toko', [StoreSettingsController::class, 'edit'])->name('store-settings.edit');
        Route::put('/pengaturan-toko', [StoreSettingsController::class, 'update'])->name('store-settings.update');

        Route::get('/export-data', [ExportController::class, 'index'])->name('export-data.index');
        Route::post('/export-data', [ExportController::class, 'store'])->name('export-data.store');
        Route::delete('/export-data/files/{filename}', [ExportController::class, 'destroy'])->name('export-data.destroy');

        Route::post('/notifications/read', [NotificationController::class, 'markAllRead'])->name('notifications.read');
        Route::delete('/notifications', [NotificationController::class, 'clear'])->name('notifications.clear');

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
