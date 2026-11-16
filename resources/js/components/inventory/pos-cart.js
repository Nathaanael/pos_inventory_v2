import { rupiah, toast } from './format';

/**
 * Layar POS (pengganti trait Livewire InteractsWithPosCart).
 * Katalog diambil dari server (JSON), keranjang dikelola di browser, lalu disimpan
 * lewat fetch. Server menghitung ulang harga & HPP dan memotong stok.
 *
 * config:
 *   itemsUrl, storeUrl   endpoint katalog & simpan
 *   date                 tanggal awal (Y-m-d)
 *   hasWholesale         fitur harga grosir aktif
 */
export default (config = {}) => ({
    search: '',
    category: null,
    catalog: [],
    loading: false,
    cart: [],
    priceType: 'retail',
    customer_name: '',
    destination: '',
    notes: '',
    date: config.date ?? new Date().toISOString().slice(0, 10),
    editingDate: false,
    saving: false,
    errors: {},
    rupiah,

    init() {
        this.fetchItems();
        this.$watch('search', () => this.fetchItemsDebounced());
        this.$watch('category', () => this.fetchItems());
    },

    _timer: null,
    fetchItemsDebounced() {
        clearTimeout(this._timer);
        this._timer = setTimeout(() => this.fetchItems(), 300);
    },

    async fetchItems() {
        this.loading = true;
        const params = new URLSearchParams();
        if (this.search.trim()) params.set('q', this.search.trim());
        if (this.category) params.set('category', this.category);
        try {
            const response = await fetch(`${config.itemsUrl}?${params}`, { headers: { Accept: 'application/json' } });
            this.catalog = response.ok ? await response.json() : [];
        } finally {
            this.loading = false;
        }
    },

    /** Enter di kotak cari: kalau kode/barcode cocok persis (mis. dari scanner), langsung masuk keranjang. */
    addFromSearch() {
        const term = this.search.trim().toLowerCase();
        if (!term) return;
        const exact = this.catalog.find((item) => [item.barcode, item.code].some((v) => v && v.toLowerCase() === term));
        const match = exact ?? (this.catalog.length === 1 ? this.catalog[0] : null);
        if (match) {
            this.add(match);
            this.search = '';
        }
    },

    price(item) {
        return this.priceType === 'grosir' ? Number(item.price_wholesale) : Number(item.price_retail);
    },

    stockClass(item) {
        if (item.stock <= 0) return 'no';
        return item.stock <= item.min_stock ? 'low' : 'ok';
    },

    add(item) {
        if (item.stock <= 0) {
            toast('error', 'Stok habis', `${item.name} tidak memiliki stok.`);
            return;
        }
        const line = this.cart.find((l) => l.id === item.id);
        if (line) {
            this.setQty(line, line.quantity + 1);
            return;
        }
        this.cart.push({ ...item, quantity: 1 });
    },

    setQty(line, qty) {
        qty = Math.floor(Number(qty) || 0);
        if (qty <= 0) {
            this.remove(line);
            return;
        }
        if (qty > line.stock) {
            toast('error', 'Stok tidak cukup', `Tersedia: ${line.stock}`);
            qty = line.stock;
        }
        line.quantity = qty;
    },

    remove(line) {
        this.cart = this.cart.filter((l) => l !== line);
    },

    clear() {
        if (confirm('Hapus semua item dari keranjang?')) this.cart = [];
    },

    lineTotal(line) {
        return this.price(line) * line.quantity;
    },

    /** HPP rata-rata per unit, FIFO per supplier (sama dengan Item::calculateFifoCogs). */
    unitCogs(line) {
        let remaining = line.quantity;
        let total = 0;
        for (const layer of line.cost_layers ?? []) {
            if (remaining <= 0) break;
            const take = Math.min(remaining, layer.stock);
            total += take * layer.price;
            remaining -= take;
        }
        if (remaining > 0) total += remaining * line.cost_fallback;
        return line.quantity > 0 ? total / line.quantity : 0;
    },

    get totalQty() {
        return this.cart.reduce((sum, l) => sum + l.quantity, 0);
    },

    get totalAmount() {
        return this.cart.reduce((sum, l) => sum + this.lineTotal(l), 0);
    },

    get totalCogs() {
        return this.cart.reduce((sum, l) => sum + this.unitCogs(l) * l.quantity, 0);
    },

    get margin() {
        return this.totalAmount - this.totalCogs;
    },

    get dateLabel() {
        const date = new Date(`${this.date}T00:00:00`);
        return isNaN(date) ? this.date : date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
    },

    async save() {
        if (!this.cart.length) {
            toast('error', 'Keranjang kosong', 'Tambahkan minimal 1 barang sebelum menyimpan.');
            return;
        }
        this.saving = true;
        this.errors = {};
        try {
            const response = await fetch(config.storeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    date: this.date,
                    customer_name: this.customer_name,
                    destination: this.destination,
                    price_type_applied: this.priceType,
                    notes: this.notes,
                    lines: this.cart.map((l) => ({ item_id: l.id, quantity: l.quantity })),
                }),
            });
            const body = await response.json().catch(() => ({}));
            if (response.ok && body.redirect) {
                window.location.href = body.redirect;
                return;
            }
            this.errors = body.errors ?? {};
            const first = Object.values(this.errors).flat()[0];
            toast('error', 'Gagal menyimpan', first ?? body.message ?? 'Terjadi kesalahan.');
        } catch (e) {
            toast('error', 'Gagal menyimpan', 'Tidak dapat terhubung ke server.');
        } finally {
            this.saving = false;
        }
    },
});
