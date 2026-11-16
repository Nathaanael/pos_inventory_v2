/**
 * Pilihan dengan kotak pencarian (pengganti Select ->searchable() Filament).
 * Dipakai lewat <x-common.form.search-select>; nilai terpilih diikat dengan x-modelable="value".
 *
 * options: fungsi yang mengembalikan [{ value, label, description?, disabled? }]
 *          (fungsi, agar daftar bisa berubah mis. saat supplier diganti)
 */
export default (getOptions = () => []) => ({
    value: '',
    open: false,
    query: '',
    highlighted: 0,

    get options() {
        return getOptions() ?? [];
    },

    get selected() {
        return this.options.find((option) => String(option.value) === String(this.value)) ?? null;
    },

    get filtered() {
        const query = this.query.trim().toLowerCase();
        const options = query
            ? this.options.filter((option) => `${option.label} ${option.description ?? ''}`.toLowerCase().includes(query))
            : this.options;
        return options.slice(0, 100);
    },

    toggle() {
        this.open ? this.close() : this.show();
    },

    show() {
        this.open = true;
        this.query = '';
        this.highlighted = Math.max(0, this.filtered.findIndex((option) => String(option.value) === String(this.value)));
        this.$nextTick(() => this.$refs.search?.focus());
    },

    close() {
        this.open = false;
    },

    choose(option) {
        if (!option || option.disabled) return;
        this.value = option.value;
        this.close();
        // Tunggu x-model induk tersinkron dulu, baru beri tahu induk.
        this.$nextTick(() => {
            this.$root.dispatchEvent(new CustomEvent('selected', { detail: { value: option.value }, bubbles: true }));
            this.$refs.trigger?.focus();
        });
    },

    move(step) {
        const count = this.filtered.length;
        if (!count) return;
        this.highlighted = (this.highlighted + step + count) % count;
        this.$nextTick(() => this.$refs.list?.querySelector(`[data-index="${this.highlighted}"]`)?.scrollIntoView({ block: 'nearest' }));
    },
});
