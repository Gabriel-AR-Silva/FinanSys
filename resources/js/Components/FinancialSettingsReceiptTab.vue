<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatMoneyInput, normalizeMoneyInput, sanitizeMoneyInput } from '@/Support/money';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    month: { type: String, required: true },
    forecasts: { type: Object, required: true },
    categories: { type: Array, required: true },
    availableReceipts: { type: Array, required: true },
});
const page = usePage();
const accounts = computed(() => page.props.receiptAccounts ?? []);
const editing = ref(null);
const creating = ref(false);
const cancelling = ref(null);
const linking = ref(null);
const receiptMode = ref('new');
const today = new Intl.DateTimeFormat('sv-SE', { timeZone: 'America/Sao_Paulo', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
const defaults = () => ({ category_id: '', amount: '0,00', expected_on: `${props.month}-01`, recurrence_count: 1, edit_scope: 'this', version: 1, operation_id: crypto.randomUUID() });
const createForm = useForm(defaults());
const cancelForm = useForm({ version: 1 });
const linkForm = useForm({ ledger_entry_id: '', forecast_version: 1, operation_id: crypto.randomUUID() });
const entryForm = useForm({ mode: 'new', account_id: '', amount: '0,00', occurred_at: today, forecast_version: 1, operation_id: crypto.randomUUID() });
const displayDate = (date) => date?.split('-').reverse().join('/') ?? '—';
const moneyFor = (amount) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(amount));
const totals = computed(() => (props.forecasts.data ?? []).reduce((total, forecast) => ({
    expected: total.expected + Number(forecast.amount),
    pending: total.pending + Number(forecast.pending),
}), { expected: 0, pending: 0 }));

function resetCreate() {
    editing.value = null;
    createForm.defaults(defaults());
    createForm.reset();
    createForm.clearErrors();
}
function openCreate() {
    if (createForm.isDirty && !window.confirm('Descartar o rascunho não salvo?')) return;
    resetCreate();
    creating.value = true;
}
function openEdit(forecast) {
    if (createForm.isDirty && !window.confirm('Descartar o rascunho não salvo?')) return;
    editing.value = forecast;
    createForm.defaults({ category_id: forecast.category_id, amount: formatMoneyInput(forecast.amount), expected_on: forecast.expected_on, recurrence_count: 1, edit_scope: 'this', version: forecast.version, operation_id: crypto.randomUUID() });
    createForm.reset();
    createForm.clearErrors();
    creating.value = true;
}
function closeCreate() {
    if (!createForm.processing) creating.value = false;
}
function submitCreate() {
    const url = editing.value
        ? route('receipt-forecasts.update', { forecast: editing.value.id, from_settings: 1 })
        : route('receipt-forecasts.store', { from_settings: 1 });
    createForm.transform((data) => ({ ...data, amount: normalizeMoneyInput(data.amount) }))
        .submit(editing.value ? 'put' : 'post', url, {
            preserveScroll: true,
            onSuccess: () => { creating.value = false; resetCreate(); },
        });
}
function openCancel(forecast) {
    cancelling.value = forecast;
    cancelForm.version = forecast.version;
    cancelForm.clearErrors();
}
function submitCancel() {
    cancelForm.put(route('receipt-forecasts.cancel', { forecast: cancelling.value.id, from_settings: 1 }), {
        preserveScroll: true,
        onSuccess: () => { cancelling.value = null; },
    });
}
function openLink(forecast) {
    linking.value = forecast;
    receiptMode.value = accounts.value.length ? 'new' : 'existing';
    linkForm.defaults({ ledger_entry_id: '', forecast_version: forecast.version, operation_id: crypto.randomUUID() });
    linkForm.reset();
    linkForm.clearErrors();
    entryForm.defaults({ mode: 'new', account_id: '', amount: formatMoneyInput(forecast.pending), occurred_at: today, forecast_version: forecast.version, operation_id: crypto.randomUUID() });
    entryForm.reset();
    entryForm.clearErrors();
}
function closeLink() {
    if (!linkForm.processing && !entryForm.processing) linking.value = null;
}
function submitLink() {
    linkForm.post(route('receipt-forecasts.receipts.store', { forecast: linking.value.id, from_settings: 1 }), {
        preserveScroll: true,
        onSuccess: () => { linking.value = null; },
    });
}
function submitEntry() {
    if (Number(normalizeMoneyInput(entryForm.amount)) > Number(linking.value.pending)
        && !window.confirm('Este recebimento ultrapassa o valor ainda previsto. Confirmar mesmo assim?')) return;
    entryForm.transform((data) => ({ ...data, amount: normalizeMoneyInput(data.amount) }))
        .post(route('receipt-forecasts.receipts.store', { forecast: linking.value.id, from_settings: 1 }), {
            preserveScroll: true,
            onSuccess: () => { linking.value = null; },
        });
}
</script>

<template>
    <div class="space-y-4" aria-labelledby="receipts-heading">
        <section class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
            <div>
                <h2 id="receipts-heading" class="font-semibold text-slate-950">Recebimentos previstos</h2>
                <p class="mt-1 text-sm leading-5 text-slate-500">Uma previsão não muda seu saldo. Somente a receita efetivamente recebida entra na conta.</p>
                <p class="mt-2 text-xs text-slate-500">Nesta página: {{ moneyFor(totals.expected) }} previstos · {{ moneyFor(totals.pending) }} ainda faltam.</p>
            </div>
            <button type="button" :disabled="!categories.length" class="rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50" @click="openCreate">Novo recebimento previsto</button>
        </section>
        <p v-if="!categories.length" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Crie uma categoria de receita em <Link :href="route('categories.index')" class="font-semibold underline">Categorias</Link> para registrar uma previsão.</p>
        <p v-if="!forecasts.data.length" class="rounded-xl border border-dashed border-slate-300 p-5 text-sm text-slate-500">Nenhuma previsão neste mês.</p>
        <ul v-else class="space-y-3" aria-label="Previsões deste mês">
            <li v-for="forecast in forecasts.data" :key="forecast.id" class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                    <div>
                        <h3 class="font-semibold text-slate-950">{{ forecast.category_name }}</h3>
                        <p class="mt-1 text-sm text-slate-500">Previsto para {{ displayDate(forecast.expected_on) }}</p>
                        <span class="mt-2 inline-block rounded-lg px-2 py-1 text-xs font-semibold" :class="forecast.status === 'cancelled' ? 'bg-slate-100 text-slate-600' : forecast.status === 'fulfilled' ? 'bg-emerald-100 text-emerald-900' : forecast.is_overdue ? 'bg-amber-50 text-amber-900' : 'bg-sky-50 text-sky-900'">{{ forecast.status === 'cancelled' ? 'Cancelado' : forecast.status === 'fulfilled' ? 'Recebido' : forecast.is_overdue ? 'Atrasado' : 'Pendente' }}</span>
                    </div>
                    <div v-if="forecast.status === 'expected'" class="flex flex-wrap gap-2">
                        <button type="button" class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white" @click="openLink(forecast)">Registrar recebimento</button>
                        <button type="button" class="rounded-lg px-3 py-2 text-sm font-semibold text-emerald-700" @click="openEdit(forecast)">Editar / remarcar</button>
                        <button type="button" class="rounded-lg px-3 py-2 text-sm font-semibold text-rose-700" @click="openCancel(forecast)">Cancelar</button>
                    </div>
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-2 border-t border-slate-100 pt-4 sm:grid-cols-4">
                    <div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">Previsto</dt><dd class="mt-1 text-sm font-semibold">R$ {{ formatMoneyInput(forecast.amount) }}</dd></div>
                    <div class="rounded-xl bg-emerald-50 p-3"><dt class="text-xs text-emerald-700">Recebido</dt><dd class="mt-1 text-sm font-semibold">R$ {{ formatMoneyInput(forecast.received) }}</dd></div>
                    <div class="rounded-xl bg-amber-50 p-3"><dt class="text-xs text-amber-700">Ainda falta</dt><dd class="mt-1 text-sm font-semibold">R$ {{ formatMoneyInput(forecast.pending) }}</dd></div>
                    <div class="rounded-xl bg-sky-50 p-3"><dt class="text-xs text-sky-700">Excedente</dt><dd class="mt-1 text-sm font-semibold">R$ {{ formatMoneyInput(forecast.excess) }}</dd></div>
                </dl>
                <ul v-if="forecast.receipts.length" class="mt-3 space-y-1 text-sm text-slate-600" aria-label="Receitas vinculadas"><li v-for="receipt in forecast.receipts" :key="receipt.id">{{ displayDate(receipt.occurred_at) }} · {{ receipt.category_name }} · R$ {{ formatMoneyInput(receipt.amount) }}</li></ul>
            </li>
        </ul>
        <nav v-if="forecasts.last_page > 1" aria-label="Páginas de previsões" class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <Link v-if="forecasts.prev_page_url" :href="forecasts.prev_page_url" class="rounded-lg border border-slate-300 px-3 py-2">Anterior</Link>
            <span>Página {{ forecasts.current_page }} de {{ forecasts.last_page }}</span>
            <Link v-if="forecasts.next_page_url" :href="forecasts.next_page_url" class="rounded-lg border border-slate-300 px-3 py-2">Próxima</Link>
        </nav>
    </div>

    <Modal :show="creating" max-width="md" :closeable="!createForm.processing" @close="closeCreate">
        <form class="space-y-4 p-5 sm:p-6" @submit.prevent="submitCreate">
            <h2 class="text-xl font-semibold">{{ editing ? 'Editar previsão' : 'Novo recebimento previsto' }}</h2>
            <p class="text-sm text-slate-500">Registrar uma previsão não cria receita na conta.</p>
            <div><InputLabel for="tab-forecast-category" value="Categoria de receita" /><select id="tab-forecast-category" v-model="createForm.category_id" required class="mt-1 w-full rounded-xl border-slate-300"><option value="" disabled>Selecione</option><option v-if="editing && !categories.some(category => category.id === editing.category_id)" :value="editing.category_id">{{ editing.category_name }} (inativa)</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select><InputError :message="createForm.errors.category_id" /></div>
            <div><InputLabel for="tab-forecast-amount" value="Valor previsto (R$)" /><TextInput id="tab-forecast-amount" :model-value="createForm.amount" required inputmode="decimal" class="mt-1 w-full" @update:model-value="createForm.amount = sanitizeMoneyInput($event)" /><InputError :message="createForm.errors.amount" /></div>
            <div><InputLabel for="tab-forecast-date" value="Data prevista" /><TextInput id="tab-forecast-date" v-model="createForm.expected_on" type="date" min="1000-01-01" max="9999-12-31" required class="mt-1 w-full" /><InputError :message="createForm.errors.expected_on" /></div>
            <div v-if="!editing"><InputLabel for="tab-forecast-repeat" value="Repetição mensal" /><select id="tab-forecast-repeat" v-model.number="createForm.recurrence_count" class="mt-1 w-full rounded-xl border-slate-300"><option :value="1">Somente esta vez</option><option :value="3">3 meses</option><option :value="6">6 meses</option><option :value="12">12 meses</option></select><InputError :message="createForm.errors.recurrence_count" /></div>
            <div v-if="editing?.is_recurring"><InputLabel for="tab-forecast-scope" value="Aplicar alteração" /><select id="tab-forecast-scope" v-model="createForm.edit_scope" class="mt-1 w-full rounded-xl border-slate-300"><option value="this">Somente nesta ocorrência</option><option value="future" :disabled="editing.has_receipts">Nesta e nas próximas sem recebimento</option></select><InputError :message="createForm.errors.edit_scope" /></div>
            <InputError v-for="(error, field) in createForm.errors" :key="field" :message="['category_id','amount','expected_on','recurrence_count','edit_scope'].includes(field) ? null : error" />
            <div class="flex justify-end gap-2"><button type="button" :disabled="createForm.processing" class="rounded-xl border border-slate-300 px-4 py-2" @click="closeCreate">Voltar</button><button type="submit" :disabled="createForm.processing" class="rounded-xl bg-slate-950 px-4 py-2 font-semibold text-white disabled:opacity-50">Salvar previsão</button></div>
        </form>
    </Modal>

    <Modal :show="cancelling !== null" max-width="md" :closeable="!cancelForm.processing" @close="!cancelForm.processing && (cancelling = null)">
        <form class="space-y-4 p-5 sm:p-6" @submit.prevent="submitCancel"><h2 class="text-lg font-semibold">Cancelar previsão?</h2><p class="text-sm text-slate-500">{{ cancelling?.category_name }} continuará no histórico. O saldo não muda.</p><InputError v-for="(error, field) in cancelForm.errors" :key="field" :message="error" /><div class="flex justify-end gap-2"><button type="button" class="rounded-xl border px-4 py-2" @click="cancelling = null">Voltar</button><button type="submit" :disabled="cancelForm.processing" class="rounded-xl bg-rose-700 px-4 py-2 font-semibold text-white">Cancelar previsão</button></div></form>
    </Modal>

    <Modal :show="linking !== null" max-width="md" :closeable="!linkForm.processing && !entryForm.processing" @close="closeLink">
        <div class="space-y-4 p-5 sm:p-6">
            <h2 class="text-lg font-semibold">Registrar recebimento de {{ linking?.category_name }}</h2>
            <p class="text-sm text-slate-500">Previsto: R$ {{ formatMoneyInput(linking?.amount ?? 0) }} · ainda falta: R$ {{ formatMoneyInput(linking?.pending ?? 0) }}. Só a entrada real altera a conta.</p>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2" role="group" aria-label="Como registrar o recebimento">
                <button type="button" :aria-pressed="receiptMode === 'new'" class="rounded-xl border px-3 py-2 text-sm font-semibold" :class="receiptMode === 'new' ? 'border-emerald-700 bg-emerald-50 text-emerald-900' : 'border-slate-300'" @click="receiptMode = 'new'">Registrar entrada agora</button>
                <button type="button" :aria-pressed="receiptMode === 'existing'" class="rounded-xl border px-3 py-2 text-sm font-semibold" :class="receiptMode === 'existing' ? 'border-emerald-700 bg-emerald-50 text-emerald-900' : 'border-slate-300'" @click="receiptMode = 'existing'">Vincular receita existente</button>
            </div>
            <form v-if="receiptMode === 'new'" class="space-y-4" @submit.prevent="submitEntry">
                <p v-if="!accounts.length" class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Cadastre uma conta em <Link :href="route('accounts.index')" class="underline">Contas</Link> antes de registrar a entrada.</p>
                <div><InputLabel for="tab-receipt-account" value="Conta onde o dinheiro entrou" /><select id="tab-receipt-account" v-model="entryForm.account_id" class="mt-1 w-full rounded-xl border-slate-300" required><option value="" disabled>Selecione</option><option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option></select><InputError :message="entryForm.errors.account_id" /></div>
                <div><InputLabel for="tab-receipt-amount" value="Valor que realmente entrou (R$)" /><TextInput id="tab-receipt-amount" :model-value="entryForm.amount" inputmode="decimal" required class="mt-1 w-full" @update:model-value="entryForm.amount = sanitizeMoneyInput($event)" /><InputError :message="entryForm.errors.amount" /></div>
                <div><InputLabel for="tab-receipt-date" value="Data real do recebimento" /><TextInput id="tab-receipt-date" v-model="entryForm.occurred_at" type="date" :max="today" required class="mt-1 w-full" /><InputError :message="entryForm.errors.occurred_at" /></div>
                <InputError v-for="(error, field) in entryForm.errors" :key="field" :message="['account_id','amount','occurred_at'].includes(field) ? null : error" />
                <p class="text-xs leading-5 text-slate-500">O FinanSys cria a receita na conta e vincula à previsão numa única operação. Não registre a mesma entrada novamente em Lançamentos.</p>
                <div class="flex justify-end gap-2"><button type="button" :disabled="entryForm.processing" class="rounded-xl border px-4 py-2" @click="closeLink">Voltar</button><button type="submit" :disabled="entryForm.processing || !accounts.length" class="rounded-xl bg-emerald-700 px-4 py-2 font-semibold text-white disabled:opacity-50">Confirmar recebimento</button></div>
            </form>
            <form v-else class="space-y-4" @submit.prevent="submitLink">
                <p class="text-sm text-slate-500">Vincular uma receita já lançada não altera o saldo novamente.</p>
                <div v-if="availableReceipts.length"><InputLabel for="tab-available-receipt" value="Receita disponível" /><select id="tab-available-receipt" v-model="linkForm.ledger_entry_id" required class="mt-1 w-full rounded-xl border-slate-300"><option value="" disabled>Selecione</option><option v-for="receipt in availableReceipts" :key="receipt.id" :value="receipt.id">{{ displayDate(receipt.occurred_at) }} · R$ {{ formatMoneyInput(receipt.amount) }} · {{ receipt.category_name }} · {{ receipt.account_name }}</option></select><InputError :message="linkForm.errors.ledger_entry_id" /></div>
                <p v-else class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Não há receita livre para vincular. Use “Registrar entrada agora” se o dinheiro já entrou.</p>
                <InputError v-for="(error, field) in linkForm.errors" :key="field" :message="field === 'ledger_entry_id' ? null : error" />
                <div class="flex justify-end gap-2"><button type="button" class="rounded-xl border px-4 py-2" @click="closeLink">Voltar</button><button type="submit" :disabled="linkForm.processing || !availableReceipts.length" class="rounded-xl bg-emerald-700 px-4 py-2 font-semibold text-white disabled:opacity-50">Confirmar vínculo</button></div>
            </form>
        </div>
    </Modal>
</template>
