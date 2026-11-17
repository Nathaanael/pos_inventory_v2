import Alpine from "alpinejs";

/**
 * Kirim <form> lewat AJAX dengan loading bar (tanpa reload halaman).
 *
 * Pemakaian di Blade:
 *   <form x-data="ajaxForm({ regions: ['nama-region'] })" x-on:submit.prevent="submit" ...>
 *
 * Server (lihat Controller::respond / respondError):
 *   - JSON { message, body?, redirect? } bila $request->expectsJson(); validasi gagal = 422 standar Laravel.
 *   - redirect: pindah ke halaman itu (pesan sukses ikut di-flash ke session oleh server).
 *
 * Region: setelah berhasil, elemen dengan id="nama" atau data-ajax-region="nama" diganti versi terbaru
 * dari halaman ini (Alpine otomatis menginisialisasi elemen baru). Masukkan form-nya sendiri ke salah
 * satu region agar isian ikut kosong kembali.
 *
 * Error validasi tampil di <p data-error-for="field"> (otomatis ada di <x-common.form.field>),
 * dan pesan pertama juga muncul sebagai toast. `busy` untuk menonaktifkan tombol.
 */
export default ({ regions = [] } = {}) => ({
    busy: false,
    errors: {},

    async submit() {
        if (this.busy) return;

        const form = this.$el;
        this.busy = true;
        this.errors = {};
        clearFieldErrors(form);
        Alpine.store("loadingBar").start();

        let navigating = false;
        try {
            const { data } = await window.axios({
                method: form.method,
                url: form.action,
                data: new FormData(form),
                headers: { Accept: "application/json" },
            });

            if (data?.redirect) {
                navigating = true;
                window.location.assign(data.redirect);
                return;
            }

            await refreshRegions(regions);
            toast("success", data?.message ?? "Berhasil disimpan", data?.body ?? null);
        } catch (error) {
            const response = error.response;
            if (response?.status === 422) {
                this.errors = Object.fromEntries(
                    Object.entries(response.data.errors ?? {}).map(([field, messages]) => [field, messages[0]]),
                );
                showFieldErrors(form, this.errors);
                toast("error", "Periksa kembali isian", Object.values(this.errors)[0]);
            } else {
                toast("error", response?.data?.message || "Gagal menyimpan", response?.data?.body ?? (response ? null : "Periksa koneksi lalu coba lagi."));
            }
        } finally {
            // Saat pindah halaman, biarkan bar & tombol tetap "loading" sampai halaman baru tampil.
            if (!navigating) {
                this.busy = false;
                Alpine.store("loadingBar").done();
            }
        }
    },
});

/** Ambil ulang halaman saat ini dan ganti region yang diminta. */
async function refreshRegions(names) {
    if (!names.length) return;

    const { data: html } = await window.axios.get(window.location.href, { headers: { Accept: "text/html" } });
    const fresh = new DOMParser().parseFromString(html, "text/html");

    for (const name of names) {
        const selector = `#${CSS.escape(name)}, [data-ajax-region="${CSS.escape(name)}"]`;
        const next = fresh.querySelectorAll(selector);
        document.querySelectorAll(selector).forEach((current, index) => {
            if (next[index]) current.replaceWith(document.importNode(next[index], true));
        });
    }
}

function clearFieldErrors(form) {
    form.querySelectorAll("[data-error-for]").forEach((el) => {
        el.textContent = "";
        el.classList.add("hidden");
    });
}

function showFieldErrors(form, errors) {
    for (const [field, message] of Object.entries(errors)) {
        const el = form.querySelector(`[data-error-for="${CSS.escape(field)}"]`);
        if (!el) continue;
        el.textContent = message;
        el.classList.remove("hidden");
    }
}

function toast(type, title, body = null) {
    window.dispatchEvent(new CustomEvent("toast", { detail: { type, title, body } }));
}
