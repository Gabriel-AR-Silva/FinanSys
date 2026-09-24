<script setup>
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import Modal from "@/Components/Modal.vue";
import TextInput from "@/Components/TextInput.vue";
import SearchableSelect from "@/Components/SearchableSelect.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import {
    formatMoneyInput,
    normalizeMoneyInput,
    sanitizeMoneyInput,
} from "@/Support/money";
import { Head, router, useForm } from "@inertiajs/vue3";
import {
    CalendarDays,
    CreditCard,
    Plus,
    ReceiptText,
    WalletCards,
} from "@lucide/vue";
import { computed, ref } from "vue";

const props = defineProps({
    cards: { type: Array, required: true },
    categories: { type: Array, required: true },
    accounts: { type: Array, required: true },
    today: { type: String, required: true },
});

const modal = ref(null);
const selectedCardId = ref(props.cards[0]?.id ?? "");
const selectedCard = computed(() =>
    props.cards.find((card) => card.id === Number(selectedCardId.value)),
);
const card = selectedCard;
const paymentCard = computed(() =>
    props.cards.find((card) => card.id === Number(paymentForm.credit_card_id)),
);
const advanceCard = computed(() =>
    props.cards.find((card) => card.id === Number(advanceForm.credit_card_id)),
);
const eligibleCharges = computed(() =>
    (paymentCard.value?.charges ?? []).filter(
        (charge) =>
            charge.status === "pending" &&
            charge.due_on.slice(0, 7) <= paymentForm.paid_on.slice(0, 7),
    ),
);

const deletingCardId = ref(null);
const deletingPaymentId = ref(null);
const editingPaymentId = ref(null);

const daysInMonth = (year, month) =>
    new Date(Date.UTC(year, month, 0)).getUTCDate();

const monthWithOffset = (year, month, offset) => {
    const date = new Date(Date.UTC(year, month - 1 + offset, 1));
    return [date.getUTCFullYear(), date.getUTCMonth() + 1];
};

const formatIsoDate = (year, month, day) =>
    `${year}-${String(month).padStart(2, "0")}-${String(day).padStart(2, "0")}`;

function suggestedFirstDueOn(cardId, purchasedOn) {
    const card = props.cards.find((item) => item.id === Number(cardId));
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(purchasedOn ?? "");
    if (!card || !match) return purchasedOn || props.today;

    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    const currentClosingDay = Math.min(
        Number(card.closing_day),
        daysInMonth(year, month),
    );
    const cycleOffset = day > currentClosingDay ? 1 : 0;
    const [closingYear, closingMonth] = monthWithOffset(
        year,
        month,
        cycleOffset,
    );
    const closingDay = Math.min(
        Number(card.closing_day),
        daysInMonth(closingYear, closingMonth),
    );

    let dueYear = closingYear;
    let dueMonth = closingMonth;
    let dueDay = Math.min(
        Number(card.due_day),
        daysInMonth(dueYear, dueMonth),
    );

    const closingStamp = Date.UTC(closingYear, closingMonth - 1, closingDay);
    let dueStamp = Date.UTC(dueYear, dueMonth - 1, dueDay);

    if (dueStamp <= closingStamp) {
        [dueYear, dueMonth] = monthWithOffset(dueYear, dueMonth, 1);
        dueDay = Math.min(
            Number(card.due_day),
            daysInMonth(dueYear, dueMonth),
        );
    }

    return formatIsoDate(dueYear, dueMonth, dueDay);
}
const cardForm = useForm({
    name: "",
    closing_day: 5,
    due_day: 12,
    credit_limit: "",
    operation_id: crypto.randomUUID(),
});
const limitForm = useForm({
    credit_limit: "",
});
const purchaseForm = useForm({
    credit_card_id: selectedCardId.value,
    category_id: "",
    description: "",
    planning_type: "ordinary",
    gross_amount: "0,00",
    purchased_on: props.today,
    installments_count: 1,
    paid_installments_count: 0,
    first_due_on: suggestedFirstDueOn(selectedCardId.value, props.today),
    operation_id: crypto.randomUUID(),
});
const chargeForm = useForm({
    credit_card_id: selectedCardId.value,
    category_id: "",
    type: "interest",
    description: "",
    planning_type: "extraordinary",
    amount: "0,00",
    charged_on: props.today,
    due_on: props.today,
    operation_id: crypto.randomUUID(),
});
const paymentForm = useForm({
    credit_card_id: selectedCardId.value,
    source_account_id: "",
    amount: "0,00",
    paid_on: props.today,
    card_charge_ids: [],
    operation_id: crypto.randomUUID(),
});
const advanceForm = useForm({
    credit_card_id: selectedCardId.value,
    source_account_id: "",
    installment_ids: [],
    discount_amount: "0,00",
    expected_gross_amount: "0.00",
    advanced_on: props.today,
    operation_id: crypto.randomUUID(),
});

const decimalToCents = (value) => {
    const normalized = String(value ?? "0").replace(",", ".");
    const [whole = "0", fraction = ""] = normalized.split(".");
    return (
        BigInt(whole || "0") * 100n +
        BigInt(fraction.padEnd(2, "0").slice(0, 2) || "0")
    );
};
const centsToDecimal = (value) =>
    `${value / 100n}.${String(value % 100n).padStart(2, "0")}`;
const eligibleInstallments = computed(() =>
    (paymentCard.value?.purchases ?? [])
        .flatMap((purchase) =>
            purchase.installments.map((installment) => ({
                ...installment,
                description: purchase.description,
                purchased_on: purchase.purchased_on,
            })),
        )
        .filter(
            (installment) =>
                installment.status === "pending" &&
                installment.due_on.slice(0, 7) <=
                    paymentForm.paid_on.slice(0, 7),
        )
        .sort(
            (left, right) =>
                left.due_on.localeCompare(right.due_on) || left.id - right.id,
        ),
);
const paymentPreview = computed(() => {
    let remaining = decimalToCents(normalizeMoneyInput(paymentForm.amount));
    const selectedIds = new Set(paymentForm.card_charge_ids.map(Number));
    const charges = eligibleCharges.value
        .filter((charge) => selectedIds.has(charge.id))
        .sort(
            (left, right) =>
                left.due_on.localeCompare(right.due_on) || left.id - right.id,
        );
    const items = [];

    for (const charge of charges) {
        if (remaining <= 0n) break;
        const pending = decimalToCents(charge.pending_amount);
        const allocated = remaining < pending ? remaining : pending;
        items.push({
            key: `charge-${charge.id}`,
            label: charge.description,
            kind: "Encargo",
            due_on: charge.due_on,
            allocated: centsToDecimal(allocated),
            after: centsToDecimal(pending - allocated),
        });
        remaining -= allocated;
    }
    for (const installment of eligibleInstallments.value) {
        if (remaining <= 0n) break;
        const pending =
            decimalToCents(installment.gross_amount) -
            decimalToCents(installment.paid_amount);
        const allocated = remaining < pending ? remaining : pending;
        items.push({
            key: `installment-${installment.id}`,
            label: `${installment.description} · parcela ${installment.number}`,
            kind: "Parcela",
            due_on: installment.due_on,
            allocated: centsToDecimal(allocated),
            after: centsToDecimal(pending - allocated),
        });
        remaining -= allocated;
    }

    return {
        items,
        remainder: remaining,
        valid:
            decimalToCents(normalizeMoneyInput(paymentForm.amount)) > 0n &&
            remaining === 0n,
    };
});
const eligibleDebt = (card) => {
    const month = paymentForm.paid_on.slice(0, 7);
    const installmentDebt = card.purchases
        .flatMap((purchase) => purchase.installments)
        .filter(
            (installment) =>
                installment.status === "pending" &&
                installment.due_on.slice(0, 7) <= month,
        )
        .reduce(
            (total, installment) =>
                total +
                decimalToCents(installment.gross_amount) -
                decimalToCents(installment.paid_amount),
            0n,
        );
    const chargeDebt = card.charges
        .filter(
            (charge) =>
                charge.status === "pending" &&
                charge.due_on.slice(0, 7) <= month,
        )
        .reduce(
            (total, charge) => total + decimalToCents(charge.pending_amount),
            0n,
        );
    return centsToDecimal(installmentDebt + chargeDebt);
};
const eligibleAdvanceInstallments = computed(() =>
    (advanceCard.value?.purchases ?? [])
        .flatMap((purchase) =>
            purchase.installments.map((installment) => ({
                ...installment,
                description: purchase.description,
                purchased_on: purchase.purchased_on,
            })),
        )
        .filter(
            (installment) =>
                installment.status === "pending" &&
                installment.purchased_on <= advanceForm.advanced_on &&
                installment.due_on.slice(0, 7) >
                    advanceForm.advanced_on.slice(0, 7),
        )
        .sort(
            (left, right) =>
                left.due_on.localeCompare(right.due_on) || left.id - right.id,
        ),
);
const advancePreview = computed(() => {
    const selectedIds = new Set(advanceForm.installment_ids.map(Number));
    const selected = eligibleAdvanceInstallments.value.filter((installment) =>
        selectedIds.has(installment.id),
    );
    const gross = selected.reduce(
        (total, installment) =>
            total +
            decimalToCents(installment.gross_amount) -
            decimalToCents(installment.paid_amount),
        0n,
    );
    const discount = decimalToCents(
        normalizeMoneyInput(advanceForm.discount_amount),
    );
    if (gross <= 0n || discount < 0n || discount > gross)
        return {
            items: [],
            gross,
            discount,
            net: gross - discount,
            valid: false,
        };

    let usedDiscount = 0n;
    const rows = selected.map((installment) => {
        const installmentGross =
            decimalToCents(installment.gross_amount) -
            decimalToCents(installment.paid_amount);
        const numerator = discount * installmentGross;
        const allocatedDiscount = numerator / gross;
        usedDiscount += allocatedDiscount;
        return {
            installment,
            gross: installmentGross,
            discount: allocatedDiscount,
            fraction: numerator % gross,
        };
    });
    const priority = [...rows].sort((left, right) =>
        left.fraction === right.fraction
            ? left.installment.id - right.installment.id
            : left.fraction > right.fraction
              ? -1
              : 1,
    );
    let centsLeft = discount - usedDiscount;
    for (let index = 0; centsLeft > 0n; index += 1, centsLeft -= 1n)
        priority[index].discount += 1n;

    return {
        items: rows.map((row) => ({
            id: row.installment.id,
            label: `${row.installment.description} · parcela ${row.installment.number}`,
            due_on: row.installment.due_on,
            gross: centsToDecimal(row.gross),
            discount: centsToDecimal(row.discount),
            net: centsToDecimal(row.gross - row.discount),
        })),
        gross,
        discount,
        net: gross - discount,
        valid: selected.length > 0 && Boolean(advanceForm.source_account_id),
    };
});

function open(kind, cardId = selectedCardId.value) {
    selectedCardId.value = cardId;
    if (kind === "limit") {
        const card = props.cards.find((item) => item.id === Number(cardId));
        limitForm.credit_limit = card?.limit?.total ? formatMoneyInput(card.limit.total) : "";
        limitForm.clearErrors();
    }
    if (kind === "purchase") {
        purchaseForm.credit_card_id = cardId;
        purchaseForm.first_due_on = suggestedFirstDueOn(
            cardId,
            purchaseForm.purchased_on,
        );
    }
    if (kind === "charge") chargeForm.credit_card_id = cardId;
    if (kind === "payment") {
        editingPaymentId.value = null;
        paymentForm.credit_card_id = cardId;
        paymentForm.card_charge_ids = [];
    }
    if (kind === "advance") {
        advanceForm.credit_card_id = cardId;
        advanceForm.installment_ids = [];
    }
    modal.value = kind;
}

function destroyCard(card) {
    if (
        !window.confirm(
            `Excluir o cartão "${card.name}"? Só cartões sem histórico financeiro podem ser removidos.`,
        )
    )
        return;

    deletingCardId.value = card.id;
    router.delete(route("credit-cards.destroy", card.id), {
        preserveScroll: true,
        onFinish: () => {
            deletingCardId.value = null;
        },
    });
}

function editPayment(payment, cardId) {
    selectedCardId.value = cardId;
    editingPaymentId.value = payment.id;
    paymentForm.credit_card_id = cardId;
    paymentForm.source_account_id = payment.source_account_id;
    paymentForm.amount = formatMoneyInput(payment.amount);
    paymentForm.paid_on = payment.paid_on;
    paymentForm.card_charge_ids = [...(payment.selected_charge_ids ?? [])];
    paymentForm.operation_id = crypto.randomUUID();
    paymentForm.clearErrors();
    modal.value = "payment";
}

function destroyPayment(payment) {
    if (!window.confirm('Excluir este pagamento de fatura? As baixas vinculadas serão revertidas.')) return;
    deletingPaymentId.value = payment.id;
    router.delete(route("card-payments.destroy", payment.id), {
        preserveScroll: true,
        onFinish: () => { deletingPaymentId.value = null; },
    });
}

const purchaseInstallmentPreview = computed(() => {
    const total = Math.max(1, Number(purchaseForm.installments_count) || 1);
    const paid = Math.min(Math.max(0, Number(purchaseForm.paid_installments_count) || 0), total - 1);
    const totalCents = decimalToCents(normalizeMoneyInput(purchaseForm.gross_amount));
    const base = totalCents / BigInt(total);
    const remainder = totalCents % BigInt(total);
    let remaining = 0n;
    for (let index = paid; index < total; index += 1) {
        remaining += base + (BigInt(index) < remainder ? 1n : 0n);
    }
    return { total, paid, remainingCount: total - paid, remaining: centsToDecimal(remaining) };
});

function refreshFirstDueOn() {
    purchaseForm.first_due_on = suggestedFirstDueOn(
        purchaseForm.credit_card_id,
        purchaseForm.purchased_on,
    );
}

function close() {
    if (
        ![cardForm, limitForm, purchaseForm, chargeForm, paymentForm, advanceForm].some(
            (form) => form.processing,
        )
    )
        modal.value = null;
}

function submitCharge() {
    chargeForm
        .transform((data) => ({
            ...data,
            amount: normalizeMoneyInput(data.amount),
        }))
        .post(route("card-charges.store"), {
            preserveScroll: true,
            onSuccess: () => {
                modal.value = null;
                chargeForm.defaults({
                    ...chargeForm.data(),
                    description: "",
                    amount: "0,00",
                    operation_id: crypto.randomUUID(),
                });
                chargeForm.reset();
            },
        });
}

function submitCard() {
    cardForm
        .transform((data) => ({
            ...data,
            credit_limit: data.credit_limit ? normalizeMoneyInput(data.credit_limit) : null,
        }))
        .post(route("credit-cards.store"), {
        preserveScroll: true,
        onSuccess: () => {
            modal.value = null;
            cardForm.defaults({
                name: "",
                closing_day: 5,
                due_day: 12,
                credit_limit: "",
                operation_id: crypto.randomUUID(),
            });
            cardForm.reset();
        },
    });
}

function submitLimit() {
    const cardId = Number(selectedCardId.value);
    limitForm
        .transform((data) => ({
            credit_limit: data.credit_limit ? normalizeMoneyInput(data.credit_limit) : null,
        }))
        .patch(route("credit-cards.limit.update", cardId), {
            preserveScroll: true,
            onSuccess: () => {
                modal.value = null;
                limitForm.reset();
            },
        });
}

function submitPurchase() {
    purchaseForm
        .transform((data) => ({
            ...data,
            gross_amount: normalizeMoneyInput(data.gross_amount),
        }))
        .post(route("card-purchases.store"), {
            preserveScroll: true,
            onSuccess: () => {
                modal.value = null;
                purchaseForm.defaults({
                    ...purchaseForm.data(),
                    description: "",
                    gross_amount: "0,00",
                    installments_count: 1,
                    paid_installments_count: 0,
                    operation_id: crypto.randomUUID(),
                });
                purchaseForm.reset();
            },
        });
}

function submitPayment() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            modal.value = null;
            editingPaymentId.value = null;
            paymentForm.defaults({
                ...paymentForm.data(),
                amount: "0,00",
                operation_id: crypto.randomUUID(),
            });
            paymentForm.reset();
        },
    };
    paymentForm.transform((data) => ({ ...data, amount: normalizeMoneyInput(data.amount) }));
    if (editingPaymentId.value) {
        paymentForm.put(route("card-payments.update", editingPaymentId.value), options);
        return;
    }
    paymentForm.post(route("card-payments.store"), options);
}

function submitAdvance() {
    advanceForm.expected_gross_amount = centsToDecimal(
        advancePreview.value.gross,
    );
    advanceForm
        .transform((data) => ({
            ...data,
            discount_amount: normalizeMoneyInput(data.discount_amount),
        }))
        .post(route("card-advances.store"), {
            preserveScroll: true,
            onSuccess: () => {
                modal.value = null;
                advanceForm.defaults({
                    ...advanceForm.data(),
                    installment_ids: [],
                    discount_amount: "0,00",
                    expected_gross_amount: "0.00",
                    operation_id: crypto.randomUUID(),
                });
                advanceForm.reset();
            },
        });
}

const date = (value) => value.split("-").reverse().join("/");
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Cartões" />
        <div class="flex min-w-0 flex-col gap-6">
            <header
                class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
            >
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-emerald-700">
                        CRÉDITO SEM DUPLICIDADE
                    </p>
                    <h1
                        class="mt-1 text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl"
                    >
                        Cartões e parcelas
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                        A compra compromete o mês da parcela; pagar a fatura
                        apenas liquida a obrigação.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold" type="button" @click="open('card')">
                        <Plus :size="17" class="mr-1 inline" />Cartão
                    </button>
                    <button :disabled="!cards.length || !categories.length" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-40" type="button" @click="open('purchase')">
                        <ReceiptText :size="17" class="mr-1 inline" />Nova compra
                    </button>
                    <details v-if="cards.length" class="relative">
                        <summary class="cursor-pointer list-none rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold">Mais ações ▾</summary>
                        <div class="absolute right-0 z-20 mt-2 grid min-w-48 gap-1 rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
                            <button class="rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-50" type="button" @click="open('charge')">Confirmar encargo</button>
                            <button class="rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-50" type="button" @click="open('payment')">Pagar fatura</button>
                            <button class="rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-50" type="button" @click="open('advance')">Antecipar parcelas</button>
                        </div>
                    </details>
                </div>
            </header>

            <section
                v-if="!cards.length"
                class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center"
            >
                <CreditCard :size="34" class="mx-auto text-slate-400" />
                <h2 class="mt-3 font-semibold text-slate-900">
                    Nenhum cartão por aqui
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Cadastre o primeiro para organizar compras e parcelas.
                </p>
            </section>

            <section v-else class="min-w-0">
                <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div class="w-full sm:max-w-sm">
                        <label for="active-card" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cartão em visualização</label>
                        <select id="active-card" v-model="selectedCardId" class="mt-1 w-full rounded-xl border-slate-300 bg-white">
                            <option v-for="item in cards" :key="item.id" :value="item.id">{{ item.name }}</option>
                        </select>
                    </div>
                    <p class="text-xs text-slate-500">Um cartão por vez para manter o gerenciamento limpo.</p>
                </div>
                <article
                    v-if="card"
                    :key="card.id"
                    class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="flex items-start justify-between gap-4 bg-slate-950 p-5 text-white"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-lg font-semibold">
                                {{ card.name }}
                            </p>
                            <p class="mt-1 text-xs text-slate-400">
                                Fecha dia {{ card.closing_day }} · vence dia
                                {{ card.due_day }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-slate-400">
                                Dívida pendente
                            </p>
                            <p class="mt-1 text-lg font-semibold">
                                R$ {{ formatMoneyInput(card.pending) }}
                            </p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-px border-b border-slate-100 bg-slate-100 sm:grid-cols-4">
                        <div class="bg-white p-3"><p class="text-[11px] uppercase tracking-wide text-slate-500">Limite total</p><p class="mt-1 text-sm font-semibold text-slate-900">{{ card.limit.total === null ? 'Não informado' : `R$ ${formatMoneyInput(card.limit.total)}` }}</p></div>
                        <div class="bg-white p-3"><p class="text-[11px] uppercase tracking-wide text-slate-500">Em uso</p><p class="mt-1 text-sm font-semibold text-slate-900">R$ {{ formatMoneyInput(card.limit.outstanding) }}</p></div>
                        <div class="bg-white p-3"><p class="text-[11px] uppercase tracking-wide text-slate-500">Disponível</p><p class="mt-1 text-sm font-semibold" :class="card.limit.total === null ? 'text-slate-500' : 'text-emerald-700'">{{ card.limit.available === null ? 'Sem controle' : `R$ ${formatMoneyInput(card.limit.available)}` }}</p></div>
                        <div class="bg-white p-3"><p class="text-[11px] uppercase tracking-wide text-slate-500">Excesso</p><p class="mt-1 text-sm font-semibold" :class="Number(card.limit.over_limit) > 0 ? 'text-rose-700' : 'text-slate-500'">R$ {{ formatMoneyInput(card.limit.over_limit) }}</p></div>
                    </div>
                    <p
                        v-if="card.may_have_unconfirmed_charges"
                        class="border-b border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-900"
                    >
                        Há obrigação vencida. A operadora pode ter lançado juros
                        ou multa ainda não informados; confira a fatura antes de
                        pagar. Nenhum valor foi estimado.
                    </p>
                    <div class="flex items-center justify-between gap-2 border-b border-slate-100 p-3">
                        <button class="rounded-lg bg-slate-950 px-3 py-2 text-xs font-semibold text-white" type="button" @click="open('purchase', card.id)">Nova compra</button>
                        <details class="relative">
                            <summary class="cursor-pointer list-none rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700">Gerenciar ▾</summary>
                            <div class="absolute right-0 z-20 mt-2 grid min-w-52 gap-1 rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
                                <button class="rounded-lg px-3 py-2 text-left text-xs hover:bg-slate-50" type="button" @click="open('limit', card.id)">Ajustar limite</button>
                                <button class="rounded-lg px-3 py-2 text-left text-xs hover:bg-slate-50" type="button" @click="open('charge', card.id)">Confirmar encargo</button>
                                <button class="rounded-lg px-3 py-2 text-left text-xs hover:bg-slate-50" type="button" @click="open('payment', card.id)">Pagar fatura</button>
                                <button class="rounded-lg px-3 py-2 text-left text-xs hover:bg-slate-50" type="button" @click="open('advance', card.id)">Antecipar parcelas</button>
                                <button class="rounded-lg px-3 py-2 text-left text-xs text-rose-700 hover:bg-rose-50 disabled:opacity-50" type="button" :disabled="deletingCardId === card.id" @click="destroyCard(card)">Excluir cartão</button>
                            </div>
                        </details>
                    </div>
                    <div
                        v-if="card.charges.length"
                        class="border-b border-slate-100 bg-amber-50/60 p-4"
                    >
                        <p
                            class="text-xs font-semibold uppercase tracking-wide text-amber-900"
                        >
                            Juros e multas confirmados
                        </p>
                        <div class="mt-2 grid gap-2">
                            <div
                                v-for="charge in card.charges"
                                :key="charge.id"
                                class="flex items-center justify-between gap-3 rounded-xl bg-white p-3 text-xs"
                            >
                                <span class="min-w-0"
                                    ><span
                                        class="block truncate font-semibold text-slate-900"
                                        >{{ charge.description }}</span
                                    ><span class="text-slate-500"
                                        >{{
                                            charge.type === "interest"
                                                ? "Juros"
                                                : "Multa"
                                        }}
                                        · vence {{ date(charge.due_on) }}</span
                                    ></span
                                >
                                <span class="shrink-0 text-right"
                                    ><span class="block font-semibold"
                                        >R$
                                        {{
                                            formatMoneyInput(charge.amount)
                                        }}</span
                                    ><span
                                        :class="
                                            charge.status === 'paid'
                                                ? 'text-emerald-700'
                                                : 'text-amber-700'
                                        "
                                        >{{
                                            charge.status === "paid"
                                                ? "Pago"
                                                : `R$ ${formatMoneyInput(charge.paid_amount)} pago`
                                        }}</span
                                    ></span
                                >
                            </div>
                        </div>
                    </div>
                    <div v-if="card.payments?.length" class="border-b border-slate-100 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pagamentos de fatura</p>
                        <div class="mt-2 grid gap-2">
                            <div v-for="payment in card.payments" :key="payment.id" class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-3 text-xs">
                                <span><strong>R$ {{ formatMoneyInput(payment.amount) }}</strong><br><span class="text-slate-500">{{ date(payment.paid_on) }} · {{ payment.source_account_name }}</span></span>
                                <span class="flex shrink-0 gap-1">
                                    <button type="button" class="rounded-lg px-2 py-1 font-semibold text-slate-700 hover:bg-white" @click="editPayment(payment, card.id)">Editar</button>
                                    <button type="button" class="rounded-lg px-2 py-1 font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-50" :disabled="deletingPaymentId === payment.id" @click="destroyPayment(payment)">Excluir</button>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="card.purchases.length"
                        class="divide-y divide-slate-100"
                    >
                        <details
                            v-for="purchase in card.purchases"
                            :key="purchase.id"
                            class="group p-4"
                        >
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between gap-3"
                            >
                                <span class="min-w-0"
                                    ><span
                                        class="block truncate text-sm font-semibold text-slate-900"
                                        >{{ purchase.description }}</span
                                    ><span class="block text-xs text-slate-500"
                                        >{{ purchase.category_name }} ·
                                        {{ date(purchase.purchased_on) }}</span
                                    ></span
                                >
                                <span class="shrink-0 text-sm font-semibold"
                                    >R$
                                    {{
                                        formatMoneyInput(purchase.gross_amount)
                                    }}</span
                                >
                            </summary>
                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                <div
                                    v-for="installment in purchase.installments"
                                    :key="installment.number"
                                    class="rounded-xl bg-slate-50 p-3 text-xs"
                                >
                                    <div class="flex justify-between gap-2">
                                        <span
                                            >{{ installment.number }}/{{
                                                purchase.installments_count
                                            }}
                                            ·
                                            {{ date(installment.due_on) }}</span
                                        ><span
                                            :class="
                                                installment.status === 'pending'
                                                    ? 'text-amber-700'
                                                    : 'text-emerald-700'
                                            "
                                            >{{
                                                installment.status ===
                                                "advanced"
                                                    ? "Antecipada"
                                                    : installment.status ===
                                                        "paid"
                                                      ? "Paga"
                                                      : "Pendente"
                                            }}</span
                                        >
                                    </div>
                                    <p class="mt-1 font-semibold">
                                        R$
                                        {{
                                            formatMoneyInput(
                                                installment.paid_amount,
                                            )
                                        }}
                                        de R$
                                        {{
                                            formatMoneyInput(
                                                installment.gross_amount,
                                            )
                                        }}
                                    </p>
                                    <p
                                        v-if="installment.advance"
                                        class="mt-1 text-sky-700"
                                    >
                                        Pago R$
                                        {{
                                            formatMoneyInput(
                                                installment.advance.net_amount,
                                            )
                                        }}
                                        · desconto R$
                                        {{
                                            formatMoneyInput(
                                                installment.advance
                                                    .discount_amount,
                                            )
                                        }}
                                        em
                                        {{
                                            date(
                                                installment.advance.advanced_on,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </details>
                    </div>
                    <p v-else class="p-5 text-sm text-slate-500">
                        Nenhuma compra registrada neste cartão.
                    </p>
                </article>
            </section>
        </div>

        <Modal :show="modal === 'card'" max-width="md" @close="close"
            ><form
                class="flex flex-col gap-4 p-5 sm:p-6"
                @submit.prevent="submitCard"
            >
                <h2 class="text-xl font-semibold">Cadastrar cartão</h2>
                <div>
                    <InputLabel for="card-name" value="Nome" /><TextInput
                        id="card-name"
                        v-model="cardForm.name"
                        required
                        class="mt-2 w-full"
                    /><InputError :message="cardForm.errors.name" />
                </div>
                <div>
                    <InputLabel for="credit-limit" value="Limite total (opcional)" />
                    <TextInput
                        id="credit-limit"
                        :model-value="cardForm.credit_limit"
                        inputmode="decimal"
                        class="mt-2 w-full"
                        placeholder="Ex.: 5.000,00"
                        @update:model-value="cardForm.credit_limit = sanitizeMoneyInput($event)"
                    />
                    <p class="mt-1 text-xs leading-5 text-slate-500">Se informado, compras novas serão validadas contra o limite disponível. Deixe vazio para não controlar limite.</p>
                    <InputError :message="cardForm.errors.credit_limit" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel
                            for="closing-day"
                            value="Dia do fechamento"
                        /><TextInput
                            id="closing-day"
                            v-model="cardForm.closing_day"
                            type="number"
                            min="1"
                            max="31"
                            required
                            class="mt-2 w-full"
                        /><InputError :message="cardForm.errors.closing_day" />
                    </div>
                    <div>
                        <InputLabel
                            for="due-day"
                            value="Dia do vencimento"
                        /><TextInput
                            id="due-day"
                            v-model="cardForm.due_day"
                            type="number"
                            min="1"
                            max="31"
                            required
                            class="mt-2 w-full"
                        /><InputError :message="cardForm.errors.due_day" />
                    </div>
                </div>
                <InputError :message="cardForm.errors.operation_id" />
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-xl border px-4 py-3 text-sm font-semibold"
                        @click="close"
                    >
                        Cancelar</button
                    ><button
                        :disabled="cardForm.processing"
                        class="rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        Salvar cartão
                    </button>
                </div>
            </form></Modal
        >

        <Modal :show="modal === 'limit'" max-width="md" @close="close">
            <form class="flex flex-col gap-4 p-5 sm:p-6" @submit.prevent="submitLimit">
                <div>
                    <h2 class="text-xl font-semibold">Ajustar limite do cartão</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500">O limite é uma referência operacional. A dívida continua vindo das compras, parcelas e encargos reais.</p>
                </div>
                <div>
                    <InputLabel for="edit-credit-limit" value="Limite total" />
                    <TextInput
                        id="edit-credit-limit"
                        :model-value="limitForm.credit_limit"
                        inputmode="decimal"
                        class="mt-2 w-full"
                        placeholder="Deixe vazio para não controlar"
                        @update:model-value="limitForm.credit_limit = sanitizeMoneyInput($event)"
                    />
                    <InputError :message="limitForm.errors.credit_limit" />
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-xl border px-4 py-3 text-sm font-semibold" @click="close">Cancelar</button>
                    <button :disabled="limitForm.processing" class="rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50">Salvar limite</button>
                </div>
            </form>
        </Modal>

        <Modal :show="modal === 'charge'" max-width="lg" @close="close"
            ><form
                class="flex flex-col gap-4 p-5 sm:p-6"
                @submit.prevent="submitCharge"
            >
                <div>
                    <h2 class="text-xl font-semibold">
                        Confirmar juros ou multa
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Cadastre somente um valor já confirmado pela operadora.
                        O encargo entra como nova despesa.
                    </p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="charge-card" value="Cartão" /><select
                            id="charge-card"
                            v-model="chargeForm.credit_card_id"
                            required
                            class="mt-2 w-full rounded-xl border-slate-300"
                        >
                            <option
                                v-for="card in cards"
                                :key="card.id"
                                :value="card.id"
                            >
                                {{ card.name }}
                            </option></select
                        ><InputError
                            :message="chargeForm.errors.credit_card_id"
                        />
                    </div>
                    <div>
                        <InputLabel
                            for="charge-category"
                            value="Categoria"
                        /><SearchableSelect
                            v-model="chargeForm.category_id"
                            :options="categories"
                            placeholder="Digite para buscar uma categoria..."
                        
                        ><InputError :message="chargeForm.errors.category_id" />
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="charge-type" value="Natureza" /><select
                            id="charge-type"
                            v-model="chargeForm.type"
                            class="mt-2 w-full rounded-xl border-slate-300"
                        >
                            <option value="interest">Juros</option>
                            <option value="late_fee">Multa</option></select
                        ><InputError :message="chargeForm.errors.type" />
                    </div>
                    <div>
                        <InputLabel
                            for="charge-kind"
                            value="Planejamento"
                        /><select
                            id="charge-kind"
                            v-model="chargeForm.planning_type"
                            class="mt-2 w-full rounded-xl border-slate-300"
                        >
                            <option value="ordinary">Cotidiano</option>
                            <option value="extraordinary">
                                Extraordinário
                            </option></select
                        ><InputError
                            :message="chargeForm.errors.planning_type"
                        />
                    </div>
                </div>
                <div>
                    <InputLabel
                        for="charge-description"
                        value="Descrição"
                    /><TextInput
                        id="charge-description"
                        v-model="chargeForm.description"
                        required
                        class="mt-2 w-full"
                    /><InputError :message="chargeForm.errors.description" />
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <InputLabel
                            for="charge-value"
                            value="Valor confirmado"
                        /><TextInput
                            id="charge-value"
                            :model-value="chargeForm.amount"
                            inputmode="decimal"
                            required
                            class="mt-2 w-full"
                            @update:model-value="
                                chargeForm.amount = sanitizeMoneyInput($event)
                            "
                        /><InputError :message="chargeForm.errors.amount" />
                    </div>
                    <div>
                        <InputLabel
                            for="charged-on"
                            value="Data da cobrança"
                        /><TextInput
                            id="charged-on"
                            v-model="chargeForm.charged_on"
                            type="date"
                            :max="today"
                            required
                            class="mt-2 w-full"
                        /><InputError :message="chargeForm.errors.charged_on" />
                    </div>
                    <div>
                        <InputLabel
                            for="charge-due"
                            value="Vencimento"
                        /><TextInput
                            id="charge-due"
                            v-model="chargeForm.due_on"
                            type="date"
                            required
                            class="mt-2 w-full"
                        /><InputError :message="chargeForm.errors.due_on" />
                    </div>
                </div>
                <InputError :message="chargeForm.errors.operation_id" />
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-xl border px-4 py-3 text-sm font-semibold"
                        @click="close"
                    >
                        Cancelar</button
                    ><button
                        :disabled="chargeForm.processing"
                        class="rounded-xl bg-amber-600 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        Confirmar encargo
                    </button>
                </div>
            </form></Modal
        >

        <Modal :show="modal === 'purchase'" max-width="lg" @close="close"
            ><form
                class="flex flex-col gap-4 p-5 sm:p-6"
                @submit.prevent="submitPurchase"
            >
                <h2 class="text-xl font-semibold">Registrar compra</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="purchase-card" value="Cartão" /><select
                            id="purchase-card"
                            v-model="purchaseForm.credit_card_id"
                            required
                            class="mt-2 w-full rounded-xl border-slate-300"
                            @change="refreshFirstDueOn"
                        >
                            <option
                                v-for="card in cards"
                                :key="card.id"
                                :value="card.id"
                            >
                                {{ card.name }}
                            </option></select
                        ><InputError
                            :message="purchaseForm.errors.credit_card_id"
                        />
                    </div>
                    <div>
                        <InputLabel
                            for="purchase-category"
                            value="Categoria"
                        /><SearchableSelect
                            v-model="purchaseForm.category_id"
                            :options="categories"
                            placeholder="Digite para buscar uma categoria..."
                        
                        ><InputError
                            :message="purchaseForm.errors.category_id"
                        />
                    </div>
                </div>
                <div>
                    <InputLabel
                        for="purchase-description"
                        value="Descrição"
                    /><TextInput
                        id="purchase-description"
                        v-model="purchaseForm.description"
                        required
                        class="mt-2 w-full"
                    /><InputError :message="purchaseForm.errors.description" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel
                            for="purchase-value"
                            value="Valor total"
                        /><TextInput
                            id="purchase-value"
                            :model-value="purchaseForm.gross_amount"
                            inputmode="decimal"
                            required
                            class="mt-2 w-full"
                            @update:model-value="
                                purchaseForm.gross_amount =
                                    sanitizeMoneyInput($event)
                            "
                        /><InputError
                            :message="purchaseForm.errors.gross_amount"
                        />
                    </div>
                    <div>
                        <InputLabel for="purchase-kind" value="Tipo" /><select
                            id="purchase-kind"
                            v-model="purchaseForm.planning_type"
                            class="mt-2 w-full rounded-xl border-slate-300"
                        >
                            <option value="ordinary">Cotidiana</option>
                            <option value="extraordinary">
                                Extraordinária
                            </option></select
                        ><InputError
                            :message="purchaseForm.errors.planning_type"
                        />
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel
                            for="purchased-on"
                            value="Data da compra"
                        /><TextInput
                            id="purchased-on"
                            v-model="purchaseForm.purchased_on"
                            type="date"
                            :max="today"
                            required
                            class="mt-2 w-full"
                            @change="refreshFirstDueOn"
                        />
                    </div>
                    <div>
                        <InputLabel for="installments" value="Parcelas totais" /><TextInput id="installments" v-model="purchaseForm.installments_count" type="number" min="1" max="120" required class="mt-2 w-full" />
                    </div>
                    <div>
                        <InputLabel for="paid-installments" value="Parcelas já pagas antes do FinanSys" /><TextInput id="paid-installments" v-model="purchaseForm.paid_installments_count" type="number" min="0" :max="Math.max(0, Number(purchaseForm.installments_count) - 1)" required class="mt-2 w-full" />
                        <p class="mt-1 text-xs text-slate-500">Essas parcelas são contexto anterior e não entram nos indicadores atuais.</p>
                    </div>
                    <div>
                        <InputLabel
                            for="first-due"
                            value="Próximo vencimento em aberto"
                        /><TextInput
                            id="first-due"
                            v-model="purchaseForm.first_due_on"
                            type="date"
                            :min="purchaseForm.purchased_on"
                            required
                            class="mt-2 w-full"
                        />
                        <p class="mt-1 text-xs text-slate-500">
                            Para compra em andamento, informe o vencimento da primeira parcela que ainda falta pagar.
                        </p>
                    </div>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-950">
                    <strong>{{ purchaseInstallmentPreview.remainingCount }} parcela(s) entrarão no FinanSys</strong>
                    <span class="block text-xs text-emerald-800">Saldo considerado: R$ {{ formatMoneyInput(purchaseInstallmentPreview.remaining) }}. As {{ purchaseInstallmentPreview.paid }} anteriores ficam fora dos indicadores.</span>
                </div>
                <InputError
                    v-for="field in [
                        'purchased_on',
                        'installments_count',
                        'paid_installments_count',
                        'first_due_on',
                        'operation_id',
                    ]"
                    :key="field"
                    :message="purchaseForm.errors[field]"
                />
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-xl border px-4 py-3 text-sm font-semibold"
                        @click="close"
                    >
                        Cancelar</button
                    ><button
                        :disabled="purchaseForm.processing"
                        class="rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        Registrar compra
                    </button>
                </div>
            </form></Modal
        >

        <Modal :show="modal === 'payment'" max-width="md" @close="close"
            ><form
                class="flex flex-col gap-4 p-5 sm:p-6"
                @submit.prevent="submitPayment"
            >
                <h2 class="text-xl font-semibold">{{ editingPaymentId ? "Editar pagamento da fatura" : "Pagar fatura" }}</h2>
                <p class="text-sm text-slate-500">
                    O pagamento baixa primeiro as parcelas vencidas e atuais
                    mais antigas. Antecipação futura será um fluxo separado.
                </p>
                <div>
                    <InputLabel for="payment-card" value="Cartão" /><select
                        id="payment-card"
                        v-model="paymentForm.credit_card_id"
                        class="mt-2 w-full rounded-xl border-slate-300"
                        @change="paymentForm.card_charge_ids = []"
                    >
                        <option
                            v-for="card in cards"
                            :key="card.id"
                            :value="card.id"
                        >
                            {{ card.name }} · elegível R$
                            {{ formatMoneyInput(eligibleDebt(card)) }}
                        </option>
                    </select>
                    <p v-if="paymentCard" class="mt-1 text-xs text-slate-500">
                        Dívida total, incluindo meses futuros: R$
                        {{ formatMoneyInput(paymentCard.pending) }}
                    </p>
                    <InputError :message="paymentForm.errors.credit_card_id" />
                </div>
                <div>
                    <InputLabel
                        for="payment-account"
                        value="Conta de pagamento"
                    /><select
                        id="payment-account"
                        v-model="paymentForm.source_account_id"
                        required
                        class="mt-2 w-full rounded-xl border-slate-300"
                    >
                        <option disabled value="">Selecione</option>
                        <option
                            v-for="account in accounts"
                            :key="account.id"
                            :value="account.id"
                        >
                            {{ account.name }} · R$
                            {{ formatMoneyInput(account.balance) }}
                        </option></select
                    ><InputError
                        :message="paymentForm.errors.source_account_id"
                    />
                </div>
                <div>
                    <InputLabel for="payment-value" value="Valor" /><TextInput
                        id="payment-value"
                        :model-value="paymentForm.amount"
                        inputmode="decimal"
                        required
                        class="mt-2 w-full"
                        @update:model-value="
                            paymentForm.amount = sanitizeMoneyInput($event)
                        "
                    /><InputError :message="paymentForm.errors.amount" />
                </div>
                <div
                    v-if="eligibleCharges.length"
                    class="rounded-xl border border-amber-200 bg-amber-50 p-3"
                >
                    <p class="text-sm font-semibold text-amber-950">
                        Encargos priorizados
                    </p>
                    <p class="mt-1 text-xs text-amber-800">
                        Somente os itens selecionados entram antes das parcelas;
                        a prévia abaixo mostra quitação total ou parcial.
                    </p>
                    <label
                        v-for="charge in eligibleCharges"
                        :key="charge.id"
                        class="mt-3 flex cursor-pointer items-center gap-3 text-sm"
                        ><input
                            v-model="paymentForm.card_charge_ids"
                            type="checkbox"
                            :value="charge.id"
                            class="rounded border-amber-300 text-amber-700"
                        /><span
                            class="flex min-w-0 flex-1 justify-between gap-2"
                            ><span class="truncate">{{
                                charge.description
                            }}</span
                            ><span class="shrink-0 font-semibold"
                                >R$
                                {{
                                    formatMoneyInput(charge.pending_amount)
                                }}</span
                            ></span
                        ></label
                    ><InputError
                        :message="paymentForm.errors.card_charge_ids"
                    />
                </div>
                <div
                    v-if="paymentPreview.items.length"
                    class="rounded-xl border border-slate-200 bg-slate-50 p-3"
                >
                    <p class="text-sm font-semibold text-slate-900">
                        Distribuição deste pagamento
                    </p>
                    <div class="mt-2 divide-y divide-slate-200">
                        <div
                            v-for="item in paymentPreview.items"
                            :key="item.key"
                            class="flex items-start justify-between gap-3 py-2 text-xs"
                        >
                            <span class="min-w-0"
                                ><span
                                    class="block truncate font-semibold text-slate-800"
                                    >{{ item.label }}</span
                                ><span class="text-slate-500"
                                    >{{ item.kind }} · vence
                                    {{ date(item.due_on) }} · saldo depois R$
                                    {{ formatMoneyInput(item.after) }}</span
                                ></span
                            ><span
                                class="shrink-0 font-semibold text-emerald-700"
                                >R$ {{ formatMoneyInput(item.allocated) }}</span
                            >
                        </div>
                    </div>
                </div>
                <p
                    v-if="paymentPreview.remainder > 0n"
                    class="rounded-xl bg-rose-50 p-3 text-xs font-semibold text-rose-700"
                >
                    O valor excede em R$
                    {{
                        formatMoneyInput(
                            centsToDecimal(paymentPreview.remainder),
                        )
                    }}
                    as obrigações selecionadas e elegíveis.
                </p>
                <div>
                    <InputLabel for="paid-on" value="Data" /><TextInput
                        id="paid-on"
                        v-model="paymentForm.paid_on"
                        type="date"
                        :max="today"
                        required
                        class="mt-2 w-full"
                        @update:model-value="paymentForm.card_charge_ids = []"
                    /><InputError :message="paymentForm.errors.paid_on" />
                </div>
                <InputError :message="paymentForm.errors.operation_id" />
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-xl border px-4 py-3 text-sm font-semibold"
                        @click="close"
                    >
                        Cancelar</button
                    ><button
                        :disabled="
                            paymentForm.processing || !paymentPreview.valid
                        "
                        class="rounded-xl bg-emerald-700 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        {{ editingPaymentId ? "Salvar alterações" : "Confirmar pagamento" }}
                    </button>
                </div>
            </form></Modal
        >

        <Modal :show="modal === 'advance'" max-width="lg" @close="close"
            ><form
                class="flex flex-col gap-4 p-5 sm:p-6"
                @submit.prevent="submitAdvance"
            >
                <div>
                    <h2 class="text-xl font-semibold">Antecipar parcelas</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Escolha compromissos de meses futuros. O líquido afeta
                        este mês e o bruto selecionado deixa de pesar nos
                        próximos.
                    </p>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <InputLabel for="advance-card" value="Cartão" /><select
                            id="advance-card"
                            v-model="advanceForm.credit_card_id"
                            class="mt-2 w-full rounded-xl border-slate-300"
                            @change="advanceForm.installment_ids = []"
                        >
                            <option
                                v-for="card in cards"
                                :key="card.id"
                                :value="card.id"
                            >
                                {{ card.name }}
                            </option></select
                        ><InputError
                            :message="advanceForm.errors.credit_card_id"
                        />
                    </div>
                    <div>
                        <InputLabel
                            for="advance-account"
                            value="Conta"
                        /><select
                            id="advance-account"
                            v-model="advanceForm.source_account_id"
                            required
                            class="mt-2 w-full rounded-xl border-slate-300"
                        >
                            <option disabled value="">Selecione</option>
                            <option
                                v-for="account in accounts"
                                :key="account.id"
                                :value="account.id"
                            >
                                {{ account.name }} · R$
                                {{ formatMoneyInput(account.balance) }}
                            </option></select
                        ><InputError
                            :message="advanceForm.errors.source_account_id"
                        />
                    </div>
                    <div>
                        <InputLabel for="advanced-on" value="Data" /><TextInput
                            id="advanced-on"
                            v-model="advanceForm.advanced_on"
                            type="date"
                            :max="today"
                            required
                            class="mt-2 w-full"
                            @update:model-value="
                                advanceForm.installment_ids = []
                            "
                        /><InputError
                            :message="advanceForm.errors.advanced_on"
                        />
                    </div>
                </div>
                <div class="rounded-xl border border-slate-200 p-3">
                    <p class="text-sm font-semibold text-slate-900">
                        Parcelas futuras
                    </p>
                    <p
                        v-if="!eligibleAdvanceInstallments.length"
                        class="mt-2 text-xs text-slate-500"
                    >
                        Nenhuma parcela elegível após o mês escolhido.
                    </p>
                    <label
                        v-for="installment in eligibleAdvanceInstallments"
                        :key="installment.id"
                        class="mt-3 flex cursor-pointer items-center gap-3 text-sm"
                        ><input
                            v-model="advanceForm.installment_ids"
                            type="checkbox"
                            :value="installment.id"
                            :disabled="
                                advanceForm.installment_ids.length >= 200 &&
                                !advanceForm.installment_ids
                                    .map(Number)
                                    .includes(installment.id)
                            "
                            class="rounded border-sky-300 text-sky-700"
                        /><span
                            class="flex min-w-0 flex-1 justify-between gap-2"
                            ><span class="truncate"
                                >{{ installment.description }} ·
                                {{ installment.number }} · vence
                                {{ date(installment.due_on) }}</span
                            ><span class="shrink-0 font-semibold"
                                >R$
                                {{
                                    formatMoneyInput(
                                        centsToDecimal(
                                            decimalToCents(
                                                installment.gross_amount,
                                            ) -
                                                decimalToCents(
                                                    installment.paid_amount,
                                                ),
                                        ),
                                    )
                                }}</span
                            ></span
                        ></label
                    ><InputError
                        :message="advanceForm.errors.installment_ids"
                    />
                </div>
                <div>
                    <InputLabel
                        for="advance-discount"
                        value="Desconto confirmado"
                    /><TextInput
                        id="advance-discount"
                        :model-value="advanceForm.discount_amount"
                        inputmode="decimal"
                        required
                        class="mt-2 w-full"
                        @update:model-value="
                            advanceForm.discount_amount =
                                sanitizeMoneyInput($event)
                        "
                    /><InputError
                        :message="advanceForm.errors.discount_amount"
                    /><InputError
                        :message="advanceForm.errors.expected_gross_amount"
                    />
                </div>
                <div
                    v-if="advancePreview.items.length"
                    class="rounded-xl border border-sky-200 bg-sky-50 p-3"
                >
                    <div class="grid grid-cols-3 gap-2 text-xs">
                        <p>
                            Bruto liberado<br /><strong
                                >R$
                                {{
                                    formatMoneyInput(
                                        centsToDecimal(advancePreview.gross),
                                    )
                                }}</strong
                            >
                        </p>
                        <p>
                            Desconto<br /><strong
                                >R$
                                {{
                                    formatMoneyInput(
                                        centsToDecimal(advancePreview.discount),
                                    )
                                }}</strong
                            >
                        </p>
                        <p>
                            Líquido agora<br /><strong
                                >R$
                                {{
                                    formatMoneyInput(
                                        centsToDecimal(advancePreview.net),
                                    )
                                }}</strong
                            >
                        </p>
                    </div>
                    <div class="mt-3 divide-y divide-sky-200">
                        <div
                            v-for="item in advancePreview.items"
                            :key="item.id"
                            class="grid gap-1 py-2 text-xs sm:grid-cols-[1fr_auto]"
                        >
                            <span
                                ><strong>{{ item.label }}</strong
                                ><br /><span class="text-slate-500"
                                    >Vencimento original
                                    {{ date(item.due_on) }}</span
                                ></span
                            ><span class="text-right"
                                >R$ {{ formatMoneyInput(item.gross) }} − R$
                                {{ formatMoneyInput(item.discount) }} =
                                <strong
                                    >R$ {{ formatMoneyInput(item.net) }}</strong
                                ></span
                            >
                        </div>
                    </div>
                </div>
                <p
                    v-if="advancePreview.discount > advancePreview.gross"
                    class="rounded-xl bg-rose-50 p-3 text-xs font-semibold text-rose-700"
                >
                    O desconto não pode ultrapassar o bruto selecionado.
                </p>
                <InputError :message="advanceForm.errors.operation_id" />
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-xl border px-4 py-3 text-sm font-semibold"
                        @click="close"
                    >
                        Cancelar</button
                    ><button
                        :disabled="
                            advanceForm.processing || !advancePreview.valid
                        "
                        class="rounded-xl bg-sky-700 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        Confirmar antecipação
                    </button>
                </div>
            </form></Modal
        >
    </AuthenticatedLayout>
</template>
