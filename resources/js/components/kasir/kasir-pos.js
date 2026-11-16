import { toast } from '../inventory/format';
import calculateTaxes from './tax-calculator';

/**
 * Layar kasir (dipindah dari script inline filament/kasir/pages/pos.blade.php).
 * Keranjang di browser; checkout dikirim sekali ke server lewat fetch.
 */
const TONES = [
    'bg-success-50 dark:bg-success-500/10',
    'bg-theme-purple-500/10',
    'bg-blue-light-50 dark:bg-blue-light-500/10',
    'bg-brand-50 dark:bg-brand-500/10',
    'bg-theme-pink-500/10',
    'bg-orange-50 dark:bg-orange-500/10',
    'bg-error-50 dark:bg-error-500/10',
    'bg-warning-50 dark:bg-warning-500/10',
    'bg-gray-100 dark:bg-white/5',
];

const fmt = new Intl.NumberFormat('id-ID');

export default (config = {}) => ({
    catalog: config.catalog ?? [],
    categories: config.categories ?? [],
    recent: config.recent ?? [],          // transaksi terakhir hari ini
    fnb: config.fnb ?? { tables: false, takeAway: false },
    tables: config.tables ?? [],          // [{ id, number, name, occupied }]
    taxRules: config.taxRules ?? [],      // master Pajak & Biaya Layanan (modul FNB)
    search: '',
    activeCategory: null,
    cart: [],                             // [{ id, qty }]
    priceType: 'retail',
    payment: 'tunai',
    paid: '',
    customer: '',
    notes: '',
    showNotes: false,
    saving: false,
    confirmOpen: false,                   // modal konfirmasi kosongkan keranjang
    saleId: null,                         // id unik per penjualan, mencegah transaksi tersimpan dobel
    orderType: 'dine_in',
    tableId: null,
    tablePickerOpen: false,
    tableSearch: '',
    tableFilter: 'all',                   // all | free | busy

    init() {
        this.tableId = this.tables.some((t) => t.id === config.preselectTable) ? config.preselectTable : null;
        this.saleId = this.newSaleId();
        this.$nextTick(() => this.$refs.search?.focus());
    },

    newSaleId() {
        return window.crypto?.randomUUID?.() ?? Date.now().toString(36) + Math.random().toString(36).slice(2);
    },

    // ---------- data helpers ----------
    get itemsById() {
        const map = {};
        for (const item of this.catalog) map[item.id] = item;
        return map;
    },

    toneFor(categoryId) {
        const index = this.categories.findIndex((c) => c.id === categoryId);
        return TONES[(index < 0 ? 0 : index) % TONES.length];
    },

    categoryName(categoryId) {
        return this.categories.find((c) => c.id === categoryId)?.name ?? '';
    },

    countIn(categoryId) {
        return this.catalog.filter((i) => i.category_id === categoryId).length;
    },

    get filtered() {
        const q = this.search.trim().toLowerCase();
        return this.catalog
            .filter((i) => this.activeCategory === null || i.category_id === this.activeCategory)
            .filter((i) => !q
                || i.name.toLowerCase().includes(q)
                || (i.code ?? '').toLowerCase().includes(q)
                || (i.barcode ?? '').toLowerCase().includes(q))
            .slice(0, 150);
    },

    price(item) {
        return this.priceType === 'grosir' && item.grosir > 0 ? item.grosir : item.retail;
    },

    money(value) {
        return 'Rp' + fmt.format(Math.round(value || 0));
    },

    // ---------- cart ----------
    qtyOf(id) {
        return this.cart.find((l) => l.id === id)?.qty ?? 0;
    },

    add(item, amount = 1) {
        if (item.stock <= 0) return toast('warning', `${item.name} stoknya habis`);
        const line = this.cart.find((l) => l.id === item.id);
        const next = (line?.qty ?? 0) + amount;
        if (next > item.stock) return toast('warning', `Stok ${item.name} tersisa ${item.stock}`);
        if (line) line.qty = next;
        else this.cart.push({ id: item.id, qty: amount });
    },

    setQty(id, value) {
        const item = this.itemsById[id];
        let qty = parseInt(value, 10);
        if (!item) return;
        if (isNaN(qty) || qty <= 0) return this.remove(id);
        if (qty > item.stock) {
            qty = item.stock;
            toast('warning', `Stok ${item.name} tersisa ${item.stock}`);
        }
        const line = this.cart.find((l) => l.id === id);
        if (line) line.qty = qty;
    },

    dec(id) {
        this.setQty(id, this.qtyOf(id) - 1);
    },

    remove(id) {
        this.cart = this.cart.filter((l) => l.id !== id);
    },

    clearCart() {
        if (!this.cart.length) return this.resetSale();
        this.confirmOpen = true;
    },

    confirmClear(yes) {
        this.confirmOpen = false;
        if (yes) this.resetSale();
        this.$nextTick(() => this.$refs.search?.focus());
    },

    resetSale() {
        this.cart = [];
        this.paid = '';
        this.customer = '';
        this.notes = '';
        this.showNotes = false;
        this.orderType = 'dine_in';
        this.tableId = null;
        this.saleId = this.newSaleId();
    },

    get lines() {
        return this.cart.map((l) => ({ ...l, item: this.itemsById[l.id] })).filter((l) => l.item);
    },

    get totalQty() {
        return this.cart.reduce((s, l) => s + l.qty, 0);
    },

    /** Total harga barang (sebelum pajak/biaya layanan exclusive). */
    get subtotal() {
        return this.lines.reduce((s, l) => s + this.price(l.item) * l.qty, 0);
    },

    /** Rincian pajak & biaya layanan (rumus sama dengan TaxCalculator di server). */
    get taxes() {
        return calculateTaxes(
            this.lines.map((l) => ({ subtotal: this.price(l.item) * l.qty, tax_rate: l.item.tax_rate, tax_method: l.item.tax_method })),
            this.taxRules,
            this.hasFnb ? this.orderType : null,
        );
    },

    /** Yang dibayar pelanggan. */
    get total() {
        return this.taxes.grand_total;
    },

    get paidNumber() {
        return parseInt(String(this.paid).replace(/\D/g, ''), 10) || 0;
    },

    get change() {
        return this.paidNumber - this.total;
    },

    get quickAmounts() {
        const t = this.total;
        if (t <= 0) return [];
        const set = new Set([t]);
        for (const step of [5000, 10000, 50000, 100000]) set.add(Math.ceil(t / step) * step);
        return [...set].sort((a, b) => a - b).slice(0, 4);
    },

    // ---------- FNB: tipe pesanan & meja ----------
    get hasFnb() {
        return this.fnb.tables || this.fnb.takeAway;
    },

    get needsTable() {
        return this.fnb.tables && this.orderType === 'dine_in';
    },

    get selectedTable() {
        return this.tables.find((t) => t.id === this.tableId) ?? null;
    },

    get freeTableCount() {
        return this.tables.filter((t) => !t.occupied).length;
    },

    get pickerTables() {
        const q = this.tableSearch.trim().toLowerCase();
        return this.tables
            .filter((t) => this.tableFilter === 'all' || (this.tableFilter === 'free' ? !t.occupied : t.occupied))
            .filter((t) => !q || t.number.toLowerCase().includes(q) || (t.name ?? '').toLowerCase().includes(q));
    },

    setOrderType(type) {
        this.orderType = type;
        if (type === 'take_away') this.tableId = null;
    },

    openTablePicker() {
        this.tableSearch = '';
        this.tableFilter = 'all';
        this.tablePickerOpen = true;
        this.$nextTick(() => this.$refs.tableSearch?.focus());
    },

    pickTable(t) {
        this.tableId = t.id;
        this.tablePickerOpen = false;
        this.$nextTick(() => this.$refs.search?.focus());
    },

    // Enter di kolom cari meja: pilih bila hanya satu hasil / nomor persis sama.
    pickTableFromSearch() {
        const q = this.tableSearch.trim().toLowerCase();
        const exact = this.tables.find((t) => t.number.toLowerCase() === q);
        const target = exact ?? (this.pickerTables.length === 1 ? this.pickerTables[0] : null);
        if (target) this.pickTable(target);
    },

    get canSubmit() {
        if (this.saving || !this.cart.length) return false;
        return this.payment !== 'tunai' || this.paidNumber >= this.total;
    },

    // ---------- search / scanner ----------
    onEnter() {
        const q = this.search.trim().toLowerCase();
        if (!q) return;
        const exact = this.catalog.find((i) => (i.barcode ?? '').toLowerCase() === q || (i.code ?? '').toLowerCase() === q);
        const target = exact ?? (this.filtered.length === 1 ? this.filtered[0] : null);
        if (target) {
            this.add(target);
            this.search = '';
        }
    },

    shortcut(e) {
        if (this.tablePickerOpen) {
            if (e.key === 'Escape') {
                e.preventDefault();
                this.tablePickerOpen = false;
            }
            return;
        }
        if (this.confirmOpen) {
            if (e.key === 'Escape') {
                e.preventDefault();
                this.confirmClear(false);
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                this.confirmClear(true);
            }
            return;
        }
        if (e.key === '/' && !['INPUT', 'TEXTAREA'].includes(document.activeElement?.tagName)) {
            e.preventDefault();
            this.$refs.search?.focus();
        }
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            this.submit();
        }
    },

    // ---------- checkout ----------
    async submit() {
        if (!this.canSubmit) return;
        // Makan di tempat tanpa meja: langsung buka pemilih meja.
        if (this.needsTable && !this.selectedTable) return this.openTablePicker();

        this.saving = true;
        try {
            const response = await fetch(config.checkoutUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    lines: this.cart.map((l) => ({ id: l.id, qty: l.qty })),
                    price_type: this.priceType,
                    payment_method: this.payment,
                    paid_amount: this.payment === 'tunai' ? this.paidNumber : null,
                    customer_name: this.customer || null,
                    notes: this.notes || null,
                    sale_id: this.saleId,
                    order_type: this.hasFnb ? this.orderType : null,
                    table_id: this.needsTable ? Number(this.tableId) : null,
                }),
            });
            const result = await response.json().catch(() => null);

            if (result?.ok) {
                const change = this.payment === 'tunai' ? this.change : 0;
                toast('success', result.message, change > 0 ? `Kembalian ${this.money(change)}.` : null);
                if (result.catalog) this.catalog = result.catalog;
                if (result.recent) this.recent = result.recent;
                if (result.tables) this.tables = result.tables;
                this.resetSale();
                this.$nextTick(() => this.$refs.search?.focus());
            } else {
                toast('error', result?.message ?? 'Gagal menyimpan transaksi.');
            }
        } catch (e) {
            toast('error', 'Koneksi ke server gagal. Coba lagi.');
        } finally {
            this.saving = false;
        }
    },
});
