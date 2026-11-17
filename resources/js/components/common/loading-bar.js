/**
 * Loading bar tipis di atas layar (pengganti indikator loading browser untuk request AJAX).
 * Ditampilkan oleh <x-common.loading-bar />.
 *
 *   Alpine.store('loadingBar').start()   // mulai / tambah request berjalan
 *   Alpine.store('loadingBar').done()    // selesai; bar penuh lalu hilang saat semua request selesai
 *
 * trackFetch() memasang loading bar otomatis untuk semua fetch() yang mengubah data
 * (POST/PUT/PATCH/DELETE), mis. checkout POS, bayar di halaman Meja, status KDS.
 */
export function trackFetch(store) {
    const original = window.fetch.bind(window);

    window.fetch = async (input, init = {}) => {
        const method = String(init.method ?? (input instanceof Request ? input.method : "GET")).toUpperCase();
        if (method === "GET" || method === "HEAD") return original(input, init);

        store().start();
        try {
            return await original(input, init);
        } finally {
            store().done();
        }
    };
}

export default {
    visible: false,
    progress: 0,
    pending: 0,
    trickle: null,
    hideTimer: null,

    start() {
        this.pending++;
        if (this.pending > 1) return;

        clearTimeout(this.hideTimer);
        clearInterval(this.trickle);
        this.visible = true;
        this.progress = 10;
        // Maju pelan mendekati 90% selama request berjalan.
        this.trickle = setInterval(() => {
            this.progress += (90 - this.progress) * 0.12;
        }, 200);
    },

    done() {
        this.pending = Math.max(0, this.pending - 1);
        if (this.pending > 0) return;

        clearInterval(this.trickle);
        this.progress = 100;
        this.hideTimer = setTimeout(() => {
            this.visible = false;
            this.progress = 0;
        }, 300);
    },
};
