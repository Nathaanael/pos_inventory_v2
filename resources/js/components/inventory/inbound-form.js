import { indexCatalog, number, rupiah } from './format';

/**
 * Form Barang Masuk (pengganti logika ->live() InboundForm Filament):
 *  - daftar barang dibatasi barang yang disuplai supplier terpilih,
 *  - harga beli otomatis dari harga supplier (read-only),
 *  - subtotal & total dihitung langsung.
 */
export default ({ catalog = [], supplierId = '', rows = [] } = {}) => ({
    catalog,
    items: indexCatalog(catalog),
    supplierId: String(supplierId ?? ''),
    rows: rows.map((row) => ({ item_id: String(row.item_id ?? ''), quantity: row.quantity ?? '' })),
    rupiah,
    number,

    get itemOptions() {
        const sid = this.supplierId;
        return this.catalog
            .filter((item) => !sid || item.suppliers[sid])
            .map((item) => ({ value: String(item.id), label: item.name, description: [item.code, item.unit].filter(Boolean).join(' · ') }));
    },

    /** Nama satuan barang di baris (mis. "kg"), kosong bila belum memilih barang. */
    unit(row) {
        return this.items[row.item_id]?.unit ?? '';
    },

    price(row) {
        const item = this.items[row.item_id];
        if (!item) return 0;
        return item.suppliers[this.supplierId]?.price ?? item.harga_beli;
    },

    subtotal(row) {
        return (Number(row.quantity) || 0) * this.price(row);
    },

    get total() {
        return this.rows.reduce((sum, row) => sum + this.subtotal(row), 0);
    },

    /** Ganti supplier: barang yang tidak disuplai supplier baru dikosongkan. */
    onSupplierChange() {
        this.rows.forEach((row) => {
            const item = this.items[row.item_id];
            if (item && this.supplierId && !item.suppliers[this.supplierId]) row.item_id = '';
        });
    },

    add() {
        this.rows.push({ item_id: '', quantity: '' });
    },

    remove(index) {
        this.rows.splice(index, 1);
    },
});
