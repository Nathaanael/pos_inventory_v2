import { indexCatalog, number, rupiah } from './format';

/**
 * Form Transaksi Retur (pengganti logika ->live() ReturnTransactionForm Filament):
 *  - Retur ke supplier: barang dibatasi milik supplier, harga = harga beli supplier,
 *    stok sesudah = stok - qty, dan qty tidak boleh melebihi stok.
 *  - Retur dari customer: semua barang, harga = harga jual retail, stok sesudah = stok + qty.
 */
export default ({ catalog = [], type = 'supplier', supplierId = '', rows = [] } = {}) => ({
    catalog,
    items: indexCatalog(catalog),
    type,
    supplierId: String(supplierId ?? ''),
    rows: rows.map((row) => ({
        item_id: String(row.item_id ?? ''),
        quantity: row.quantity ?? '',
        price: row.price ?? '',
        condition: row.condition ?? 'rusak',
    })),
    rupiah,
    number,

    get isSupplier() {
        return this.type === 'supplier';
    },

    get itemOptions() {
        const sid = this.supplierId;
        const list = this.isSupplier ? (sid ? this.catalog.filter((item) => item.suppliers[sid]) : []) : this.catalog;
        return list.map((item) => ({ value: String(item.id), label: item.name, description: item.code }));
    },

    get itemPlaceholder() {
        return this.isSupplier && !this.supplierId ? 'Pilih supplier dulu' : 'Cari barang...';
    },

    stock(row) {
        return this.items[row.item_id]?.stock ?? 0;
    },

    supplierStock(row) {
        return this.items[row.item_id]?.suppliers[this.supplierId]?.stock ?? 0;
    },

    stockLabel(row) {
        return row.item_id ? `${this.stock(row)} (Supplier: ${this.supplierStock(row)})` : '';
    },

    stockAfterLabel(row) {
        if (!row.item_id) return '';
        const qty = Number(row.quantity) || 0;
        const sign = this.isSupplier ? -1 : 1;
        return `${this.stock(row) + sign * qty} (Supplier: ${this.supplierStock(row) + sign * qty})`;
    },

    qtyError(row) {
        if (!this.isSupplier || !row.item_id) return '';
        const qty = Number(row.quantity) || 0;
        if (qty > this.stock(row)) return `Stok tidak cukup. Tersedia: ${this.stock(row)}`;
        if (qty > this.supplierStock(row)) return `Stok dari supplier ini tidak cukup. Tersedia: ${this.supplierStock(row)}`;
        return '';
    },

    subtotal(row) {
        return (Number(row.quantity) || 0) * (Number(row.price) || 0);
    },

    get total() {
        return this.rows.reduce((sum, row) => sum + this.subtotal(row), 0);
    },

    onItemChange(row) {
        const item = this.items[row.item_id];
        if (!item) {
            row.price = '';
            return;
        }
        row.price = this.isSupplier
            ? (item.suppliers[this.supplierId]?.price || item.harga_beli)
            : item.harga_jual_retail;
    },

    /** Ganti tipe/supplier: barang yang tidak lagi valid dikosongkan, harga disesuaikan. */
    onHeaderChange() {
        this.rows.forEach((row) => {
            const item = this.items[row.item_id];
            if (item && this.isSupplier && !item.suppliers[this.supplierId]) {
                row.item_id = '';
                row.price = '';
            } else if (item) {
                this.onItemChange(row);
            }
        });
    },

    add() {
        this.rows.push({ item_id: '', quantity: '', price: '', condition: 'rusak' });
    },

    remove(index) {
        this.rows.splice(index, 1);
    },
});
