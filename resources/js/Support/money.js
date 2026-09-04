export function normalizeMoneyInput(value) {
    const normalized = sanitizeMoneyInput(value).replace(/\s/g, '');

    if (normalized.includes(',')) {
        return normalized.replace(/\./g, '').replace(',', '.');
    }

    return normalized.replace(/\./g, '');
}

export function sanitizeMoneyInput(value) {
    const digits = String(value ?? '').replace(/\D/g, '').replace(/^0+(?=\d)/, '');
    const paddedDigits = (digits || '0').padStart(3, '0');
    const integer = paddedDigits.slice(0, -2).replace(/^0+(?=\d)/, '') || '0';
    const decimal = paddedDigits.slice(-2);

    return `${groupInteger(integer)},${decimal}`;
}

export function formatMoneyInput(value) {
    return sanitizeMoneyInput(value);
}

function groupInteger(value) {
    if (value === '') {
        return '';
    }

    return value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}
