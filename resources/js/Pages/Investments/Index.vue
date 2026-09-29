<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ investments: Object });

const form = useForm({
    asset_type: 'Renda variável',
    ticker: '',
    name: '',
    purchased_on: '',
    quantity: '',
    average_cost: '',
    current_value: '',
    valued_on: '',
});

const submit = () => form.post(route('investments.store'), {
    preserveScroll: true,
    onSuccess: () => form.reset('ticker', 'name', 'purchased_on', 'quantity', 'average_cost', 'current_value', 'valued_on'),
});

const movement = useForm({
    position_id: '',
    type: 'buy',
    occurred_on: '',
    quantity: '',
    unit_price: '',
    amount: '',
    fees: '0',
    notes: '',
});

const submitMovement = () => {
    if (!movement.position_id) return;
    movement.post(route('investments.movements.store', movement.position_id), {
        preserveScroll: true,
        onSuccess: () => movement.reset('occurred_on', 'quantity', 'unit_price', 'amount', 'fees', 'notes'),
    });
};

const valuation = useForm({
    position_id: '',
    current_value: '',
    valued_on: '',
});

const submitValuation = () => {
    if (!valuation.position_id) return;
    valuation.patch(route('investments.valuation.update', valuation.position_id), {
        preserveScroll: true,
        onSuccess: () => valuation.reset('current_value', 'valued_on'),
    });
};
</script>

<template>
    <Head title="Investimentos" />
    <AuthenticatedLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-950">Investimentos</h1>
                <p class="mt-1 text-sm text-slate-500">Posições financeiras separadas do caixa disponível.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Investido</p>
                    <p class="mt-2 text-2xl font-bold">R$ {{ investments.summary.total_invested }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Valor atual</p>
                    <p class="mt-2 text-2xl font-bold">R$ {{ investments.summary.current_value }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Resultado não realizado</p>
                    <p class="mt-2 text-2xl font-bold">R$ {{ investments.summary.unrealized_result }}</p>
                </div>
            </div>

            <form class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 md:grid-cols-3" @submit.prevent="submit">
                <input v-model="form.name" class="rounded-xl border-slate-300" placeholder="Nome do ativo" required />
                <input v-model="form.ticker" class="rounded-xl border-slate-300" placeholder="Ticker (opcional)" />
                <input v-model="form.asset_type" class="rounded-xl border-slate-300" placeholder="Tipo" required />
                <input v-model="form.purchased_on" type="date" class="rounded-xl border-slate-300" required />
                <input v-model="form.quantity" type="number" step="0.00000001" min="0" class="rounded-xl border-slate-300" placeholder="Quantidade" required />
                <input v-model="form.average_cost" type="number" step="0.0001" min="0" class="rounded-xl border-slate-300" placeholder="Custo médio" required />
                <input v-model="form.current_value" type="number" step="0.01" min="0" class="rounded-xl border-slate-300" placeholder="Valor atual manual" />
                <input v-model="form.valued_on" type="date" class="rounded-xl border-slate-300" />
                <button class="rounded-xl bg-slate-950 px-4 py-2 font-semibold text-white md:col-span-2" :disabled="form.processing">Adicionar posição</button>
            </form>

            <div class="grid gap-4 lg:grid-cols-2">
                <form class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-5" @submit.prevent="submitMovement">
                    <h2 class="font-semibold text-slate-900">Registrar movimentação</h2>
                    <select v-model="movement.position_id" class="rounded-xl border-slate-300" required>
                        <option value="">Selecione a posição</option>
                        <option v-for="position in props.investments.positions" :key="position.id" :value="position.id">{{ position.ticker || position.name }}</option>
                    </select>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <select v-model="movement.type" class="rounded-xl border-slate-300">
                            <option value="buy">Compra</option>
                            <option value="sell">Venda</option>
                            <option value="income">Rendimento</option>
                            <option value="fee">Taxa</option>
                        </select>
                        <input v-model="movement.occurred_on" type="date" class="rounded-xl border-slate-300" required />
                        <input v-model="movement.quantity" type="number" step="0.00000001" min="0" class="rounded-xl border-slate-300" placeholder="Quantidade" />
                        <input v-model="movement.unit_price" type="number" step="0.0001" min="0" class="rounded-xl border-slate-300" placeholder="Preço unitário" />
                        <input v-model="movement.amount" type="number" step="0.01" min="0" class="rounded-xl border-slate-300" placeholder="Valor (rendimento/taxa)" />
                        <input v-model="movement.fees" type="number" step="0.01" min="0" class="rounded-xl border-slate-300" placeholder="Taxas" />
                    </div>
                    <input v-model="movement.notes" class="rounded-xl border-slate-300" placeholder="Observação" />
                    <button class="rounded-xl bg-slate-950 px-4 py-2 font-semibold text-white" :disabled="movement.processing">Registrar</button>
                </form>

                <form class="grid content-start gap-3 rounded-2xl border border-slate-200 bg-white p-5" @submit.prevent="submitValuation">
                    <h2 class="font-semibold text-slate-900">Atualizar valor atual</h2>
                    <select v-model="valuation.position_id" class="rounded-xl border-slate-300" required>
                        <option value="">Selecione a posição</option>
                        <option v-for="position in props.investments.positions" :key="position.id" :value="position.id">{{ position.ticker || position.name }}</option>
                    </select>
                    <input v-model="valuation.current_value" type="number" step="0.01" min="0" class="rounded-xl border-slate-300" placeholder="Valor atual da posição" required />
                    <input v-model="valuation.valued_on" type="date" class="rounded-xl border-slate-300" required />
                    <p class="text-xs text-slate-500">A avaliação manual atualiza patrimônio, não caixa disponível.</p>
                    <button class="rounded-xl bg-slate-950 px-4 py-2 font-semibold text-white" :disabled="valuation.processing">Atualizar avaliação</button>
                </form>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div v-if="!investments.positions.length" class="p-6 text-sm text-slate-500">Nenhuma posição cadastrada.</div>
                <div v-for="position in investments.positions" :key="position.id" class="flex flex-col gap-2 border-t border-slate-100 p-5 first:border-t-0 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-semibold text-slate-900">{{ position.ticker || position.name }}</p>
                        <p class="text-sm text-slate-500">{{ position.name }} · {{ position.asset_type }}</p>
                    </div>
                    <div class="text-sm sm:text-right">
                        <p>{{ position.quantity }} un. · PM R$ {{ position.average_cost }}</p>
                        <p class="font-medium">Atual: R$ {{ position.current_value ?? position.total_invested }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
