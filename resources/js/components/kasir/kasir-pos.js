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
    openBills: config.openBills ?? [],    // Open Bill belum lunas (semua kasir) → dibayar dari POS
    billSearch: '',
    billsOpen: false,                     // modal daftar Open Bill
    fnb: config.fnb ?? { tables: false, takeAway: false },
    tables: config.tables ?? [],          // [{ id, number, name, occupied }]
    taxRules: config.taxRules ?? [],      // master Pajak & Biaya Layanan (modul FNB)
    usesModifiers: config.usesModifiers ?? false,
    activeOrder: config.activeOrder ?? null,
    shiftRequired: config.shiftRequired ?? false,   // FNB tanpa shift aktif: simpan transaksi diblokir
    search: '',
    activeCategory: null,
    cart: [],                             // [{ uid, id, qty, modifier_ids, modifiers, modifiers_total, notes }]
    priceType: 'retail',
    payment: 'tunai',
    paid: '',
    customer: '',
    // Member: kolom "Nama pelanggan" sekaligus pencarian member (nama / nomor HP).
    memberId: null,
    memberPhone: '',
    memberResults: [],
    memberOpen: false,
    memberLoading: false,
    memberIndex: -1,
    memberTimer: null,
    quickMember: { open: false, name: '', phone: '', birth_date: '', saving: false, errors: {} },
    notes: '',
    showNotes: false,
    discountType: 'percent',              // percent | fixed
    discountValue: '',
    showDiscount: false,
    saving: false,
    confirmOpen: false,                   // modal konfirmasi kosongkan keranjang
    saleId: null,                         // id unik per penjualan, mencegah transaksi tersimpan dobel
    orderType: 'dine_in',
    tableId: null,
    tablePickerOpen: false,
    tableSearch: '',
    tableFilter: 'all',                   // all | free | busy
    modModal: { open: false, item: null, editingUid: null, selected: [], notes: '', qty: 1 },

    init() {
        this.tableId = this.tables.some((t) => t.id === config.preselectTable) ? config.preselectTable : null;
        if (this.activeOrder) {
            this.orderType = this.activeOrder.order_type || 'dine_in';
            this.customer = this.activeOrder.customer_name || '';
        }
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

    // ---------- Member (kolom nama pelanggan) ----------
    /** Mengetik ulang nama melepas member terpilih, lalu cari member (debounce). */
    onCustomerInput() {
        if (this.memberId) this.clearMemberSelection();
        clearTimeout(this.memberTimer);
        const q = this.customer.trim();
        if (q.length < 2) {
            this.memberResults = [];
            this.memberOpen = false;
            return;
        }
        this.memberLoading = true;
        this.memberOpen = true;
        this.memberTimer = setTimeout(() => this.searchMembers(q), 250);
    },

    async searchMembers(q) {
        try {
            const response = await fetch(`${config.memberSearchUrl}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
            const results = response.ok ? await response.json() : [];
            if (q !== this.customer.trim()) return; // hasil untuk ketikan lama
            this.memberResults = results;
            this.memberIndex = results.length ? 0 : -1;
        } catch (e) {
            this.memberResults = [];
        } finally {
            this.memberLoading = false;
        }
    },

    selectMember(member) {
        this.customer = member.name;
        this.memberId = member.id;
        this.memberPhone = member.phone_label;
        this.memberOpen = false;
        this.memberResults = [];
    },

    clearMemberSelection() {
        this.memberId = null;
        this.memberPhone = '';
        this.memberResults = [];
        this.memberOpen = false;
    },

    /** Tombol "Tambah member" tampil bila nama yang diketik belum ada di hasil pencarian. */
    get canAddMember() {
        const q = this.customer.trim().toLowerCase();
        return q.length >= 2 && !this.memberLoading && !this.memberResults.some((m) => m.name.toLowerCase() === q);
    },

    memberKey(e) {
        if (!this.memberOpen) return;
        const max = this.memberResults.length - 1;
        if (e.key === 'ArrowDown') { e.preventDefault(); this.memberIndex = Math.min(max, this.memberIndex + 1); }
        if (e.key === 'ArrowUp') { e.preventDefault(); this.memberIndex = Math.max(0, this.memberIndex - 1); }
        if (e.key === 'Escape') { e.preventDefault(); this.memberOpen = false; }
        if (e.key === 'Enter' && this.memberResults[this.memberIndex]) {
            e.preventDefault();
            this.selectMember(this.memberResults[this.memberIndex]);
        }
    },

    /** Buka form tambah member cepat; ketikan berupa angka diisi ke nomor HP, selain itu ke nama. */
    openQuickMember() {
        const typed = this.customer.trim();
        const isPhone = /^\+?[\d\s-]{6,}$/.test(typed);
        this.quickMember = { open: true, name: isPhone ? '' : typed, phone: isPhone ? typed : '', birth_date: '', saving: false, errors: {} };
        this.memberOpen = false;
    },

    async saveQuickMember() {
        if (this.quickMember.saving) return;
        this.quickMember.saving = true;
        this.quickMember.errors = {};
        try {
            const response = await fetch(config.memberStoreUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    name: this.quickMember.name,
                    phone: this.quickMember.phone,
                    birth_date: this.quickMember.birth_date,
                }),
            });
            const data = await response.json().catch(() => null);
            if (response.status === 422) {
                this.quickMember.errors = Object.fromEntries(Object.entries(data?.errors ?? {}).map(([k, v]) => [k, v[0]]));
                return;
            }
            if (!response.ok || !data?.member) {
                toast('error', data?.message ?? 'Gagal menambah member.');
                return;
            }
            this.selectMember(data.member);
            this.quickMember.open = false;
            toast('success', data.message, data.member.name + ' dipilih sebagai pelanggan.');
        } catch (e) {
            toast('error', 'Koneksi ke server gagal. Coba lagi.');
        } finally {
            this.quickMember.saving = false;
        }
    },

    // ---------- Open Bill belum lunas ----------
    get filteredBills() {
        const q = this.billSearch.trim().toLowerCase();
        if (!q) return this.openBills;
        return this.openBills.filter((b) =>
            [b.customer, b.table, b.order, b.code].some((v) => (v ?? '').toLowerCase().includes(q)));
    },

    get openBillsTotal() {
        return this.openBills.reduce((sum, b) => sum + (b.remaining || 0), 0);
    },

    openBillsModal() {
        this.billSearch = '';
        this.billsOpen = true;
    },

    /** Tutup daftar lalu buka modal Bayar Tagihan bersama (komponen main.kasir.pay-bill-modal). */
    payBill(bill) {
        this.billsOpen = false;
        this.$dispatch('pay-bill', { ...bill, table: bill.table ?? bill.order });
    },

    /** Setelah tagihan lunas (event 'bill-paid'): perbarui daftar & status meja. */
    onBillPaid(data) {
        if (data.open_bills) this.openBills = data.open_bills;
        if (data.recent) this.recent = data.recent;
        if (data.tables) {
            this.tables = data.tables.map((t) => ({ id: t.id, number: t.number, name: t.name, occupied: t.occupied }));
        }
    },


    // ---------- cart ----------
    lineUid(id, modIds = [], notes = '') {
        const mods = [...modIds].map(Number).sort((a, b) => a - b).join(',');
        return `${id}|${mods}|${(notes || '').trim()}`;
    },

    qtyOf(id) {
        return this.cart.filter((l) => l.id === id).reduce((s, l) => s + l.qty, 0);
    },

    add(item, amount = 1) {
        if (item.stock <= 0) return toast('warning', `${item.name} stoknya habis`);
        if (this.usesModifiers && (item.modifier_groups?.length ?? 0) > 0) {
            return this.openModifierModal(item);
        }
        this.addConfiguredLine(item, amount, [], '');
    },

    addConfiguredLine(item, amount = 1, modIds = [], notes = '', editingUid = null) {
        const cleanNotes = (notes || '').trim();
        const sortedIds = [...modIds].map(Number).filter(Boolean).sort((a, b) => a - b);

        // Hitung total qty item ini di keranjang (kecuali baris yang sedang diedit).
        const otherQty = this.cart
            .filter((l) => l.id === item.id && l.uid !== editingUid)
            .reduce((s, l) => s + l.qty, 0);

        const uid = this.lineUid(item.id, sortedIds, cleanNotes);
        const existingSame = this.cart.find((l) => l.uid === uid && l.uid !== editingUid);
        const targetQty = (existingSame?.qty ?? 0) + amount;

        if (otherQty + targetQty > item.stock) {
            return toast('warning', `Stok ${item.name} tersisa ${item.stock}`);
        }

        const allMods = (item.modifier_groups ?? []).flatMap((g) => g.modifiers ?? []);
        const pickedMods = sortedIds.map((mid) => allMods.find((m) => m.id === mid)).filter(Boolean);
        const modTotal = pickedMods.reduce((s, m) => s + Number(m.price_delta || 0), 0);

        if (editingUid) {
            this.cart = this.cart.filter((l) => l.uid !== editingUid);
        }

        if (existingSame) {
            existingSame.qty = targetQty;
        } else {
            this.cart.push({
                uid,
                id: item.id,
                qty: amount,
                modifier_ids: sortedIds,
                modifiers: pickedMods,
                modifiers_total: modTotal,
                notes: cleanNotes,
            });
        }
    },

    openModifierModal(item, existingLine = null) {
        this.modModal = {
            open: true,
            item,
            editingUid: existingLine?.uid ?? null,
            selected: existingLine ? [...(existingLine.modifier_ids ?? [])] : [],
            notes: existingLine?.notes ?? '',
            qty: existingLine?.qty ?? 1,
        };
    },

    isModSelected(modId) {
        return this.modModal.selected.includes(modId);
    },

    toggleMod(group, mod) {
        const idx = this.modModal.selected.indexOf(mod.id);
        if (idx >= 0) {
            this.modModal.selected.splice(idx, 1);
            return;
        }
        const groupModIds = (group.modifiers ?? []).map((m) => m.id);
        const selectedInGroup = this.modModal.selected.filter((id) => groupModIds.includes(id));

        if (group.max_select === 1) {
            this.modModal.selected = this.modModal.selected.filter((id) => !groupModIds.includes(id));
            this.modModal.selected.push(mod.id);
        } else if (selectedInGroup.length >= group.max_select) {
            toast('warning', `Maksimal ${group.max_select} pilihan untuk ${group.name}`);
        } else {
            this.modModal.selected.push(mod.id);
        }
    },

    confirmModifierModal() {
        const item = this.modModal.item;
        if (!item) return;

        for (const group of item.modifier_groups ?? []) {
            const groupModIds = (group.modifiers ?? []).map((m) => m.id);
            const picked = this.modModal.selected.filter((id) => groupModIds.includes(id)).length;
            const min = group.is_required ? Math.max(1, group.min_select) : group.min_select;
            if (picked < min) {
                return toast('warning', `Pilih minimal ${min} opsi pada ${group.name}`);
            }
        }

        this.addConfiguredLine(item, this.modModal.qty, this.modModal.selected, this.modModal.notes, this.modModal.editingUid);
        this.modModal.open = false;
        this.$nextTick(() => this.$refs.search?.focus());
    },

    setQty(uid, value) {
        const line = this.cart.find((l) => l.uid === uid);
        if (!line) return;
        const item = this.itemsById[line.id];
        let qty = parseInt(value, 10);
        if (!item) return;
        if (isNaN(qty) || qty <= 0) return this.remove(uid);

        const otherQty = this.cart.filter((l) => l.id === line.id && l.uid !== uid).reduce((s, l) => s + l.qty, 0);
        if (otherQty + qty > item.stock) {
            qty = Math.max(0, item.stock - otherQty);
            toast('warning', `Stok ${item.name} tersisa ${item.stock}`);
        }
        if (qty <= 0) return this.remove(uid);
        line.qty = qty;
    },

    dec(uid) {
        const line = this.cart.find((l) => l.uid === uid);
        if (line) this.setQty(uid, line.qty - 1);
    },

    remove(uid) {
        this.cart = this.cart.filter((l) => l.uid !== uid);
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
        this.clearMemberSelection();
        this.notes = '';
        this.showNotes = false;
        this.discountType = 'percent';
        this.discountValue = '';
        this.showDiscount = false;
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

    lineUnitPrice(l) {
        return this.price(l.item) + Number(l.modifiers_total || 0);
    },

    /** Total harga barang (sebelum pajak/biaya layanan exclusive). */
    get subtotal() {
        return this.lines.reduce((s, l) => s + this.lineUnitPrice(l) * l.qty, 0);
    },

    get discountAmount() {
        const val = parseFloat(this.discountValue) || 0;
        if (val <= 0 || this.subtotal <= 0) return 0;
        if (this.discountType === 'percent') return Math.round(this.subtotal * Math.min(100, val) / 100);
        return Math.min(this.subtotal, Math.round(val));
    },

    /** Rincian pajak & biaya layanan (rumus sama dengan TaxCalculator di server). */
    get taxes() {
        return calculateTaxes(
            this.lines.map((l) => ({ subtotal: this.lineUnitPrice(l) * l.qty, tax_rate: l.item.tax_rate, tax_method: l.item.tax_method })),
            this.taxRules,
            this.hasFnb ? this.orderType : null,
            this.discountAmount,
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
        if (this.shiftRequired || this.saving || !this.cart.length) return false;
        return this.payment !== 'tunai' || this.paidNumber >= this.total;
    },

    get canSaveOpenBill() {
        return !this.shiftRequired && !this.saving && this.cart.length > 0 && this.usesModifiers;
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
        if (this.billsOpen || this.quickMember.open) return; // Esc ditangani modal
        if (this.modModal.open) {
            if (e.key === 'Escape') {
                e.preventDefault();
                this.modModal.open = false;
            }
            return;
        }
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
    async submit(asOpenBill = false) {
        if (this.activeOrder) {
            return this.submitAddItems();
        }
        if (asOpenBill) {
            if (!this.canSaveOpenBill) return;
        } else if (!this.canSubmit) {
            return;
        }
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
                    lines: this.cart.map((l) => ({
                        id: l.id,
                        qty: l.qty,
                        modifier_ids: l.modifier_ids ?? [],
                        notes: l.notes || null,
                    })),
                    price_type: this.priceType,
                    payment_method: asOpenBill ? null : this.payment,
                    paid_amount: !asOpenBill && this.payment === 'tunai' ? this.paidNumber : null,
                    customer_name: this.customer || null,
                    member_id: this.memberId,
                    notes: this.notes || null,
                    sale_id: this.saleId,
                    order_type: this.hasFnb ? this.orderType : null,
                    table_id: this.needsTable ? Number(this.tableId) : null,
                    save_as_open_bill: asOpenBill,
                    discount_type: this.discountAmount > 0 ? this.discountType : null,
                    discount_value: this.discountAmount > 0 ? (parseFloat(this.discountValue) || 0) : 0,
                }),
            });
            const result = await response.json().catch(() => null);

            if (result?.ok) {
                const change = !asOpenBill && this.payment === 'tunai' ? this.change : 0;
                toast('success', result.message, change > 0 ? `Kembalian ${this.money(change)}.` : null);
                if (result.catalog) this.catalog = result.catalog;
                if (result.recent) this.recent = result.recent;
                if (result.tables) this.tables = result.tables;
                if (result.open_bills) this.openBills = result.open_bills;
                this.resetSale();
                this.$nextTick(() => this.$refs.search?.focus());
            } else {
                if (result?.shift_required) this.shiftRequired = true;
                toast('error', result?.message ?? 'Gagal menyimpan transaksi.');
            }
        } catch (e) {
            toast('error', 'Koneksi ke server gagal. Coba lagi.');
        } finally {
            this.saving = false;
        }
    },

    async submitAddItems() {
        if (this.saving || !this.cart.length || !this.activeOrder) return;
        this.saving = true;
        try {
            const url = config.addItemsUrlTemplate.replace('__ID__', this.activeOrder.id);
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    lines: this.cart.map((l) => ({
                        id: l.id,
                        qty: l.qty,
                        modifier_ids: l.modifier_ids ?? [],
                        notes: l.notes || null,
                    })),
                    price_type: this.priceType,
                }),
            });
            const result = await response.json().catch(() => null);
            if (result?.ok) {
                toast('success', result.message);
                window.location.href = config.mejaUrl;
            } else {
                toast('error', result?.message ?? 'Gagal menambah pesanan.');
            }
        } catch (e) {
            toast('error', 'Koneksi ke server gagal. Coba lagi.');
        } finally {
            this.saving = false;
        }
    },
});
