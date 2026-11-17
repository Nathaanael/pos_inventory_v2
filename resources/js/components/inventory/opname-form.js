import { indexCatalog, number, qty, signed, signedQty } from './format';

/**
 * Form Stock Opname: stok sistem terisi saat barang dipilih,
 * selisih & status (cocok/kurang/lebih) dihitung saat stok fisik diisi.
 */
export default ({ catalog = [], rows = [] } = {}) => ({
    catalog,
    items: indexCatalog(catalog),
    rows: rows.map((row) => ({
        item_id: String(row.item_id ?? ''),
        system_stock: row.system_stock ?? '',
        physical_stock: row.physical_stock ?? '',
        notes: row.notes ?? '',
    })),
    number,
    signed,
    qty,
    signedQty,

    /** Nama satuan barang di baris (mis. "kg"), kosong bila belum memilih barang. */
    unit(row) {
        return this.items[row.item_id]?.unit ?? '';
    },

    unitSuffix(row) {
        const unit = this.unit(row);
        return unit ? ` ${unit}` : '';
    },

    get itemOptions() {
        return this.catalog.map((item) => ({ value: String(item.id), label: item.name, description: item.code }));
    },

    onItemChange(row) {
        row.system_stock = this.items[row.item_id]?.stock ?? '';
    },

    hasPhysical(row) {
        return row.physical_stock !== '' && row.physical_stock !== null;
    },

    difference(row) {
        if (!this.hasPhysical(row)) return null;
        // Dibulatkan 4 desimal (sama seperti server) agar 1,1 - 1,0 tidak jadi 0,10000000000000009.
        return Math.round((Number(row.physical_stock) - Number(row.system_stock || 0)) * 10000) / 10000;
    },

    status(row) {
        const diff = this.difference(row);
        if (diff === null) return null;
        return diff === 0 ? 'Cocok' : diff > 0 ? 'Lebih' : 'Kurang';
    },

    add() {
        this.rows.push({ item_id: '', system_stock: '', physical_stock: '', notes: '' });
    },

    remove(index) {
        this.rows.splice(index, 1);
    },
});
