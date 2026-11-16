{{-- Store tema terang/gelap (Alpine) + pencegah kedip tema. Dipakai layout kasir. --}}
<script>
    (function () {
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark');
            document.documentElement.setAttribute('data-color-scheme', 'dark');
        }
    })();

    document.addEventListener('alpine:init', () => {
        Alpine.store('theme', {
            theme: 'light',
            resolvedTheme: 'light',
            init() {
                this.theme = localStorage.getItem('theme') === 'dark' ? 'dark' : 'light';
                this.updateTheme();
            },
            set(value) {
                this.theme = value === 'dark' ? 'dark' : 'light';
                localStorage.setItem('theme', this.theme);
                this.updateTheme();
                window.dispatchEvent(new CustomEvent('theme-changed', { detail: this.theme }));
            },
            toggle() {
                this.set(this.resolvedTheme === 'dark' ? 'light' : 'dark');
            },
            updateTheme() {
                const html = document.documentElement;
                const isDark = this.theme === 'dark';
                html.classList.toggle('dark', isDark);
                this.resolvedTheme = isDark ? 'dark' : 'light';
                html.setAttribute('data-color-scheme', this.resolvedTheme);
                html.dataset.theme = this.resolvedTheme;
                html.style.colorScheme = this.resolvedTheme;
            },
        });
    });
</script>
