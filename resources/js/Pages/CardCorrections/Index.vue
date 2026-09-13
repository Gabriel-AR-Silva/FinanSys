<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CircleDollarSign, RotateCcw } from '@lucide/vue';
import { computed, watch } from 'vue';

const props = defineProps({
    cards: { type: Array, required: true },
    purchases: { type: Array, required: true },
    credits: { type: Array, required: true },
    obligations: { type: Array, required: true },
    today: { type: String, required: true },
});

const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const money = (value) => currency.format(Number(value));
const uuid = () => crypto.randomUUID();

const reversal = useForm({
    card_purchase_id: '',
    reversed_on: props.today,
    reason: '',
    operation_id: uuid(),
});

const allocation = useForm({
    card_credit_id: '',
    target_type: 'installment',
    target_id: '',
    amount: '',
    applied_on: props.today,
    operation_id: uuid(),
});

const selectedCredit = computed(() => props.credits.find((credit) => credit.id === Number(allocation.card_credit_id)) ?? null);
const compatibleObligations = computed(() => selectedCredit.value
    ? props.obligations.filter((obligation) => obligation.credit_card_id === selectedCredit.value.credit_card_id)
    : []);

watch(() => allocation.card_credit_id, () => {
    allocation.target_id = '';
    allocation.amount = '';
});

watch(() => allocation.target_id, () => {
    const target = compatibleObligations.value.find((obligation) => obligation.id === Number(allocation.target_id) && obligation.type === allocation.target_type);
    if (target && selectedCredit.value) {
        allocation.amount = String(Math.min(Number(target.remaining_amount), Number(selectedCredit.value.remaining_amount)).toFixed(2));
    }
});

const submitReversal = () => {
    reversal.post(route('card-purchase-reversals.store'), {
        preserveScroll: true,
        onSuccess: () => {
            reversal.reset('card_purchase_id', 'reason');
            reversal.reversed_on = props.today;
            reversal.operation_id = uuid();
        },
    });
};

const submitAllocation = () => {
    allocation.post(route('card-credit-allocations.store'), {
        preserveScroll: true,
        onSuccess: () => {
            allocation.reset('card_credit_id', 'target_id', 'amount');
            allocation.target_type = 'installment';
            allocation.applied_on = props.today;
            allocation.operation_id = uuid();
        },
    });
};
</script>

<template>
    <Head title="Correções de cartão" />
    <AuthenticatedLayout>
        <div class="mx-auto flex w-full max-w-5xl flex-col gap-6">
            <header class="flex flex-col gap-3">
                <Link :href="route('credit-cards.index')" class="inline-flex items-center gap-2 self-start text-sm font-medium text-emerald-700"><ArrowLeft :size="16" />Voltar aos cartões</Link>
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl">Correções de cartão</h1>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">Estorne compras e use créditos de forma explícita. Crédito de cartão reduz obrigações do cartão, mas não vira renda nem entrada na conta.</p>
                </div>
            </header>

            <div class="grid gap-5 lg:grid-cols-2">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start gap-3"><span class="rounded-xl bg-rose-50 p-2 text-rose-700"><RotateCcw :size="19" /></span><div><h2 class="font-semibold text-slate-950">Estornar compra</h2><p class="mt-1 text-sm leading-5 text-slate-500">A parte pendente é cancelada. Valores efetivamente pagos viram crédito do cartão.</p></div></div>
                    <form class="mt-5 flex flex-col gap-4" @submit.prevent="submitReversal">
                        <div><label for="reversal-purchase" class="text-sm font-medium text-slate-700">Compra</label><select id="reversal-purchase" v-model="reversal.card_purchase_id" required class="mt-1 w-full rounded-xl border-slate-300 text-sm"><option value="" disabled>Escolha uma compra</option><option v-for="purchase in purchases" :key="purchase.id" :value="purchase.id">{{ purchase.card_name }} · {{ purchase.description }} · {{ money(purchase.gross_amount) }} · {{ purchase.purchased_on }}</option></select><p v-if="reversal.errors.card_purchase_id" class="mt-1 text-xs text-rose-600">{{ reversal.errors.card_purchase_id }}</p></div>
                        <div><label for="reversal-date" class="text-sm font-medium text-slate-700">Data real do estorno</label><input id="reversal-date" v-model="reversal.reversed_on" :max="today" required type="date" class="mt-1 w-full rounded-xl border-slate-300 text-sm" /><p v-if="reversal.errors.reversed_on" class="mt-1 text-xs text-rose-600">{{ reversal.errors.reversed_on }}</p></div>
                        <div><label for="reversal-reason" class="text-sm font-medium text-slate-700">Motivo <span class="font-normal text-slate-400">(opcional)</span></label><input id="reversal-reason" v-model="reversal.reason" maxlength="255" type="text" class="mt-1 w-full rounded-xl border-slate-300 text-sm" placeholder="Ex.: compra cancelada pela loja" /></div>
                        <button type="submit" :disabled="reversal.processing || !reversal.card_purchase_id" class="min-h-11 rounded-xl bg-rose-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-600 disabled:cursor-not-allowed disabled:opacity-50">{{ reversal.processing ? 'Estornando…' : 'Confirmar estorno' }}</button>
                    </form>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start gap-3"><span class="rounded-xl bg-emerald-50 p-2 text-emerald-700"><CircleDollarSign :size="19" /></span><div><h2 class="font-semibold text-slate-950">Aplicar crédito</h2><p class="mt-1 text-sm leading-5 text-slate-500">Escolha exatamente qual obrigação será abatida. Nenhuma aplicação acontece automaticamente.</p></div></div>
                    <form class="mt-5 flex flex-col gap-4" @submit.prevent="submitAllocation">
                        <div><label for="credit" class="text-sm font-medium text-slate-700">Crédito disponível</label><select id="credit" v-model="allocation.card_credit_id" required class="mt-1 w-full rounded-xl border-slate-300 text-sm"><option value="" disabled>Escolha um crédito</option><option v-for="credit in credits" :key="credit.id" :value="credit.id">{{ credit.card_name }} · disponível {{ money(credit.remaining_amount) }} · {{ credit.credited_on }}</option></select><p v-if="allocation.errors.card_credit_id" class="mt-1 text-xs text-rose-600">{{ allocation.errors.card_credit_id }}</p></div>
                        <div><label for="target-type" class="text-sm font-medium text-slate-700">Tipo de obrigação</label><select id="target-type" v-model="allocation.target_type" class="mt-1 w-full rounded-xl border-slate-300 text-sm" @change="allocation.target_id = ''"><option value="installment">Parcela</option><option value="charge">Juro/multa/encargo</option></select></div>
                        <div><label for="target" class="text-sm font-medium text-slate-700">Obrigação</label><select id="target" v-model="allocation.target_id" required :disabled="!selectedCredit" class="mt-1 w-full rounded-xl border-slate-300 text-sm disabled:bg-slate-100"><option value="" disabled>Escolha uma obrigação</option><option v-for="obligation in compatibleObligations.filter((item) => item.type === allocation.target_type)" :key="`${obligation.type}-${obligation.id}`" :value="obligation.id">{{ obligation.label }} · saldo {{ money(obligation.remaining_amount) }} · vence {{ obligation.due_on }}</option></select><p v-if="allocation.errors.target_id" class="mt-1 text-xs text-rose-600">{{ allocation.errors.target_id }}</p></div>
                        <div class="grid gap-4 sm:grid-cols-2"><div><label for="credit-amount" class="text-sm font-medium text-slate-700">Valor</label><input id="credit-amount" v-model="allocation.amount" required inputmode="decimal" type="number" min="0.01" step="0.01" class="mt-1 w-full rounded-xl border-slate-300 text-sm" /></div><div><label for="credit-date" class="text-sm font-medium text-slate-700">Data da aplicação</label><input id="credit-date" v-model="allocation.applied_on" :max="today" required type="date" class="mt-1 w-full rounded-xl border-slate-300 text-sm" /></div></div>
                        <p v-if="allocation.errors.amount || allocation.errors.applied_on" class="text-xs text-rose-600">{{ allocation.errors.amount || allocation.errors.applied_on }}</p>
                        <button type="submit" :disabled="allocation.processing || !allocation.target_id" class="min-h-11 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50">{{ allocation.processing ? 'Aplicando…' : 'Aplicar crédito' }}</button>
                    </form>
                </section>
            </div>

            <section v-if="!purchases.length && !credits.length" class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Não há compras ativas nem créditos disponíveis para corrigir.</section>
        </div>
    </AuthenticatedLayout>
</template>
