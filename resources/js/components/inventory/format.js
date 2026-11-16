/** Format angka untuk form transaksi. */
export const rupiah = (value) => 'Rp ' + Math.round(Number(value) || 0).toLocaleString('id-ID');

export const number = (value) => Math.round(Number(value) || 0).toLocaleString('id-ID');

export const signed = (value) => {
    const n = Math.round(Number(value) || 0);
    return n > 0 ? `+${n}` : String(n);
};

/** Tampilkan toast (lihat <x-common.flash>). */
export const toast = (type, title, body = null) =>
    window.dispatchEvent(new CustomEvent('toast', { detail: { type, title, body } }));

/** Peta id → barang dari katalog ItemCatalog::forForms(). */
export const indexCatalog = (catalog) => Object.fromEntries(catalog.map((item) => [String(item.id), item]));
