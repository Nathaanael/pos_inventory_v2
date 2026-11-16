/**
 * Halaman daftar transaksi: centang banyak baris, dialog konfirmasi, dan panel "Lihat"
 * yang isinya dimuat dari server (pengganti ViewAction ->slideOver() Filament).
 */
export default () => ({
    selected: [],
    panelOpen: false,
    panelTitle: '',
    panelHtml: '',
    panelLoading: false,

    isSelected(id) {
        return this.selected.includes(id);
    },

    toggle(id) {
        this.selected = this.isSelected(id) ? this.selected.filter((x) => x !== id) : [...this.selected, id];
    },

    toggleAll(ids) {
        this.selected = ids.length && ids.every((id) => this.isSelected(id)) ? [] : [...ids];
    },

    confirm(options) {
        window.dispatchEvent(new CustomEvent('confirm', { detail: options }));
    },

    /** Konfirmasi aksi massal dengan id baris yang dicentang. */
    confirmBulk(options) {
        this.confirm({ ...options, fields: this.selected.map((id) => ['ids[]', id]) });
    },

    async view(url, title) {
        this.panelTitle = title;
        this.panelHtml = '';
        this.panelLoading = true;
        this.panelOpen = true;
        try {
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            this.panelHtml = response.ok ? await response.text() : '<p class="text-sm text-error-500">Gagal memuat data.</p>';
        } finally {
            this.panelLoading = false;
        }
    },
});
