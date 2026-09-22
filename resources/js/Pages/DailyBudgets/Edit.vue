<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HelpHint from '@/Components/HelpHint.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatMoneyInput, normalizeMoneyInput, sanitizeMoneyInput } from '@/Support/money';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({
    localDate: { type: String, required: true },
    currentBudget: { type: Object, default: null },
});

const newOperationId = () => crypto.randomUUID();
const form = useForm({
    amount: props.currentBudget ? formatMoneyInput(props.currentBudget.amount) : '',
    reason: '',
    operation_id: newOperationId(),
});

// A changed request must not reuse the UUID of an earlier attempt with different data.
watch(() => [form.amount, form.reason], () => { form.operation_id = newOperationId(); });

function submit() {
    form.transform((data) => ({
        ...data,
        amount: normalizeMoneyInput(data.amount),
        reason: data.reason.trim() || null,
    })).post(route('daily-budgets.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reason = '';
            form.operation_id = newOperationId();
            form.defaults();
        },
    });
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Orçamento diário" />
        <div class="mx-auto flex max-w-2xl flex-col gap-5">
            <nav class="flex flex-wrap items-center gap-2 text-sm text-slate-600" aria-label="Você está aqui">
                <Link :href="route('dashboard')" class="rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 hover:text-emerald-700">Início</Link>
                <span aria-hidden="true">/</span>
                <Link :href="route('financial-settings.edit')" class="rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 hover:text-emerald-700">Configurações financeiras</Link>
                <span aria-hidden="true">/</span>
                <span aria-current="page" class="font-semibold text-slate-950">Orçamento diário</span>
            </nav>
            <header class="space-y-2">
                <p class="text-sm font-medium text-emerald-700">Planejamento pessoal · {{ localDate }}</p>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl">Orçamento diário</h1>
                <p class="text-sm leading-6 text-slate-500">Escolha quanto pretende gastar por dia. Você pode mudar esse valor quando precisar.</p>
            </header>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6" aria-label="Orçamento vigente">
                <p class="text-sm text-slate-500">Orçamento vigente agora</p>
                <p v-if="currentBudget" class="mt-2 text-3xl font-semibold text-slate-950">{{ new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(currentBudget.amount)) }}</p>
                <p v-else class="mt-2 text-lg font-semibold text-slate-700">Ainda não configurado</p>
                <div class="mt-2"><HelpHint label="O que esse valor significa?">É quanto você escolheu como referência para os gastos do dia. Não é o dinheiro que está na sua conta. Mudar o valor não confirma despesas nem altera automaticamente os dias anteriores.</HelpHint></div>
            </section>
            <form class="flex flex-col gap-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6" @submit.prevent="submit">
                <div class="space-y-2">
                    <InputLabel for="daily-budget-amount" value="Novo orçamento por dia (R$)" />
                    <p class="text-sm text-slate-500">Por exemplo, R$ 90,00 para orientar seus gastos de hoje.</p>
                    <TextInput id="daily-budget-amount" :model-value="form.amount" required inputmode="decimal" class="block w-full" :aria-invalid="!!form.errors.amount" @update:model-value="form.amount = sanitizeMoneyInput($event)" />
                    <InputError :message="form.errors.amount" />
                </div>
                <div class="space-y-2">
                    <InputLabel for="daily-budget-reason" value="Motivo da mudança (opcional)" />
                    <textarea id="daily-budget-reason" v-model="form.reason" maxlength="255" rows="2" class="block w-full rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" />
                    <HelpHint label="Preciso preencher o motivo?">Não. Se quiser, anote algo como “vou viajar hoje”. Essa informação ajuda você a lembrar por que mudou o valor.</HelpHint>
                    <InputError :message="form.errors.reason" />
                </div>
                <InputError :message="form.errors.operation_id" />
                <p v-if="form.recentlySuccessful" role="status" class="text-sm text-emerald-700">Orçamento atualizado.</p>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <Link :href="route('financial-settings.edit')" class="text-sm font-semibold text-slate-600 hover:text-slate-950">Voltar às configurações</Link>
                    <button type="submit" :disabled="form.processing || !form.amount" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white disabled:opacity-50">{{ form.processing ? 'Salvando…' : 'Atualizar orçamento' }}</button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
