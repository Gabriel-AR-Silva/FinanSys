const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const calendar = new Intl.DateTimeFormat('pt-BR', { timeZone: 'UTC' });

export const paymentMethods = {
    cash: 'Dinheiro',
    pix: 'Pix',
    debit: 'Débito',
    credit: 'Crédito',
};

export const plateTypes = { standard: 'Normal', custom: 'Personalizada' };

export function money(cents) {
    return currency.format(Number(cents) / 100);
}

export function date(value) {
    return value ? calendar.format(new Date(`${value}T12:00:00Z`)) : '—';
}
