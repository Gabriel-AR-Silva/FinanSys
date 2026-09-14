<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatMoneyInput, normalizeMoneyInput, sanitizeMoneyInput } from '@/Support/money';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CircleCheck, Link2, Plus } from '@lucide/vue';
import { onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    month: { type: String, required: true },
    forecasts: { type: Object, required: true },
    categories: { type: Array, required: true },
    availableReceipts: { type: Array, required: true },
});

const selectedMonth = ref(props.month);
const changingMonth = ref(false);
const createOpen = ref(false);
const editing = ref(null);
const cancelling = ref(null);
const linking = ref(null);
const createForm = useForm({ category_id: '', amount: '0,00', expected_on: `${props.month}-01`, recurrence_count: 1, edit_scope: 'this', operation_id: crypto.randomUUID(), version: 1 });
const cancelForm = useForm({ version: 1 });
const linkForm = useForm({ ledger_entry_id: '', forecast_version: 1, operation_id: crypto.randomUUID() });
watch(() => props.month, (month) => { selectedMonth.value = month; });
const displayDate = (date) => date.split('-').reverse().join('/');
const removeNavigationGuard = router.on('before', (event) => {
    if (event.detail.visit.method !== 'get' || !createForm.isDirty) return;
    if (!window.confirm('Há uma previsão não salva. Deseja sair e descartar o rascunho?')) {
        selectedMonth.value = props.month;
        changingMonth.value = false;
        return false;
    }
});
onUnmounted(removeNavigationGuard);

function discardDraft() {
    if (createForm.processing || !window.confirm('Confira a listagem antes de enviar novamente: uma tentativa anterior pode ter sido salva. Descartar este rascunho e iniciar outra previsão?')) return;
    editing.value = null;
    createForm.defaults({ category_id: '', amount: '0,00', expected_on: `${props.month}-01`, recurrence_count: 1, edit_scope: 'this', operation_id: crypto.randomUUID(), version: 1 });
    createForm.reset();
    createForm.clearErrors();
}

function changeMonth(event) {
    const month = event.target.value;
    if (!month || month === props.month) {
        selectedMonth.value = props.month;
        event.target.value = props.month;
        return;
    }
    changingMonth.value = true;
    router.get(route('receipt-forecasts.index'), { month }, {
        preserveState: false,
        onFinish: () => { changingMonth.value = false; selectedMonth.value = props.month; },
    });
}

function openCreate() {
    if (editing.value) {
        if (createForm.isDirty && !window.confirm('Descartar a edição não salva e iniciar uma nova previsão?')) return;
        editing.value = null;
        createForm.defaults({ category_id: '', amount: '0,00', expected_on: `${props.month}-01`, recurrence_count: 1, edit_scope: 'this', operation_id: crypto.randomUUID(), version: 1 });
        createForm.reset();
        createForm.clearErrors();
    }
    if (!createForm.isDirty) createForm.expected_on = `${props.month}-01`;
    createOpen.value = true;
}

function openEdit(forecast) {
    if (createForm.isDirty && !window.confirm('Descartar o rascunho não salvo para editar esta previsão?')) return;
    editing.value = forecast;
    createForm.defaults({ category_id: forecast.category_id, amount: formatMoneyInput(forecast.amount), expected_on: forecast.expected_on, recurrence_count: 1, edit_scope: 'this', operation_id: crypto.randomUUID(), version: forecast.version });
    createForm.reset();
    createForm.clearErrors();
    createOpen.value = true;
}

function closeCreate() {
    if (createForm.processing) return;
    createOpen.value = false;
}

function submit() {
    const method = editing.value ? 'put' : 'post';
    const url = editing.value ? route('receipt-forecasts.update', editing.value.id) : route('receipt-forecasts.store');
    createForm.transform((data) => ({ ...data, amount: normalizeMoneyInput(data.amount) })).submit(method, url, {
        preserveScroll: true,
        onSuccess: () => {
            createOpen.value = false;
            editing.value = null;
            createForm.defaults({ category_id: '', amount: '0,00', expected_on: `${props.month}-01`, recurrence_count: 1, edit_scope: 'this', operation_id: crypto.randomUUID(), version: 1 });
            createForm.reset();
        },
    });
}

function openCancellation(forecast) {
    cancelForm.clearErrors();
    cancelForm.version = forecast.version;
    cancelling.value = forecast;
}

function closeCancellation() {
    if (!cancelForm.processing) cancelling.value = null;
}

function submitCancellation() {
    cancelForm.put(route('receipt-forecasts.cancel', cancelling.value.id), {
        preserveScroll: true,
        onSuccess: () => { cancelling.value = null; },
    });
}

function openLink(forecast) {
    linking.value = forecast;
    linkForm.defaults({ ledger_entry_id: '', forecast_version: forecast.version, operation_id: crypto.randomUUID() });
    linkForm.reset();
    linkForm.clearErrors();
}

function closeLink() {
    if (!linkForm.processing) linking.value = null;
}

function submitLink() {
    linkForm.post(route('receipt-forecasts.receipts.store', linking.value.id), {
        preserveScroll: true,
        onSuccess: () => { linking.value = null; },
    });
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Recebimentos previstos" />
        <div class="mx-auto flex w-full min-w-0 max-w-3xl flex-col gap-5">
            <header class="flex flex-col gap-3">
                <Link :href="route('financial-settings.edit', { month })" class="self-start rounded-lg py-2 text-sm font-medium text-emerald-700 focus-visible:ring-2 focus-visible:ring-emerald-500">← Configuração financeira</Link>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl">Recebimentos previstos</h1>
                <p class="text-sm leading-6 text-slate-500">Anote o que espera receber. Previsão não é dinheiro disponível e não altera o saldo das suas contas.</p>
            </header>

            <section class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:flex-row sm:items-end sm:justify-between" aria-label="Filtro de recebimentos">
                <div class="flex min-w-0 flex-col gap-2">
                    <InputLabel for="forecast-month" value="Mês previsto" />
                    <input id="forecast-month" v-model="selectedMonth" type="month" min="1000-01" max="9999-12" required :disabled="changingMonth" class="block w-full min-w-0 rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 sm:w-48" @change="changeMonth" />
                    <InputError :message="$page.props.errors.month" />
                </div>
                <button type="button" :disabled="!categories.length || changingMonth" class="flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800 focus-visible:ring-2 focus-visible:ring-emerald-500 disabled:opacity-50" @click="openCreate"><Plus :size="18" />Novo recebimento previsto</button>
            </section>

            <p v-if="!categories.length" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">Você precisa de uma categoria de receita ativa para criar uma previsão. <Link :href="route('categories.index')" class="inline-block rounded px-1 py-2 font-semibold underline focus-visible:ring-2 focus-visible:ring-amber-600">Gerenciar categorias</Link></p>
            <p v-if="!forecasts.data.length" class="rounded-2xl border border-dashed border-slate-300 p-6 text-sm leading-6 text-slate-500">Nenhum recebimento previsto neste mês. Quando tiver uma entrada em vista, registre por aqui. 🤝</p>
            <ul v-else class="flex min-w-0 flex-col gap-3" aria-label="Recebimentos previstos no mês">
                <li v-for="forecast in forecasts.data" :key="forecast.id" class="flex min-w-0 flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
                    <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex min-w-0 flex-col gap-2">
                            <h2 class="break-words font-semibold text-slate-950">{{ forecast.category_name }}</h2>
                            <p class="text-sm text-slate-500">Previsto para {{ displayDate(forecast.expected_on) }}</p>
                            <span class="self-start rounded-lg px-2 py-1 text-xs font-medium" :class="forecast.status === 'cancelled' ? 'bg-slate-100 text-slate-600' : forecast.status === 'fulfilled' ? 'bg-emerald-100 text-emerald-900' : forecast.is_overdue ? 'bg-amber-50 text-amber-800' : 'bg-sky-50 text-sky-800'">{{ forecast.status === 'cancelled' ? 'Cancelado' : forecast.status === 'fulfilled' ? 'Recebido' : forecast.is_overdue ? 'Data prevista passou' : 'Pendente' }}</span>
                        </div>
                        <div v-if="forecast.status === 'expected'" class="flex flex-wrap gap-2 sm:justify-end">
                            <button type="button" class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-emerald-700 px-3 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:ring-2 focus-visible:ring-emerald-500" @click="openLink(forecast)"><Link2 :size="17" />Vincular receita</button>
                            <button type="button" :title="forecast.has_receipts ? 'Remarcar somente o valor que ainda falta' : 'Editar ou remarcar previsão'" class="min-h-11 rounded-lg px-3 py-2.5 text-sm font-medium text-emerald-700 hover:bg-emerald-50 focus-visible:ring-2 focus-visible:ring-emerald-500" @click="openEdit(forecast)">Editar / remarcar</button>
                            <button type="button" class="min-h-11 rounded-lg px-3 py-2.5 text-sm font-medium text-rose-700 hover:bg-rose-50 focus-visible:ring-2 focus-visible:ring-rose-500" @click="openCancellation(forecast)">Cancelar</button>
                        </div>
                    </div>
                    <dl class="grid min-w-0 grid-cols-2 gap-2 border-t border-slate-100 pt-4 sm:grid-cols-4">
                        <div class="min-w-0 rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">Previsto</dt><dd class="break-words pt-1 text-sm font-semibold tabular-nums text-slate-950">R$ {{ formatMoneyInput(forecast.amount) }}</dd></div>
                        <div class="min-w-0 rounded-xl bg-emerald-50 p-3"><dt class="text-xs text-emerald-700">Recebido</dt><dd class="break-words pt-1 text-sm font-semibold tabular-nums text-emerald-900">R$ {{ formatMoneyInput(forecast.received) }}</dd></div>
                        <div class="min-w-0 rounded-xl bg-amber-50 p-3"><dt class="text-xs text-amber-700">Ainda falta</dt><dd class="break-words pt-1 text-sm font-semibold tabular-nums text-amber-900">R$ {{ formatMoneyInput(forecast.pending) }}</dd></div>
                        <div class="min-w-0 rounded-xl bg-sky-50 p-3"><dt class="text-xs text-sky-700">Excedente</dt><dd class="break-words pt-1 text-sm font-semibold tabular-nums text-sky-900">R$ {{ formatMoneyInput(forecast.excess) }}</dd></div>
                    </dl>
                    <ul v-if="forecast.receipts.length" class="flex min-w-0 flex-col gap-2" aria-label="Receitas vinculadas">
                        <li v-for="receipt in forecast.receipts" :key="receipt.id" class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-emerald-100 bg-emerald-50/50 px-3 py-2 text-sm">
                            <span class="min-w-0 break-words text-slate-600"><CircleCheck :size="16" class="mr-1 inline text-emerald-700" />{{ receipt.category_name || 'Receita' }} · {{ displayDate(receipt.occurred_at) }}</span>
                            <strong class="shrink-0 tabular-nums text-emerald-800">R$ {{ formatMoneyInput(receipt.amount) }}</strong>
                        </li>
                    </ul>
                </li>
            </ul>
            <nav v-if="forecasts.last_page > 1" class="flex flex-wrap items-center justify-between gap-3 text-sm" aria-label="Paginação dos recebimentos">
                <Link v-if="forecasts.prev_page_url" :href="forecasts.prev_page_url" class="rounded-xl border border-slate-300 px-4 py-3 focus-visible:ring-2 focus-visible:ring-emerald-500">Anterior</Link>
                <span class="text-slate-500">Página {{ forecasts.current_page }} de {{ forecasts.last_page }}</span>
                <Link v-if="forecasts.next_page_url" :href="forecasts.next_page_url" class="rounded-xl border border-slate-300 px-4 py-3 focus-visible:ring-2 focus-visible:ring-emerald-500">Próxima</Link>
            </nav>
        </div>

        <Modal :show="createOpen" max-width="md" :closeable="!createForm.processing" @close="closeCreate">
            <form class="flex min-w-0 flex-col gap-5 p-5 sm:p-6" @submit.prevent="submit">
                <h2 class="text-xl font-semibold text-slate-950">{{ editing ? 'Editar recebimento previsto' : 'Novo recebimento previsto' }}</h2>
                <p class="text-sm leading-6 text-slate-500">Isso registra uma expectativa, sem criar receita ou movimentar dinheiro.</p>
                <fieldset :disabled="createForm.processing" class="flex min-w-0 flex-col gap-4 disabled:opacity-60">
                    <div class="flex min-w-0 flex-col gap-2">
                        <InputLabel for="forecast-category" value="Categoria de receita" />
                        <select id="forecast-category" v-model="createForm.category_id" required autofocus class="w-full min-w-0 rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" :aria-invalid="!!createForm.errors.category_id" aria-describedby="forecast-category-error"><option disabled value="">Selecione uma categoria</option><option v-if="editing && !categories.some(category => category.id === editing.category_id)" :value="editing.category_id">{{ editing.category_name }} (inativa)</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select>
                        <InputError id="forecast-category-error" :message="createForm.errors.category_id" />
                    </div>
                    <div class="flex min-w-0 flex-col gap-2">
                        <InputLabel for="forecast-amount" value="Valor previsto (R$)" />
                        <TextInput id="forecast-amount" :model-value="createForm.amount" required inputmode="decimal" class="block w-full min-w-0" :aria-invalid="!!createForm.errors.amount" aria-describedby="forecast-amount-error" @update:model-value="createForm.amount = sanitizeMoneyInput($event)" />
                        <InputError id="forecast-amount-error" :message="createForm.errors.amount" />
                    </div>
                    <div class="flex min-w-0 flex-col gap-2">
                        <InputLabel for="forecast-date" value="Data prevista" />
                        <TextInput id="forecast-date" v-model="createForm.expected_on" type="date" min="1000-01-01" max="9999-12-31" required class="block w-full min-w-0" :aria-invalid="!!createForm.errors.expected_on" aria-describedby="forecast-date-error" />
                        <InputError id="forecast-date-error" :message="createForm.errors.expected_on" />
                    </div>
                    <div v-if="!editing" class="flex min-w-0 flex-col gap-2">
                        <InputLabel for="forecast-recurrence" value="Repetição mensal" />
                        <select id="forecast-recurrence" v-model="createForm.recurrence_count" class="w-full min-w-0 rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"><option :value="1">Somente esta vez</option><option :value="3">3 meses</option><option :value="6">6 meses</option><option :value="12">12 meses</option></select>
                        <p class="text-xs leading-5 text-slate-500">Se o dia não existir no mês, usamos o último dia e voltamos ao dia original no mês seguinte.</p>
                        <InputError :message="createForm.errors.recurrence_count" />
                    </div>
                    <div v-if="editing?.is_recurring" class="flex min-w-0 flex-col gap-2">
                        <InputLabel for="forecast-edit-scope" value="Aplicar alteração" />
                        <select id="forecast-edit-scope" v-model="createForm.edit_scope" class="w-full min-w-0 rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"><option value="this">Somente nesta ocorrência</option><option value="future" :disabled="editing.has_receipts">Nesta e nas próximas sem recebimento</option></select>
                        <p v-if="editing.has_receipts" class="text-xs leading-5 text-amber-700">O que já entrou fica preservado; data e total alteram somente o residual desta ocorrência.</p>
                        <InputError :message="createForm.errors.edit_scope" />
                    </div>
                </fieldset>
                <InputError :message="createForm.errors.operation_id" />
                <InputError :message="createForm.errors.version" />
                <InputError :message="createForm.errors.forecast" />
                <button v-if="createForm.isDirty || createForm.errors.operation_id" type="button" :disabled="createForm.processing" class="self-start rounded-lg px-2 py-2 text-sm font-medium text-slate-600 underline focus-visible:ring-2 focus-visible:ring-emerald-500" @click="discardDraft">Descartar rascunho e iniciar nova previsão</button>
                <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <button type="button" :disabled="createForm.processing" class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-emerald-500" @click="closeCreate">Voltar</button>
                    <button type="submit" :disabled="createForm.processing" class="rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white focus-visible:ring-2 focus-visible:ring-emerald-500 disabled:opacity-50">{{ createForm.processing ? 'Salvando…' : 'Salvar previsão' }}</button>
                </div>
            </form>
        </Modal>

        <Modal :show="cancelling !== null" max-width="md" :closeable="!cancelForm.processing" @close="closeCancellation">
            <form class="flex min-w-0 flex-col gap-5 p-5 sm:p-6" @submit.prevent="submitCancellation">
                <h2 class="text-xl font-semibold text-slate-950">Cancelar esta previsão?</h2>
                <p class="break-words text-sm leading-6 text-slate-500">{{ cancelling?.category_name }} · R$ {{ formatMoneyInput(cancelling?.amount ?? '0.00') }}. Ela ficará no histórico como cancelada. Nenhum saldo será alterado.</p>
                <InputError v-for="(message, key) in cancelForm.errors" :key="key" :message="message" />
                <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <button type="button" :disabled="cancelForm.processing" class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-emerald-500" @click="closeCancellation">Manter previsão</button>
                    <button type="submit" :disabled="cancelForm.processing" class="rounded-xl bg-rose-700 px-4 py-3 text-sm font-semibold text-white focus-visible:ring-2 focus-visible:ring-rose-500 disabled:opacity-50">{{ cancelForm.processing ? 'Cancelando…' : 'Confirmar cancelamento' }}</button>
                </div>
            </form>
        </Modal>

        <Modal :show="linking !== null" max-width="md" :closeable="!linkForm.processing" @close="closeLink">
            <form class="flex min-w-0 flex-col gap-5 p-5 sm:p-6" @submit.prevent="submitLink">
                <div class="flex min-w-0 flex-col gap-2">
                    <h2 class="text-xl font-semibold text-slate-950">Vincular receita recebida</h2>
                    <p class="break-words text-sm leading-6 text-slate-500">Escolha uma receita que já entrou. O sistema só cria o vínculo e não duplica o lançamento nem o saldo.</p>
                </div>
                <div v-if="availableReceipts.length" class="flex min-w-0 flex-col gap-2">
                    <InputLabel for="available-receipt" value="Receita disponível" />
                    <select id="available-receipt" v-model="linkForm.ledger_entry_id" required autofocus class="w-full min-w-0 rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" :aria-invalid="!!linkForm.errors.ledger_entry_id" aria-describedby="available-receipt-error">
                        <option disabled value="">Selecione uma receita</option>
                        <option v-for="receipt in availableReceipts" :key="receipt.id" :value="receipt.id">{{ displayDate(receipt.occurred_at) }} · R$ {{ formatMoneyInput(receipt.amount) }} · {{ receipt.category_name || 'Receita' }}{{ receipt.account_name ? ` · ${receipt.account_name}` : '' }}</option>
                    </select>
                    <InputError id="available-receipt-error" :message="linkForm.errors.ledger_entry_id" />
                </div>
                <p v-else class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">Nenhuma receita livre para vincular. Registre a entrada em Lançamentos e volte aqui. 🤝</p>
                <InputError :message="linkForm.errors.forecast_version" />
                <InputError :message="linkForm.errors.operation_id" />
                <InputError :message="linkForm.errors.forecast" />
                <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <button type="button" :disabled="linkForm.processing" class="min-h-11 rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-emerald-500" @click="closeLink">Voltar</button>
                    <button type="submit" :disabled="linkForm.processing || !availableReceipts.length" class="min-h-11 rounded-xl bg-emerald-700 px-4 py-3 text-sm font-semibold text-white focus-visible:ring-2 focus-visible:ring-emerald-500 disabled:opacity-50">{{ linkForm.processing ? 'Vinculando…' : 'Confirmar vínculo' }}</button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
