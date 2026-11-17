import { toast } from '../inventory/format';

/**
 * Modal "Bayar Tagihan" Open Bill (dipakai halaman Meja & POS, lihat <x-main.kasir.pay-bill-modal>).
 *
 * Buka:    window.dispatchEvent(new CustomEvent('pay-bill', { detail: { id, code, remaining, total, customer, table } }))
 * Selesai: memancarkan 'bill-paid' (window) dengan detail = respons server
 *          { message, change, tables, open_bills, recent } agar halaman memperbarui datanya.
 */
export default ({ payUrlTemplate, methods = {} } = {}) => ({
    bill: null,
    methods,
    method: 'tunai',
    amount: '',          // angka mentah (string), diikat ke <x-common.form.money>
    paying: false,
    error: '',

    open(bill) {
        this.bill = bill;
        this.method = 'tunai';
        this.amount = String(Math.ceil(bill.remaining || bill.total || 0));
        this.error = '';
    },

    close() {
        if (!this.paying) this.bill = null;
    },

    get remaining() {
        return Math.ceil(this.bill?.remaining || 0);
    },

    get paidNumber() {
        return Number(this.amount) || 0;
    },

    /** Kembalian (positif) atau kekurangan (negatif) untuk tunai. */
    get change() {
        return this.paidNumber - this.remaining;
    },

    get canPay() {
        if (!this.bill || this.paying) return false;
        return this.method !== 'tunai' || this.paidNumber >= this.remaining;
    },

    /** Uang pas + pecahan bulat terdekat di atas tagihan (mis. 50rb, 100rb). */
    get quickAmounts() {
        const total = this.remaining;
        const options = new Set([total]);
        [10000, 20000, 50000, 100000].forEach((step) => {
            const rounded = Math.ceil(total / step) * step;
            if (rounded > total) options.add(rounded);
        });
        return [...options].sort((a, b) => a - b).slice(0, 4);
    },

    money(v) {
        return 'Rp' + new Intl.NumberFormat('id-ID').format(Math.round(v || 0));
    },

    async pay() {
        if (!this.canPay) return;
        this.paying = true;
        this.error = '';

        try {
            const response = await fetch(payUrlTemplate.replace('__ID__', this.bill.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    payment_method: this.method,
                    paid_amount: this.method === 'tunai' ? this.paidNumber : null,
                }),
            });
            const data = await response.json().catch(() => null);

            if (data?.ok) {
                toast('success', data.message, data.change > 0 ? `Kembalian ${this.money(data.change)}.` : null);
                window.dispatchEvent(new CustomEvent('bill-paid', { detail: data }));
                this.bill = null;
            } else {
                this.error = data?.message ?? 'Gagal memproses pembayaran.';
            }
        } catch (e) {
            this.error = 'Koneksi gagal. Coba lagi.';
        } finally {
            this.paying = false;
        }
    },
});
