import { indexCatalog, number, signed } from './format';

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
        return this.hasPhysical(row) ? Number(row.physical_stock) - Number(row.system_stock || 0) : null;
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
