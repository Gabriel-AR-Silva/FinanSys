<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatMoneyInput, normalizeMoneyInput, sanitizeMoneyInput } from '@/Support/money';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    accounts: { type: Array, required: true },
    categories: { type: Array, required: true },
    commitments: { type: Array, required: true },
});
const today = new Date(new Date().getTime() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const asCents = (value) => {
    const [whole, fraction = ''] = String(value ?? '0').replace(',', '.').split('.');
    return BigInt(whole || '0') * 100n + BigInt(fraction.padEnd(2, '0').slice(0, 2) || '0');
};
const formatCents = (cents) => money.format(Number(cents) / 100);
const remaining = (item) => asCents(item.amount) - asCents(item.paid_amount);
const pending = computed(() => props.commitments.filter(item => item.status === 'pending'));
const totalPending = computed(() => pending.value.reduce((total, item) => total + remaining(item), 0n));
const totalBalance = computed(() => props.accounts.reduce((total, account) => total + asCents(account.balance), 0n));
const formatDate = (value) => new Intl.DateTimeFormat('pt-BR', { timeZone: 'UTC' }).format(new Date(`${value}T12:00:00Z`));
const scheduleForm = useForm({
    account_id: props.accounts[0]?.id ?? '',
    category_id: props.categories[0]?.id ?? '',
    description: '', amount: '', due_on: today,
    planning_type: 'ordinary', operation_id: crypto.randomUUID(),
});
const selected = ref(null);
const paymentForm = useForm({ amount: '', paid_on: today, operation_id: crypto.randomUUID() });
const cancelForm = useForm({});
const cancellingId = ref(null);
function schedule() {
    scheduleForm.transform(data => ({ ...data, amount: normalizeMoneyInput(data.amount) }))
        .post(route('expense-commitments.store'), {
            preserveScroll: true,
            onSuccess: () => {
                scheduleForm.reset('description', 'amount');
                scheduleForm.operation_id = crypto.randomUUID();
            },
        });
}
function startPayment(item) {
    selected.value = item;
    paymentForm.clearErrors();
    paymentForm.amount = formatMoneyInput(`${remaining(item) / 100n}.${String(remaining(item) % 100n).padStart(2, '0')}`);
    paymentForm.paid_on = today;
    paymentForm.operation_id = crypto.randomUUID();
}
function pay() {
    paymentForm.transform(data => ({ ...data, amount: normalizeMoneyInput(data.amount) }))
        .post(route('expense-commitments.pay', selected.value.id), {
            preserveScroll: true,
            onSuccess: () => { selected.value = null; paymentForm.operation_id = crypto.randomUUID(); },
        });
}
function cancel(item) {
    if (!confirm(`Cancelar o compromisso “${item.description}”? Pagamentos já registrados serão preservados.`)) return;
    cancellingId.value = item.id;
    cancelForm.post(route('expense-commitments.cancel', item.id), {
        preserveScroll: true,
        onFinish: () => { cancellingId.value = null; },
    });
}
</script>

<template>
    <Head title="Compromissos futuros" />
    <AuthenticatedLayout>
        <div class="space-y-7">
            <header><h1 class="text-2xl font-semibold text-slate-950">Compromissos futuros</h1><p class="mt-2 text-sm text-slate-600">Planeje despesas sem lançar dinheiro que ainda não saiu da conta. O saldo só muda quando você registra um pagamento.</p></header>
            <section class="grid gap-3 sm:grid-cols-3" aria-label="Resumo de compromissos">
                <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-sm text-slate-500">Saldo atual das contas</p><p class="mt-2 text-xl font-semibold">{{ formatCents(totalBalance) }}</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-sm text-slate-500">Comprometido ainda não pago</p><p class="mt-2 text-xl font-semibold">{{ formatCents(totalPending) }}</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-sm text-slate-500">Saldo após esses compromissos</p><p class="mt-2 text-xl font-semibold">{{ formatCents(totalBalance - totalPending) }}</p><p class="mt-1 text-xs text-slate-500">Estimativa: não é o saldo bancário nem considera outros compromissos.</p></div>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5" aria-label="Cadastrar compromisso">
                <h2 class="text-lg font-semibold">Novo compromisso</h2>
                <form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="schedule">
                    <div class="sm:col-span-2"><InputLabel for="commit-description" value="Descrição" /><TextInput id="commit-description" v-model="scheduleForm.description" required maxlength="255" class="mt-1 block w-full" placeholder="Ex.: boleto da moto" /><InputError :message="scheduleForm.errors.description" /></div>
                    <div><InputLabel for="commit-account" value="Conta que vai pagar" /><select id="commit-account" v-model="scheduleForm.account_id" required class="mt-1 block w-full rounded-xl border-slate-300"><option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option></select><InputError :message="scheduleForm.errors.account_id" /></div>
                    <div><InputLabel for="commit-category" value="Categoria de despesa" /><select id="commit-category" v-model="scheduleForm.category_id" required class="mt-1 block w-full rounded-xl border-slate-300"><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select><InputError :message="scheduleForm.errors.category_id" /></div>
                    <div><InputLabel for="commit-amount" value="Valor previsto" /><TextInput id="commit-amount" v-model="scheduleForm.amount" inputmode="decimal" required class="mt-1 block w-full" placeholder="0,00" @input="scheduleForm.amount = sanitizeMoneyInput($event.target.value)" @blur="scheduleForm.amount = formatMoneyInput(scheduleForm.amount)" /><InputError :message="scheduleForm.errors.amount" /></div>
                    <div><InputLabel for="commit-date" value="Vencimento" /><TextInput id="commit-date" v-model="scheduleForm.due_on" type="date" required class="mt-1 block w-full" /><InputError :message="scheduleForm.errors.due_on" /></div>
                    <div><InputLabel for="commit-planning" value="Comportamento no planejamento" /><select id="commit-planning" v-model="scheduleForm.planning_type" required class="mt-1 block w-full rounded-xl border-slate-300"><option value="ordinary">Cotidiano</option><option value="fixed">Fixo</option><option value="extraordinary">Extraordinário</option></select><InputError :message="scheduleForm.errors.planning_type" /></div>
                    <div class="flex items-end"><button type="submit" :disabled="scheduleForm.processing || !accounts.length || !categories.length" class="w-full rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50">{{ scheduleForm.processing ? 'Salvando…' : 'Cadastrar sem alterar saldo' }}</button></div>
                    <InputError :message="scheduleForm.errors.operation_id" class="sm:col-span-2" />
                </form>
            </section>
            <section class="space-y-3" aria-label="Compromissos cadastrados"><h2 class="text-lg font-semibold">Seus compromissos</h2><p v-if="!commitments.length" class="rounded-2xl border border-dashed border-slate-300 p-6 text-sm text-slate-500">Nenhum compromisso cadastrado.</p>
                <article v-for="item in commitments" :key="item.id" class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="flex flex-wrap items-start justify-between gap-4"><div><p class="font-semibold">{{ item.description }}</p><p class="mt-1 text-sm text-slate-500">Vence {{ formatDate(item.due_on) }} · {{ item.status === 'pending' ? 'Pendente' : item.status === 'paid' ? 'Quitado' : 'Cancelado' }}</p><p class="mt-1 text-sm text-slate-600">Total {{ money.format(Number(item.amount)) }} · Pago {{ money.format(Number(item.paid_amount)) }} · Restante {{ formatCents(remaining(item)) }}</p></div><div v-if="item.status === 'pending'" class="flex flex-wrap gap-2"><button type="button" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white" @click="startPayment(item)">Registrar pagamento</button><button type="button" :disabled="cancellingId === item.id" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 disabled:opacity-50" @click="cancel(item)">Cancelar restante</button></div></div>
                    <p v-if="item.payments.length" class="mt-3 text-xs text-slate-500">Pagamentos: {{ item.payments.map(payment => `${formatDate(payment.paid_on)} · ${money.format(Number(payment.amount))}`).join(' | ') }}</p>
                </article>
            </section>
            <section v-if="selected" class="rounded-2xl border-2 border-emerald-500 bg-white p-5" aria-label="Registrar pagamento"><h2 class="text-lg font-semibold">Pagar: {{ selected.description }}</h2><p class="mt-1 text-sm text-slate-600">Restante: {{ formatCents(remaining(selected)) }}. Pagamentos parciais conservam a pendência; valores lançados saem do saldo apenas uma vez.</p><form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="pay"><div><InputLabel for="payment-amount" value="Valor efetivamente pago" /><TextInput id="payment-amount" v-model="paymentForm.amount" required inputmode="decimal" class="mt-1 block w-full" @input="paymentForm.amount = sanitizeMoneyInput($event.target.value)" @blur="paymentForm.amount = formatMoneyInput(paymentForm.amount)" /><InputError :message="paymentForm.errors.amount" /></div><div><InputLabel for="payment-date" value="Data do pagamento" /><TextInput id="payment-date" v-model="paymentForm.paid_on" type="date" :max="today" required class="mt-1 block w-full" /><InputError :message="paymentForm.errors.paid_on" /></div><InputError :message="paymentForm.errors.expense_commitment || paymentForm.errors.operation_id" class="sm:col-span-2" /><div class="flex flex-wrap gap-3 sm:col-span-2"><button type="submit" :disabled="paymentForm.processing" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ paymentForm.processing ? 'Registrando…' : 'Confirmar pagamento' }}</button><button type="button" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold" @click="selected = null">Fechar</button></div></form></section>
        </div>
    </AuthenticatedLayout>
</template>
