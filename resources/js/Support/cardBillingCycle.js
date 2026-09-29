export const daysInMonth = (year, month) =>
    new Date(Date.UTC(year, month, 0)).getUTCDate();

export const monthWithOffset = (year, month, offset) => {
    const date = new Date(Date.UTC(year, month - 1 + offset, 1));
    return [date.getUTCFullYear(), date.getUTCMonth() + 1];
};

const formatIsoDate = (year, month, day) =>
    `${year}-${String(month).padStart(2, "0")}-${String(day).padStart(2, "0")}`;

export function suggestedFirstDueOn(card, purchasedOn, fallback = "") {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(purchasedOn ?? "");
    if (!card || !match) return purchasedOn || fallback;

    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    const currentClosingDay = Math.min(Number(card.closing_day), daysInMonth(year, month));

    // FinanSys contract: closing_day is the FIRST day of the new cycle.
    // closing_day=5 => day 4 stays on the current invoice; day 5 rolls forward.
    const cycleOffset = day >= currentClosingDay ? 1 : 0;
    const [closingYear, closingMonth] = monthWithOffset(year, month, cycleOffset);
    const closingDay = Math.min(Number(card.closing_day), daysInMonth(closingYear, closingMonth));

    let dueYear = closingYear;
    let dueMonth = closingMonth;
    let dueDay = Math.min(Number(card.due_day), daysInMonth(dueYear, dueMonth));

    if (Date.UTC(dueYear, dueMonth - 1, dueDay) <= Date.UTC(closingYear, closingMonth - 1, closingDay)) {
        [dueYear, dueMonth] = monthWithOffset(dueYear, dueMonth, 1);
        dueDay = Math.min(Number(card.due_day), daysInMonth(dueYear, dueMonth));
    }

    return formatIsoDate(dueYear, dueMonth, dueDay);
}
