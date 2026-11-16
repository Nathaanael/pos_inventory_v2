import { mountChart, readConfig } from '../helpers';

/** Penjualan per Kategori (dulu SalesByCategoryChart Filament, doughnut). */
const rupiah = (val) => 'Rp ' + Math.round(Number(val) || 0).toLocaleString('id-ID');

export default (el) => {
    const { labels, totals } = readConfig(el);

    mountChart(el, (dark) => ({
        series: totals,
        labels,
        colors: ['#465FFF', '#12B76A', '#F79009', '#7A5AF8', '#EE46BC', '#667085'],
        chart: { fontFamily: 'Outfit, sans-serif', type: 'donut', height: 320 },
        stroke: { width: 2, colors: [dark ? '#101828' : '#FFFFFF'] },
        dataLabels: { enabled: false },
        legend: { position: 'bottom', labels: { colors: dark ? '#94A3B8' : '#364153' } },
        plotOptions: {
            pie: {
                donut: {
                    size: '68%',
                    labels: {
                        show: true,
                        value: { color: dark ? '#E5E7EB' : '#1D2939', formatter: rupiah },
                        total: { show: true, label: 'Total', color: dark ? '#94A3B8' : '#667085', formatter: (w) => rupiah(w.globals.seriesTotals.reduce((a, b) => a + b, 0)) },
                    },
                },
            },
        },
        tooltip: { theme: dark ? 'dark' : 'light', y: { formatter: rupiah } },
        noData: { text: 'Belum ada penjualan', style: { color: dark ? '#94A3B8' : '#667085' } },
    }));
};
