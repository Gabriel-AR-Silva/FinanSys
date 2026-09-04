export function normalizeMoneyInput(value) {
    const normalized = sanitizeMoneyInput(value).replace(/\s/g, '');

    if (normalized.includes(',')) {
        return normalized.replace(/\./g, '').replace(',', '.');
    }

    return normalized.replace(/\./g, '');
}

export function sanitizeMoneyInput(value) {
    const raw = String(value ?? '').replace(/[^\d,.]/g, '');
    const commaIndex = raw.lastIndexOf(',');
    const dots = raw.match(/\./g) ?? [];
    const lastDotIndex = raw.lastIndexOf('.');
    const dotLooksDecimal = commaIndex === -1
        && dots.length === 1
        && raw.length - lastDotIndex - 1 <= 2;
    const decimalSeparator = commaIndex >= 0 ? commaIndex : (dotLooksDecimal ? lastDotIndex : -1);

    if (decimalSeparator === -1) {
        return groupInteger(raw.replace(/\D/g, '').replace(/^0+(?=\d)/, ''));
    }

    const integer = raw.slice(0, decimalSeparator).replace(/\D/g, '').replace(/^0+(?=\d)/, '') || '0';
    const decimal = raw.slice(decimalSeparator + 1).replace(/\D/g, '').slice(0, 2);

    return `${groupInteger(integer)},${decimal}`;
}

export function formatMoneyInput(value) {
    const normalized = normalizeMoneyInput(value);

    if (normalized === '' || ! /^\d+(\.\d{0,2})?$/.test(normalized)) {
        return '';
    }

    const [integer, decimal = ''] = normalized.split('.');
    return `${groupInteger(integer)},${decimal.padEnd(2, '0')}`;
}

function groupInteger(value) {
    if (value === '') {
        return '';
    }

    return value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}
