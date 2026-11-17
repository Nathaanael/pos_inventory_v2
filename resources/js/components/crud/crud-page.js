/**
 * Perilaku halaman daftar + modal tambah/ubah (pengganti ManageRecords Filament).
 *
 * Pemakaian di Blade:
 *   x-data="crudPage({ blank: {...}, routes: { store: '...', update: '.../__ID__' }, reopen: {...} | null })"
 *
 * - create()          buka modal tambah dengan form kosong
 * - edit(row)         buka modal ubah dengan data baris
 * - confirm(options)  buka dialog konfirmasi global (lihat <x-common.confirm-dialog>)
 * - selected / toggleAll()  centang banyak baris untuk aksi massal
 * - reopen            dikirim server saat validasi gagal atau "Simpan & buat lagi", agar modal terbuka kembali
 */
export default (config = {}) => ({
    blank: config.blank ?? {},
    routes: config.routes ?? {},
    form: {},
    mode: 'create',
    editId: null,
    modalOpen: false,
    selected: [],

    init() {
        this.form = this.fill({});

        if (config.reopen) {
            this.mode = config.reopen.mode === 'edit' ? 'edit' : 'create';
            this.editId = config.reopen.id ?? null;
            this.form = this.fill(config.reopen.form ?? {});
            this.modalOpen = true;
        }
    },

    /** Gabungkan data ke form kosong; nilai boolean dari old() ("1"/"0") dikembalikan ke true/false. */
    fill(data) {
        const form = { ...this.blank };
        for (const [key, value] of Object.entries(data)) {
            if (!(key in this.blank)) continue;
            form[key] = typeof this.blank[key] === 'boolean'
                ? value === true || value === 1 || value === '1' || value === 'on'
                : value ?? '';
        }
        return form;
    },

    get action() {
        return this.mode === 'edit'
            ? String(this.routes.update ?? '').replace('__ID__', this.editId)
            : this.routes.store;
    },

    get isEdit() {
        return this.mode === 'edit';
    },

    create() {
        this.mode = 'create';
        this.editId = null;
        this.form = this.fill({});
        this.modalOpen = true;
    },

    edit(row) {
        this.mode = 'edit';
        this.editId = row.id;
        this.form = this.fill(row);
        this.modalOpen = true;
    },

    close() {
        this.modalOpen = false;
    },

    /**
     * Form modal yang disimpan lewat ajaxForm berhasil: tutup modal, atau kosongkan form
     * bila tombol "Simpan & buat lagi" yang diklik. Pasang: x-on:ajax-form-success="saved($event)".
     */
    saved(event) {
        if (event.detail?.submitter?.name === 'create_another') {
            this.create();
            return;
        }
        this.close();
    },

    confirm(options) {
        window.dispatchEvent(new CustomEvent('confirm', { detail: options }));
    },

    isSelected(id) {
        return this.selected.includes(id);
    },

    toggle(id) {
        this.selected = this.isSelected(id) ? this.selected.filter((x) => x !== id) : [...this.selected, id];
    },

    toggleAll(ids) {
        this.selected = ids.every((id) => this.isSelected(id)) ? [] : [...ids];
    },
});
