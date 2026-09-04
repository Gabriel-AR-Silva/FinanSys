export function normalizeMoneyInput(value) {
    const normalized = sanitizeMoneyInput(value).replace(/\s/g, '');

    if (normalized.includes(',')) {
        return normalized.replace(/\./g, '').replace(',', '.');
    }

    return normalized.replace(/\./g, '');
}

export function sanitizeMoneyInput(value) {
    const raw = String(value ?? '').replace(/[^\d,.]/g, '');
    const decimalSeparator = Math.max(raw.lastIndexOf(','), raw.lastIndexOf('.'));

    if (decimalSeparator === -1) {
        return raw.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
    }

    const integer = raw.slice(0, decimalSeparator).replace(/\D/g, '').replace(/^0+(?=\d)/, '') || '0';
    const decimal = raw.slice(decimalSeparator + 1).replace(/\D/g, '').slice(0, 2);

    return `${integer},${decimal}`;
}

export function formatMoneyInput(value) {
    const normalized = normalizeMoneyInput(value);

    if (normalized === '' || ! /^\d+(\.\d{0,2})?$/.test(normalized)) {
        return '';
    }

    const [integer, decimal = ''] = normalized.split('.');
    const groupedInteger = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 0 }).format(Number(integer));

    return `${groupedInteger},${decimal.padEnd(2, '0')}`;
}
