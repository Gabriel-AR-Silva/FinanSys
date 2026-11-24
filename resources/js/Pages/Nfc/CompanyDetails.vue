<script setup>
import { computed } from 'vue';
import { date, money as formatMoney } from './format';

const props = defineProps({
    permissions: { type: Object, default: () => ({ create: false, update: false, delete: false }) },
    hiddenValues: { type: Boolean, default: false },
    readOnly: { type: Boolean, default: false },
    company: { type: Object, required: true },
    highlightSaleId: { type: String, default: null },
});
const money = value => props.hiddenValues ? '••••••' : formatMoney(value);
const emit = defineEmits(['edit', 'destroy']);

const units = computed(() => props.company.units.map(unit => ({
    ...unit,
    sales: props.company.sales.filter(sale => sale.unit_id === unit.id),
})));
const paymentMethods = { cash: 'Dinheiro', pix: 'Pix', debit: 'Débito', credit: 'Crédito' };
const paymentStatus = {
    paid: { label: 'Quitada', tone: 'nfc-success' },
    partial: { label: 'Parcial', tone: 'nfc-warning' },
    pending: { label: 'Pendente', tone: 'nfc-warning' },
};

function edit(kind, context = {}) {
    emit('edit', kind, { companyId: props.company.id, ...context });
}

function destroy(kind, context = {}) {
    emit('destroy', kind, { companyId: props.company.id, ...context });
}
</script>

<template>
    <div class="flex min-w-0 flex-col gap-4">
        <dl v-if="company.phone || company.cnpj || company.notes" class="nfc-panel grid gap-3 p-3 text-sm sm:grid-cols-2">
            <div v-if="company.phone"><dt class="nfc-muted">Telefone</dt><dd class="break-words font-medium">{{ company.phone }}</dd></div>
            <div v-if="company.cnpj"><dt class="nfc-muted">CPF/CNPJ</dt><dd class="break-words font-medium">{{ company.cnpj }}</dd></div>
            <div v-if="company.notes" class="min-w-0 sm:col-span-2"><dt class="nfc-muted">Observações</dt><dd class="whitespace-pre-wrap break-words">{{ company.notes }}</dd></div>
        </dl>
        <div class="flex flex-wrap items-center gap-2">
            <button v-if="permissions.update" type="button" class="nfc-button" @click="edit('company-form')">Editar empresa</button>
            <button v-if="permissions.create" type="button" class="nfc-button" @click="edit('unit')">Nova unidade</button>
            <button v-if="permissions.create" type="button" class="nfc-button nfc-primary" @click="edit('sale')">Nova venda</button>
            <button v-if="permissions.delete" type="button" class="nfc-button nfc-danger" @click="destroy('company')">Excluir empresa</button>
        </div>
        <dl class="grid min-w-0 grid-cols-2 gap-2 sm:grid-cols-4" aria-label="Resumo da empresa">
            <div class="nfc-panel flex min-w-0 flex-col gap-1 p-3">
                <dt class="nfc-muted text-xs">Plaquinhas vendidas</dt>
                <dd class="text-lg font-semibold tabular-nums">{{ company.sold }}</dd>
                <span class="nfc-muted text-xs">{{ company.usable_sold }} sem defeito · {{ company.defective }} com defeito</span>
            </div>
            <div class="nfc-panel flex min-w-0 flex-col gap-1 p-3">
                <dt class="nfc-muted text-xs">Total a receber</dt>
                <dd class="text-lg font-semibold tabular-nums" :class="company.outstanding_cents > 0 ? 'text-[var(--fs-warning)]' : ''">{{ money(company.outstanding_cents) }}</dd>
            </div>
        </dl>

        <details v-for="unit in units" :key="unit.id" class="nfc-panel min-w-0" :open="units.length === 1 || unit.sales.some(sale => sale.id === highlightSaleId)">
            <summary class="cursor-pointer break-words rounded-xl px-3 py-3 font-semibold">
                {{ unit.name }} <span class="nfc-muted text-sm font-normal">· {{ unit.sold }} plaquinha(s) vendida(s) · {{ unit.usable_sold }} sem defeito</span>
                <span v-if="unit.production_id" class="nfc-badge ml-1" :class="unit.production_status === 'in_progress' ? 'nfc-warning' : 'nfc-success'">{{ unit.production_status === 'in_progress' ? 'Em fabricação' : 'Fabricado' }} · lote #{{ unit.production_id }}</span>
            </summary>
            <div class="nfc-rule flex min-w-0 flex-col gap-3 border-t p-3">
                <div class="flex flex-wrap gap-2">
                    <button v-if="permissions.update" type="button" class="nfc-button" @click="edit('unit', { unitId: unit.id })">Editar unidade</button>
                    <button v-if="permissions.create" type="button" class="nfc-button" @click="edit('sale', { unitId: unit.id })">Nova venda nesta unidade</button>
                    <button v-if="permissions.delete" type="button" class="nfc-button nfc-danger" @click="destroy('unit', { unitId: unit.id })">Excluir unidade</button>
                </div>
                <p v-if="!unit.sales.length" class="nfc-muted text-sm">Nenhuma venda nesta unidade.</p>
                <details v-for="sale in [...unit.sales].reverse()" :key="sale.id" class="nfc-panel min-w-0" :open="sale.id === highlightSaleId">
                    <summary class="cursor-pointer rounded-xl px-3 py-3 text-sm font-semibold">
                        {{ date(sale.sold_on) }} · {{ sale.plates.length }} {{ sale.plates.length === 1 ? 'plaquinha' : 'plaquinhas' }}
                        <span class="nfc-badge ml-1" :class="paymentStatus[sale.payment_status].tone">{{ paymentStatus[sale.payment_status].label }}</span>
                    </summary>
                    <div class="nfc-rule flex min-w-0 flex-col gap-3 border-t p-3">
                        <p class="text-sm">
                            {{ sale.plates.filter(plate => plate.type === 'standard').length }} normais ·
                            {{ sale.plates.filter(plate => plate.type === 'custom').length }} personalizadas
                        </p>
                        <dl class="nfc-panel grid min-w-0 grid-cols-2 gap-3 p-3 text-sm sm:grid-cols-3" aria-label="Resumo financeiro da venda">
                            <div class="flex min-w-0 flex-col gap-1">
                                <dt class="nfc-muted text-xs">Valor total</dt>
                                <dd class="text-base font-semibold tabular-nums">{{ money(sale.total_cents) }}</dd>
                            </div>
                            <div class="flex min-w-0 flex-col gap-1">
                                <dt class="nfc-muted text-xs">Recebido</dt>
                                <dd class="text-base font-semibold tabular-nums">{{ money(sale.paid_cents) }}</dd>
                            </div>
                            <div class="flex min-w-0 flex-col gap-1">
                                <dt class="nfc-muted text-xs">A receber</dt>
                                <dd class="text-lg font-bold tabular-nums" :class="sale.balance_cents > 0 ? 'text-[var(--fs-warning)]' : ''">{{ money(sale.balance_cents) }}</dd>
                            </div>
                        </dl>
                        <p class="self-start nfc-badge" :class="sale.warranty_status === 'active' ? 'nfc-warning' : 'nfc-info'">
                            {{ sale.warranty_status === 'active' ? 'Em garantia até' : 'Garantia encerrada em' }} {{ date(sale.warranty_until) }}
                        </p>
                        <p v-if="sale.warranty_started_on && sale.warranty_started_on !== sale.sold_on" class="nfc-muted text-xs">Início da garantia: {{ date(sale.warranty_started_on) }}</p>
                        <ul v-if="sale.payments.length" class="flex flex-wrap gap-2" aria-label="Pagamentos">
                            <li v-for="payment in sale.payments" :key="payment.id" class="nfc-badge nfc-info flex items-center gap-2">
                                {{ paymentMethods[payment.method] }} · {{ money(payment.amount_cents) }} · {{ payment.received_on ? date(payment.received_on) : 'Sem data' }}
                                <button v-if="permissions.delete" type="button" class="font-semibold underline" :aria-label="'Excluir pagamento de ' + money(payment.amount_cents)" @click="destroy('payment', { saleId: sale.id, paymentId: payment.id })">Excluir</button>
                            </li>
                        </ul>
                        <div class="flex flex-wrap gap-2">
                            <button v-if="permissions.update" type="button" class="nfc-button" @click="edit('sale', { saleId: sale.id, unitId: unit.id })">Editar venda</button>
                            <button v-if="permissions.delete" type="button" class="nfc-button nfc-danger" @click="destroy('sale', { saleId: sale.id })">Excluir venda</button>
                            <button v-if="permissions.create && sale.balance_cents > 0" type="button" class="nfc-button nfc-primary" @click="edit('payment', { saleId: sale.id })">Registrar pagamento</button>
                        </div>
                        <ul class="nfc-rule border-t" aria-label="Plaquinhas da venda">
                            <li v-for="plate in sale.plates" :key="plate.id" class="nfc-rule flex min-w-0 flex-wrap items-center justify-between gap-2 border-b py-2 last:border-b-0">
                                <div class="min-w-0 flex-1 text-sm">
                                    <p class="break-words font-semibold">{{ plate.label || (plate.type === 'custom' ? 'Personalizada' : 'Normal') }}</p>
                                    <p v-if="plate.customizing" class="nfc-muted">Em customização</p>
                                    <p v-if="plate.defective" class="nfc-badge nfc-danger">Defeituosa · {{ plate.estimated_loss_cents == null ? 'prejuízo a estimar' : money(plate.estimated_loss_cents) }}</p>
                                    <p v-if="plate.notes" class="nfc-muted whitespace-pre-wrap break-words">{{ plate.notes }}</p>
                                </div>
                                <div class="flex gap-2">
                                    <button v-if="permissions.update" type="button" class="nfc-button" :aria-label="'Editar ' + (plate.label || 'plaquinha')" @click="edit('plate', { saleId: sale.id, plateId: plate.id })">Editar</button>
                                    <button v-if="permissions.delete" type="button" class="nfc-button nfc-danger" :aria-label="'Excluir ' + (plate.label || 'plaquinha')" @click="destroy('plate', { saleId: sale.id, plateId: plate.id })">Excluir</button>
                                </div>
                            </li>
                        </ul>
                    </div>
                </details>
            </div>
        </details>
    </div>
</template>
