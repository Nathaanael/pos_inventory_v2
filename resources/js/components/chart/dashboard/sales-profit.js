import { mountChart, readConfig } from '../helpers';

/** Trend Penjualan & Laba (dulu SalesAndProfitChart Filament). */
const rupiah = (val) => 'Rp ' + Math.round(Number(val) || 0).toLocaleString('id-ID');
const short = (val) => {
    const n = Number(val) || 0;
    if (Math.abs(n) >= 1e9) return (n / 1e9).toFixed(1).replace('.0', '') + ' M';
    if (Math.abs(n) >= 1e6) return (n / 1e6).toFixed(1).replace('.0', '') + ' jt';
    if (Math.abs(n) >= 1e3) return (n / 1e3).toFixed(0) + ' rb';
    return String(n);
};

export default (el) => {
    const { labels, sales, profits } = readConfig(el);

    mountChart(el, (dark) => ({
        series: [
            { name: 'Total Penjualan', data: sales },
            { name: 'Laba Kotor', data: profits },
        ],
        colors: ['#465FFF', '#12B76A'],
        chart: { fontFamily: 'Outfit, sans-serif', type: 'area', height: 310, toolbar: { show: false }, zoom: { enabled: false } },
        stroke: { curve: 'smooth', width: [2, 2] },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.45, opacityTo: 0 } },
        dataLabels: { enabled: false },
        markers: { size: 0, hover: { size: 5 } },
        legend: { show: true, position: 'top', horizontalAlign: 'left', labels: { colors: dark ? '#94A3B8' : '#364153' } },
        grid: { borderColor: dark ? '#1E2640' : '#F3F4F6', xaxis: { lines: { show: false } }, yaxis: { lines: { show: true } } },
        xaxis: {
            categories: labels,
            axisBorder: { show: false },
            axisTicks: { show: false },
            tickAmount: Math.min(labels.length, 10),
            labels: { rotate: 0, hideOverlappingLabels: true, style: { colors: dark ? '#94A3B8' : '#6B7280', fontSize: '12px' } },
        },
        yaxis: { labels: { formatter: short, style: { colors: dark ? '#94A3B8' : '#6B7280', fontSize: '12px' } } },
        tooltip: { theme: dark ? 'dark' : 'light', y: { formatter: rupiah } },
    }));
};
