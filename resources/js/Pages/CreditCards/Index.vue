<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatMoneyInput, normalizeMoneyInput, sanitizeMoneyInput } from '@/Support/money';
import { Head, useForm } from '@inertiajs/vue3';
import { CalendarDays, CreditCard, Plus, ReceiptText, WalletCards } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({
    cards: { type: Array, required: true },
    categories: { type: Array, required: true },
    accounts: { type: Array, required: true },
    today: { type: String, required: true },
});

const modal = ref(null);
const selectedCardId = ref(props.cards[0]?.id ?? '');
const selectedCard = computed(() => props.cards.find((card) => card.id === Number(selectedCardId.value)));
const cardForm = useForm({ name: '', closing_day: 5, due_day: 12, operation_id: crypto.randomUUID() });
const purchaseForm = useForm({ credit_card_id: selectedCardId.value, category_id: '', description: '', planning_type: 'ordinary', gross_amount: '0,00', purchased_on: props.today, installments_count: 1, first_due_on: props.today, operation_id: crypto.randomUUID() });
const paymentForm = useForm({ credit_card_id: selectedCardId.value, source_account_id: '', amount: '0,00', paid_on: props.today, operation_id: crypto.randomUUID() });

function open(kind, cardId = selectedCardId.value) {
    selectedCardId.value = cardId;
    if (kind === 'purchase') purchaseForm.credit_card_id = cardId;
    if (kind === 'payment') paymentForm.credit_card_id = cardId;
    modal.value = kind;
}

function close() {
    if (![cardForm, purchaseForm, paymentForm].some((form) => form.processing)) modal.value = null;
}

function submitCard() {
    cardForm.post(route('credit-cards.store'), { preserveScroll: true, onSuccess: () => {
        modal.value = null;
        cardForm.defaults({ name: '', closing_day: 5, due_day: 12, operation_id: crypto.randomUUID() });
        cardForm.reset();
    } });
}

function submitPurchase() {
    purchaseForm.transform((data) => ({ ...data, gross_amount: normalizeMoneyInput(data.gross_amount) }))
        .post(route('card-purchases.store'), { preserveScroll: true, onSuccess: () => {
            modal.value = null;
            purchaseForm.defaults({ ...purchaseForm.data(), description: '', gross_amount: '0,00', installments_count: 1, operation_id: crypto.randomUUID() });
            purchaseForm.reset();
        } });
}

function submitPayment() {
    paymentForm.transform((data) => ({ ...data, amount: normalizeMoneyInput(data.amount) }))
        .post(route('card-payments.store'), { preserveScroll: true, onSuccess: () => {
            modal.value = null;
            paymentForm.defaults({ ...paymentForm.data(), amount: '0,00', operation_id: crypto.randomUUID() });
            paymentForm.reset();
        } });
}

const date = (value) => value.split('-').reverse().join('/');
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Cartões" />
        <div class="flex min-w-0 flex-col gap-6">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-emerald-700">CRÉDITO SEM DUPLICIDADE</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl">Cartões e parcelas</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">A compra compromete o mês da parcela; pagar a fatura apenas liquida a obrigação.</p>
                </div>
                <div class="grid grid-cols-1 gap-2 min-[420px]:grid-cols-3">
                    <button class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold" type="button" @click="open('card')"><Plus :size="17" class="mr-1 inline" />Cartão</button>
                    <button :disabled="!cards.length || !categories.length" class="rounded-xl bg-slate-950 px-3 py-2.5 text-sm font-semibold text-white disabled:opacity-40" type="button" @click="open('purchase')"><ReceiptText :size="17" class="mr-1 inline" />Compra</button>
                    <button :disabled="!cards.length || !accounts.length" class="rounded-xl bg-emerald-700 px-3 py-2.5 text-sm font-semibold text-white disabled:opacity-40" type="button" @click="open('payment')"><WalletCards :size="17" class="mr-1 inline" />Pagar</button>
                </div>
            </header>

            <section v-if="!cards.length" class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
                <CreditCard :size="34" class="mx-auto text-slate-400" />
                <h2 class="mt-3 font-semibold text-slate-900">Nenhum cartão por aqui</h2>
                <p class="mt-1 text-sm text-slate-500">Cadastre o primeiro para organizar compras e parcelas.</p>
            </section>

            <section v-else class="grid min-w-0 gap-4 xl:grid-cols-2">
                <article v-for="card in cards" :key="card.id" class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-start justify-between gap-4 bg-slate-950 p-5 text-white">
                        <div class="min-w-0"><p class="truncate text-lg font-semibold">{{ card.name }}</p><p class="mt-1 text-xs text-slate-400">Fecha dia {{ card.closing_day }} · vence dia {{ card.due_day }}</p></div>
                        <div class="text-right"><p class="text-xs text-slate-400">Dívida pendente</p><p class="mt-1 text-lg font-semibold">R$ {{ formatMoneyInput(card.pending) }}</p></div>
                    </div>
                    <div class="flex gap-2 border-b border-slate-100 p-3">
                        <button class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700" type="button" @click="open('purchase', card.id)">Nova compra</button>
                        <button class="rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700" type="button" @click="open('payment', card.id)">Pagar fatura</button>
                    </div>
                    <div v-if="card.purchases.length" class="divide-y divide-slate-100">
                        <details v-for="purchase in card.purchases" :key="purchase.id" class="group p-4">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                                <span class="min-w-0"><span class="block truncate text-sm font-semibold text-slate-900">{{ purchase.description }}</span><span class="block text-xs text-slate-500">{{ purchase.category_name }} · {{ date(purchase.purchased_on) }}</span></span>
                                <span class="shrink-0 text-sm font-semibold">R$ {{ formatMoneyInput(purchase.gross_amount) }}</span>
                            </summary>
                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                <div v-for="installment in purchase.installments" :key="installment.number" class="rounded-xl bg-slate-50 p-3 text-xs">
                                    <div class="flex justify-between gap-2"><span>{{ installment.number }}/{{ purchase.installments_count }} · {{ date(installment.due_on) }}</span><span :class="installment.status === 'paid' ? 'text-emerald-700' : 'text-amber-700'">{{ installment.status === 'paid' ? 'Paga' : 'Pendente' }}</span></div>
                                    <p class="mt-1 font-semibold">R$ {{ formatMoneyInput(installment.paid_amount) }} de R$ {{ formatMoneyInput(installment.gross_amount) }}</p>
                                </div>
                            </div>
                        </details>
                    </div>
                    <p v-else class="p-5 text-sm text-slate-500">Nenhuma compra registrada neste cartão.</p>
                </article>
            </section>
        </div>

        <Modal :show="modal === 'card'" max-width="md" @close="close"><form class="flex flex-col gap-4 p-5 sm:p-6" @submit.prevent="submitCard">
            <h2 class="text-xl font-semibold">Cadastrar cartão</h2>
            <div><InputLabel for="card-name" value="Nome" /><TextInput id="card-name" v-model="cardForm.name" required class="mt-2 w-full" /><InputError :message="cardForm.errors.name" /></div>
            <div class="grid grid-cols-2 gap-3"><div><InputLabel for="closing-day" value="Dia do fechamento" /><TextInput id="closing-day" v-model="cardForm.closing_day" type="number" min="1" max="31" required class="mt-2 w-full" /><InputError :message="cardForm.errors.closing_day" /></div><div><InputLabel for="due-day" value="Dia do vencimento" /><TextInput id="due-day" v-model="cardForm.due_day" type="number" min="1" max="31" required class="mt-2 w-full" /><InputError :message="cardForm.errors.due_day" /></div></div>
            <InputError :message="cardForm.errors.operation_id" /><div class="flex justify-end gap-2"><button type="button" class="rounded-xl border px-4 py-3 text-sm font-semibold" @click="close">Cancelar</button><button :disabled="cardForm.processing" class="rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50">Salvar cartão</button></div>
        </form></Modal>

        <Modal :show="modal === 'purchase'" max-width="lg" @close="close"><form class="flex flex-col gap-4 p-5 sm:p-6" @submit.prevent="submitPurchase">
            <h2 class="text-xl font-semibold">Registrar compra</h2>
            <div class="grid gap-4 sm:grid-cols-2"><div><InputLabel for="purchase-card" value="Cartão" /><select id="purchase-card" v-model="purchaseForm.credit_card_id" required class="mt-2 w-full rounded-xl border-slate-300"><option v-for="card in cards" :key="card.id" :value="card.id">{{ card.name }}</option></select><InputError :message="purchaseForm.errors.credit_card_id" /></div><div><InputLabel for="purchase-category" value="Categoria" /><select id="purchase-category" v-model="purchaseForm.category_id" required class="mt-2 w-full rounded-xl border-slate-300"><option disabled value="">Selecione</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select><InputError :message="purchaseForm.errors.category_id" /></div></div>
            <div><InputLabel for="purchase-description" value="Descrição" /><TextInput id="purchase-description" v-model="purchaseForm.description" required class="mt-2 w-full" /><InputError :message="purchaseForm.errors.description" /></div>
            <div class="grid gap-4 sm:grid-cols-2"><div><InputLabel for="purchase-value" value="Valor total" /><TextInput id="purchase-value" :model-value="purchaseForm.gross_amount" inputmode="decimal" required class="mt-2 w-full" @update:model-value="purchaseForm.gross_amount = sanitizeMoneyInput($event)" /><InputError :message="purchaseForm.errors.gross_amount" /></div><div><InputLabel for="purchase-kind" value="Tipo" /><select id="purchase-kind" v-model="purchaseForm.planning_type" class="mt-2 w-full rounded-xl border-slate-300"><option value="ordinary">Cotidiana</option><option value="extraordinary">Extraordinária</option></select><InputError :message="purchaseForm.errors.planning_type" /></div></div>
            <div class="grid gap-4 sm:grid-cols-3"><div><InputLabel for="purchased-on" value="Data da compra" /><TextInput id="purchased-on" v-model="purchaseForm.purchased_on" type="date" :max="today" required class="mt-2 w-full" /></div><div><InputLabel for="installments" value="Parcelas" /><TextInput id="installments" v-model="purchaseForm.installments_count" type="number" min="1" max="120" required class="mt-2 w-full" /></div><div><InputLabel for="first-due" value="Primeiro vencimento" /><TextInput id="first-due" v-model="purchaseForm.first_due_on" type="date" required class="mt-2 w-full" /></div></div>
            <InputError v-for="field in ['purchased_on','installments_count','first_due_on','operation_id']" :key="field" :message="purchaseForm.errors[field]" /><div class="flex justify-end gap-2"><button type="button" class="rounded-xl border px-4 py-3 text-sm font-semibold" @click="close">Cancelar</button><button :disabled="purchaseForm.processing" class="rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50">Registrar compra</button></div>
        </form></Modal>

        <Modal :show="modal === 'payment'" max-width="md" @close="close"><form class="flex flex-col gap-4 p-5 sm:p-6" @submit.prevent="submitPayment">
            <h2 class="text-xl font-semibold">Pagar fatura</h2><p class="text-sm text-slate-500">O pagamento baixa primeiro as parcelas vencidas e atuais mais antigas. Antecipação futura será um fluxo separado.</p>
            <div><InputLabel for="payment-card" value="Cartão" /><select id="payment-card" v-model="paymentForm.credit_card_id" class="mt-2 w-full rounded-xl border-slate-300"><option v-for="card in cards" :key="card.id" :value="card.id">{{ card.name }} · R$ {{ formatMoneyInput(card.pending) }}</option></select><InputError :message="paymentForm.errors.credit_card_id" /></div>
            <div><InputLabel for="payment-account" value="Conta de pagamento" /><select id="payment-account" v-model="paymentForm.source_account_id" required class="mt-2 w-full rounded-xl border-slate-300"><option disabled value="">Selecione</option><option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }} · R$ {{ formatMoneyInput(account.balance) }}</option></select><InputError :message="paymentForm.errors.source_account_id" /></div>
            <div><InputLabel for="payment-value" value="Valor" /><TextInput id="payment-value" :model-value="paymentForm.amount" inputmode="decimal" required class="mt-2 w-full" @update:model-value="paymentForm.amount = sanitizeMoneyInput($event)" /><InputError :message="paymentForm.errors.amount" /></div>
            <div><InputLabel for="paid-on" value="Data" /><TextInput id="paid-on" v-model="paymentForm.paid_on" type="date" :max="today" required class="mt-2 w-full" /><InputError :message="paymentForm.errors.paid_on" /></div>
            <InputError :message="paymentForm.errors.operation_id" /><div class="flex justify-end gap-2"><button type="button" class="rounded-xl border px-4 py-3 text-sm font-semibold" @click="close">Cancelar</button><button :disabled="paymentForm.processing" class="rounded-xl bg-emerald-700 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50">Confirmar pagamento</button></div>
        </form></Modal>
    </AuthenticatedLayout>
</template>
