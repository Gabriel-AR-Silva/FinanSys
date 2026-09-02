export function normalizeMoneyInput(value) {
    const normalized = String(value).trim().replace(/\s/g, '');

    if (normalized.includes(',')) {
        return normalized.replace(/\./g, '').replace(',', '.');
    }

    return normalized;
}
