/** Format angka untuk form transaksi. */
export const rupiah = (value) => 'Rp ' + Math.round(Number(value) || 0).toLocaleString('id-ID');

export const number = (value) => Math.round(Number(value) || 0).toLocaleString('id-ID');

/** Jumlah stok: desimal hanya bila ada (maks. 4 digit), mis. "1.250" atau "1,25". */
export const qty = (value) => (Number(value) || 0).toLocaleString('id-ID', { maximumFractionDigits: 4 });

/** Jumlah stok dengan tanda: "+1,5", "-2", "0". */
export const signedQty = (value) => {
    const n = Math.round((Number(value) || 0) * 10000) / 10000;
    return (n > 0 ? '+' : '') + qty(n);
};

export const signed = (value) => {
    const n = Math.round(Number(value) || 0);
    return n > 0 ? `+${n}` : String(n);
};

/** Tampilkan toast (lihat <x-common.flash>). */
export const toast = (type, title, body = null) =>
    window.dispatchEvent(new CustomEvent('toast', { detail: { type, title, body } }));

/** Peta id → barang dari katalog ItemCatalog::forForms(). */
export const indexCatalog = (catalog) => Object.fromEntries(catalog.map((item) => [String(item.id), item]));
