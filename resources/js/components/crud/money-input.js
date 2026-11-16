/**
 * Input rupiah: tampil "15.000", nilai yang dikirim tetap angka mentah "15000"
 * (pengganti ->mask(RawJs::make('$money($input)')) di Filament).
 *
 * Pakai bersama x-modelable="raw" agar bisa diikat ke x-model induk.
 */
export default (initial = '') => ({
    raw: normalize(initial),

    get display() {
        if (this.raw === '') return '';
        return Number(this.raw).toLocaleString('id-ID', { maximumFractionDigits: 2 });
    },

    onInput(event) {
        const digits = event.target.value.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
        this.raw = digits;
        event.target.value = this.display;
    },
});

function normalize(value) {
    if (value === null || value === undefined || value === '') return '';
    const number = Number(value);
    return Number.isFinite(number) ? String(Math.round(number)) : '';
}
