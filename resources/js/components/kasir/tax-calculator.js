/**
 * Pratinjau Pajak & Biaya Layanan di layar kasir/POS.
 * Rumus SAMA dengan App\Services\TaxCalculator (server menghitung ulang saat menyimpan).
 *
 * lines: [{ subtotal, tax_rate (null = ikut master), tax_method }]
 * rules: [{ id, name, type: 'tax'|'service', rate, method: 'inclusive'|'exclusive', applies_to }]
 */
export default function calculateTaxes(lines, rules = [], orderType = null) {
    const subtotal = lines.reduce((sum, l) => sum + (Number(l.subtotal) || 0), 0);
    const result = { subtotal, service_amount: 0, tax_amount: 0, grand_total: subtotal, details: [] };
    if (!rules.length && !lines.some((l) => l.tax_rate !== null && l.tax_rate !== undefined && l.tax_rate !== '')) return result;
    if (subtotal <= 0) return result;

    const applicable = rules.filter((r) => r.applies_to === 'all' || (orderType && r.applies_to === orderType));
    const services = applicable.filter((r) => r.type === 'service');
    const taxes = applicable.filter((r) => r.type === 'tax');

    const details = new Map();
    const add = (key, rule, amount) => {
        if (!details.has(key)) {
            details.set(key, { name: rule.name, type: rule.type, rate: Number(rule.rate), method: rule.method, included: rule.method === 'inclusive', amount: 0 });
        }
        details.get(key).amount += amount;
    };

    let exclusiveServiceRate = 0;
    for (const rule of services) {
        const r = rule.rate / 100;
        if (rule.method === 'exclusive') {
            add(`s${rule.id}`, rule, subtotal * r);
            exclusiveServiceRate += r;
        } else {
            add(`s${rule.id}`, rule, subtotal - subtotal / (1 + r));
        }
    }

    for (const line of lines) {
        const base = (Number(line.subtotal) || 0) * (1 + exclusiveServiceRate);
        const hasOwn = line.tax_rate !== null && line.tax_rate !== undefined && line.tax_rate !== '';
        const lineTaxes = hasOwn
            ? [{
                id: `item-${line.tax_rate}-${line.tax_method || 'exclusive'}`,
                name: 'Pajak Barang', // persentase ditampilkan terpisah (rate)
                type: 'tax',
                rate: Number(line.tax_rate),
                method: line.tax_method || 'exclusive',
            }]
            : taxes;

        for (const rule of lineTaxes) {
            if (rule.rate <= 0) continue;
            const r = rule.rate / 100;
            add(`t${rule.id}`, rule, rule.method === 'exclusive' ? base * r : base - base / (1 + r));
        }
    }

    result.details = [...details.values()].map((d) => ({ ...d, amount: Math.round(d.amount) })).filter((d) => d.amount > 0);
    result.service_amount = result.details.filter((d) => d.type === 'service' && !d.included).reduce((s, d) => s + d.amount, 0);
    result.tax_amount = result.details.filter((d) => d.type === 'tax' && !d.included).reduce((s, d) => s + d.amount, 0);
    result.grand_total = subtotal + result.service_amount + result.tax_amount;

    return result;
}
