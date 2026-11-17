import Alpine from 'alpinejs';

/**
 * Loading untuk form biasa (bukan AJAX) yang memuat ulang / pindah halaman:
 * tombol menampilkan spinner + loading bar sampai halaman berikutnya tampil, dan mencegah kirim dobel.
 *
 *   <form method="POST" x-data="formLoading" x-on:submit="start($event)">
 *       <button x-bind:disabled="loading">…</button>
 *   </form>
 *
 * Bisa juga untuk tautan: <a x-data="formLoading" x-on:click="start()">.
 */
export default () => ({
    loading: false,

    init() {
        // Kembali lewat tombol Back (bfcache): reset status loading.
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) this.loading = false;
        });
    },

    start(event = null) {
        if (this.loading) {
            event?.preventDefault();
            return;
        }
        this.loading = true;
        Alpine.store('loadingBar').start();
    },
});
