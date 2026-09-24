<script setup>
import { computed, ref, watch } from "vue";
import {
    formatMoneyInput,
    normalizeMoneyInput,
    sanitizeMoneyInput,
} from "@/Support/money";

const props = defineProps({
    totalAmount: { type: String, default: "0,00" },
    installmentsCount: { type: [Number, String], default: 1 },
    paidInstallmentsCount: { type: [Number, String], default: 0 },
});

const emit = defineEmits([
    "update:totalAmount",
    "update:installmentsCount",
    "update:paidInstallmentsCount",
]);

const installmentAmount = ref("0,00");
const lastEdited = ref("total");
const syncing = ref(false);

const toCents = (value) => {
    const normalized = normalizeMoneyInput(value || "0");
    const [whole = "0", fraction = ""] = String(normalized).split(".");
    return Number(whole || 0) * 100 + Number(fraction.padEnd(2, "0").slice(0, 2) || 0);
};

const fromCents = (value) => formatMoneyInput((Math.max(0, Math.round(value)) / 100).toFixed(2));

const count = computed(() => Math.max(1, Math.min(120, Number(props.installmentsCount) || 1)));
const paidCount = computed(() =>
    Math.max(0, Math.min(count.value - 1, Number(props.paidInstallmentsCount) || 0)),
);
const remainingCount = computed(() => count.value - paidCount.value);
const firstOpenNumber = computed(() => paidCount.value + 1);
const remainingAmount = computed(() => {
    const total = toCents(props.totalAmount);
    if (count.value <= 0) return 0;
    const base = Math.floor(total / count.value);
    const extra = total - base * count.value;
    let remaining = 0;
    for (let index = paidCount.value; index < count.value; index += 1) {
        remaining += base + (index < extra ? 1 : 0);
    }
    return remaining;
});

function syncInstallmentFromTotal() {
    if (syncing.value) return;
    syncing.value = true;
    const cents = toCents(props.totalAmount);
    installmentAmount.value = fromCents(cents / count.value);
    syncing.value = false;
}

function syncTotalFromInstallment() {
    if (syncing.value) return;
    syncing.value = true;
    const cents = toCents(installmentAmount.value) * count.value;
    emit("update:totalAmount", fromCents(cents));
    syncing.value = false;
}

function onTotalInput(value) {
    lastEdited.value = "total";
    emit("update:totalAmount", sanitizeMoneyInput(value));
}

function onInstallmentInput(value) {
    lastEdited.value = "installment";
    installmentAmount.value = sanitizeMoneyInput(value);
    syncTotalFromInstallment();
}

function onCountInput(event) {
    const value = Math.max(1, Math.min(120, Number(event.target.value) || 1));
    emit("update:installmentsCount", value);
    if (paidCount.value >= value) emit("update:paidInstallmentsCount", Math.max(0, value - 1));
}

function onPaidInput(event) {
    const value = Math.max(0, Math.min(count.value - 1, Number(event.target.value) || 0));
    emit("update:paidInstallmentsCount", value);
}

watch(
    () => [props.totalAmount, props.installmentsCount],
    () => {
        if (lastEdited.value === "total") syncInstallmentFromTotal();
        else syncTotalFromInstallment();
    },
    { immediate: true },
);
</script>

<template>
    <div class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="text-sm font-medium text-slate-700" for="purchase-value">Valor total original</label>
                <input
                    id="purchase-value"
                    :value="totalAmount"
                    inputmode="decimal"
                    required
                    class="mt-2 w-full rounded-xl border-slate-300"
                    placeholder="Ex.: 1.000,00"
                    @input="onTotalInput($event.target.value)"
                />
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700" for="purchase-installment-value">Valor por parcela</label>
                <input
                    id="purchase-installment-value"
                    :value="installmentAmount"
                    inputmode="decimal"
                    class="mt-2 w-full rounded-xl border-slate-300"
                    placeholder="Calculado automaticamente"
                    @input="onInstallmentInput($event.target.value)"
                />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="text-sm font-medium text-slate-700" for="installments">Total de parcelas</label>
                <input
                    id="installments"
                    :value="installmentsCount"
                    type="number"
                    min="1"
                    max="120"
                    required
                    class="mt-2 w-full rounded-xl border-slate-300"
                    @input="onCountInput"
                />
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700" for="paid-installments">Parcelas já pagas</label>
                <input
                    id="paid-installments"
                    :value="paidInstallmentsCount"
                    type="number"
                    min="0"
                    :max="Math.max(0, count - 1)"
                    class="mt-2 w-full rounded-xl border-slate-300"
                    @input="onPaidInput"
                />
            </div>
        </div>

        <div class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
            <div class="flex flex-wrap justify-between gap-2">
                <span>{{ paidCount }} pagas · {{ remainingCount }} restantes</span>
                <strong class="text-slate-900">Saldo a controlar: R$ {{ fromCents(remainingAmount) }}</strong>
            </div>
            <p class="mt-1 text-xs text-slate-500">
                O FinanSys começará na parcela {{ firstOpenNumber }}/{{ count }}. Parcelas anteriores ficam fora dos indicadores atuais.
            </p>
        </div>
    </div>
</template>
