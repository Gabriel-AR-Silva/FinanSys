<script setup>
import { useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { date, money, paymentMethods, plateTypes } from './format';

const props = defineProps({
    kind: { type: String, required: true },
    context: { type: Object, required: true },
    today: { type: String, required: true },
    company: { type: Object, default: null },
    companies: { type: Array, required: true },
    unit: { type: Object, default: null },
    sale: { type: Object, default: null },
    plate: { type: Object, default: null },
    batch: { type: Object, default: null },
    manufacturer: { type: Object, default: null },
    manufacturers: { type: Array, required: true },
    production: { type: Array, required: true },
    nfcRoute: { type: Function, required: true },
});
const emit = defineEmits(['cancel', 'saved', 'busy', 'feedback']);

const decimalPattern = '[0-9]+([.,][0-9]{1,2})?';
const inputMoney = cents => cents == null ? '' : (cents / 100).toFixed(2).replace('.', ',');

function asCents(value) {
    const normalized = String(value ?? '').trim().replace(',', '.');
    if (!/^\d+(?:\.\d{1,2})?$/.test(normalized)) return null;
    const [reais, fraction = ''] = normalized.split('.');
    const cents = Number(reais) * 100 + Number(fraction.padEnd(2, '0'));
    return Number.isSafeInteger(cents) ? cents : null;
}

function fields() {
    const { kind, context, company, unit, sale, plate, batch, manufacturer, today } = props;
    switch (kind) {
        case 'manufacturer-form':
            return {
                ...(!manufacturer && { operation_id: crypto.randomUUID() }),
                name: manufacturer?.name ?? '',
                phone: manufacturer?.phone ?? '',
                notes: manufacturer?.notes ?? '',
            };
        case 'company-form':
            return {
                ...(!company && { operation_id: crypto.randomUUID(), unit_name: '' }),
                name: company?.name ?? '',
                phone: company?.phone ?? '',
                cnpj: company?.cnpj ?? '',
                notes: company?.notes ?? '',
                interested: company?.interested ?? true,
            };
        case 'unit':
            return { ...(!unit && { operation_id: crypto.randomUUID() }), name: unit?.name ?? '', production_id: unit?.production_id ?? null };
        case 'sale':
            return {
                ...(!sale && { operation_id: crypto.randomUUID(), standard_quantity: 1, custom_quantity: 0, in_customization_quantity: 0, payments: [] }),
                unit_id: sale?.unit_id ?? context.unitId ?? company?.units[0]?.id ?? '',
                sold_on: sale?.sold_on ?? today,
                total: inputMoney(sale?.total_cents),
            };
        case 'payment':
            return { operation_id: crypto.randomUUID(), method: '', amount: inputMoney(sale.balance_cents) };
        case 'plate':
            return {
                type: plate.type, label: plate.label ?? '', notes: plate.notes ?? '',
                customizing: plate.customizing,
                defective: plate.defective ?? false,
                estimated_loss: inputMoney(plate.estimated_loss_cents),
            };
        case 'batch':
            return {
                ...(!batch && { operation_id: crypto.randomUUID(), initial: Boolean(context.initial) }),
                produced_on: batch ? batch.produced_on ?? '' : context.initial ? '' : today,
                quantity: batch?.quantity ?? (context.initial ? 60 : 1),
                customized_quantity: batch ? batch.customized_quantity ?? '' : context.initial ? '' : 0,
                cost: inputMoney(batch?.cost_cents),
                status: batch?.status ?? 'completed',
                manufacturer_id: batch?.manufacturer_id ?? null,
            };
        case 'notice':
            return { operation_id: crypto.randomUUID(), text: '', important: false, company_id: context.companyId ?? '', plate_id: context.plateId ?? '' };
    }
}

const form = useForm(fields());
const errors = computed(() => [...new Set(Object.values(form.errors).flat())]);
const companyForNotice = computed(() => props.companies.find(company => company.id === form.company_id));
const noticeSales = computed(() => companyForNotice.value?.sales.filter(sale => sale.plates.length) ?? []);
const initialBatch = computed(() => props.batch?.initial ?? Boolean(props.context.initial));
const balance = computed(() => {
    const total = asCents(form.total);
    if (total === null) return null;
    const entered = (form.payments ?? []).reduce((sum, payment) => sum + (asCents(payment.amount) ?? 0), 0);
    return total - entered;
});
let paymentRowKey = 0;

function addPayment() {
    form.payments.push({ key: ++paymentRowKey, method: '', amount: '' });
}

function salePayload({ total, payments, ...data }) {
    const payload = { ...data, total_cents: asCents(total) };
    if (payments) {
        payload.payments = payments.map(({ method, amount }) => ({ method, amount_cents: asCents(amount) }));
    }
    return payload;
}

function submit() {
    if (form.processing) return;
    const { company, sale, unit, plate, batch, manufacturer } = props;
    const successMessages = {
        'manufacturer-form': manufacturer ? 'Fabricante atualizado.' : 'Fabricante cadastrado.',
        'company-form': company ? 'Empresa atualizada.' : 'Empresa cadastrada.',
        unit: unit ? 'Unidade atualizada.' : 'Unidade cadastrada.',
        sale: sale ? 'Venda atualizada.' : 'Venda cadastrada.',
        payment: 'Pagamento registrado.',
        plate: 'Plaquinha atualizada.',
        batch: batch ? 'Lote atualizado.' : 'Lote cadastrado.',
        notice: 'Aviso adicionado.',
    };
    const options = {
        preserveScroll: true,
        onStart: () => emit('busy', true),
        onFinish: () => emit('busy', false),
        onSuccess: () => emit('saved', successMessages[props.kind] ?? 'Registro salvo.'),
        onError: errors => {
            const first = Object.values(errors ?? {}).flat().find(Boolean);
            emit('feedback', {
                type: 'error',
                message: first ? String(first) : 'Não foi possível salvar. Confira os dados e tente novamente.',
            });
        },
    };

    switch (props.kind) {
        case 'manufacturer-form':
            if (manufacturer) form.patch(props.nfcRoute('nfc.manufacturers.update', { manufacturer: manufacturer.id }), options);
            else form.post(props.nfcRoute('nfc.manufacturers.store'), options);
            break;
        case 'company-form':
            if (company) form.patch(props.nfcRoute('nfc.companies.update', { company: company.id }), options);
            else form.post(props.nfcRoute('nfc.companies.store'), options);
            break;
        case 'unit':
            if (unit) form.patch(props.nfcRoute('nfc.units.update', { company: company.id, unit: unit.id }), options);
            else form.post(props.nfcRoute('nfc.units.store', { company: company.id }), options);
            break;
        case 'sale':
            form.transform(salePayload);
            if (sale) form.patch(props.nfcRoute('nfc.sales.update', { company: company.id, sale: sale.id }), options);
            else form.post(props.nfcRoute('nfc.sales.store', { company: company.id }), options);
            break;
        case 'payment':
            form.transform(({ amount, ...data }) => ({ ...data, amount_cents: asCents(amount) }));
            form.post(props.nfcRoute('nfc.payments.store', { company: company.id, sale: sale.id }), options);
            break;
        case 'plate':
            form.transform(({ estimated_loss, ...data }) => ({
                ...data,
                estimated_loss_cents: data.defective && estimated_loss !== '' ? asCents(estimated_loss) : null,
            }));
            form.patch(props.nfcRoute('nfc.plates.update', { company: company.id, sale: sale.id, plate: plate.id }), options);
            break;
        case 'batch':
            form.transform(({ cost, customized_quantity, produced_on, ...data }) => ({
                ...data,
                produced_on: produced_on || null,
                customized_quantity: customized_quantity === '' ? null : customized_quantity,
                cost_cents: asCents(cost),
            }));
            if (batch) form.patch(props.nfcRoute('nfc.production.update', { production: batch.id }), options);
            else form.post(props.nfcRoute('nfc.production.store'), options);
            break;
        case 'notice':
            form.transform(data => ({ ...data, company_id: data.company_id || null, plate_id: data.plate_id || null }));
            form.post(props.nfcRoute('nfc.notices.store'), options);
            break;
    }
}
</script>

<template>
    <form class="flex min-w-0 flex-col gap-4" @submit.prevent="submit">
        <ul v-if="errors.length" class="nfc-danger grid gap-1 rounded-xl p-3 text-sm [overflow-wrap:anywhere]" role="alert">
            <li v-for="message in errors" :key="message">{{ message }}</li>
        </ul>

        <template v-if="kind === 'company-form'">
            <label class="nfc-field">Nome da empresa
                <input v-model.trim="form.name" class="nfc-control" autocomplete="organization" required />
            </label>
            <div class="grid min-w-0 gap-4 sm:grid-cols-2">
                <label class="nfc-field">Telefone ou WhatsApp
                    <input v-model.trim="form.phone" class="nfc-control" type="tel" autocomplete="tel" />
                </label>
                <label class="nfc-field">CPF/CNPJ
                    <input v-model.trim="form.cnpj" class="nfc-control" type="text" autocomplete="off" />
                </label>
            </div>
            <label class="nfc-field">Informações da empresa
                <textarea v-model.trim="form.notes" class="nfc-control" rows="3" />
            </label>
            <label v-if="!company" class="nfc-field">Nome da primeira unidade
                <input v-model.trim="form.unit_name" class="nfc-control" placeholder="Unidade 1" />
            </label>
            <label class="flex min-h-11 items-center gap-2 text-sm font-medium">
                <input v-model="form.interested" type="checkbox" class="nfc-check" /> Empresa interessada
            </label>
        </template>

        <template v-else-if="kind === 'manufacturer-form'">
            <label class="nfc-field">Nome do fabricante
                <input v-model.trim="form.name" class="nfc-control" autocomplete="organization" required />
            </label>
            <label class="nfc-field">Telefone ou WhatsApp
                <input v-model.trim="form.phone" class="nfc-control" type="tel" autocomplete="tel" />
            </label>
            <label class="nfc-field">Informações / observações
                <textarea v-model.trim="form.notes" class="nfc-control" rows="3" />
            </label>
            <p class="nfc-muted text-xs">Depois de salvar, abra a ficha do fabricante para anexar até 10 imagens.</p>
        </template>

        <template v-else-if="kind === 'unit'">
            <label class="nfc-field">Nome da unidade
                <input v-model.trim="form.name" class="nfc-control" required />
            </label>
            <label class="nfc-field">Lote vinculado
                <select v-model="form.production_id" class="nfc-control">
                    <option :value="null">Sem lote vinculado</option>
                    <option v-for="batchOption in production" :key="batchOption.id" :value="batchOption.id">
                        {{ batchOption.initial ? 'Lote inicial' : batchOption.produced_on ? date(batchOption.produced_on) : 'Sem data' }} · {{ batchOption.quantity }} plaquinhas
                    </option>
                </select>
            </label>
        </template>

        <template v-else-if="kind === 'sale'">
            <label class="nfc-field">Unidade
                <select v-model="form.unit_id" class="nfc-control" required>
                    <option v-for="item in company.units" :key="item.id" :value="item.id">{{ item.name }}</option>
                </select>
            </label>
            <div class="grid min-w-0 gap-4 sm:grid-cols-2">
                <label class="nfc-field">Data da venda
                    <input v-model="form.sold_on" class="nfc-control" type="date" required />
                </label>
                <label class="nfc-field">Valor total (R$)
                    <input v-model.trim="form.total" class="nfc-control" inputmode="decimal" :pattern="decimalPattern" placeholder="0,00" required />
                </label>
            </div>
            <template v-if="!sale">
                <div class="grid min-w-0 gap-3 sm:grid-cols-3">
                    <label class="nfc-field">Normais
                        <input v-model.number="form.standard_quantity" class="nfc-control" type="number" inputmode="numeric" min="0" step="1" required />
                    </label>
                    <label class="nfc-field">Personalizadas
                        <input v-model.number="form.custom_quantity" class="nfc-control" type="number" inputmode="numeric" min="0" step="1" required />
                    </label>
                    <label class="nfc-field">Em customização
                        <input v-model.number="form.in_customization_quantity" class="nfc-control" type="number" inputmode="numeric" min="0" :max="form.custom_quantity" step="1" required />
                    </label>
                </div>
                <fieldset class="nfc-rule flex min-w-0 flex-col gap-3 border-t pt-3">
                    <legend class="pr-2 text-sm font-semibold">Pagamentos recebidos</legend>
                    <div v-for="(payment, index) in form.payments" :key="payment.key" class="grid min-w-0 grid-cols-[minmax(0,1fr)_auto] items-end gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                        <label class="nfc-field col-span-2 sm:col-span-1">Forma de pagamento
                            <select v-model="payment.method" class="nfc-control" required>
                                <option value="" disabled>Selecione</option>
                                <option v-for="(label, value) in paymentMethods" :key="value" :value="value">{{ label }}</option>
                            </select>
                        </label>
                        <label class="nfc-field">Valor (R$)
                            <input v-model.trim="payment.amount" class="nfc-control" inputmode="decimal" :pattern="decimalPattern" placeholder="0,00" required />
                        </label>
                        <button type="button" class="nfc-button nfc-icon-button" :aria-label="`Remover pagamento ${index + 1}`" @click="form.payments.splice(index, 1)"><Trash2 :size="16" aria-hidden="true" /></button>
                    </div>
                    <button type="button" class="nfc-button self-start" @click="addPayment"><Plus :size="16" aria-hidden="true" /> Adicionar pagamento</button>
                    <p v-if="balance !== null" class="text-sm font-medium" aria-live="polite">
                        {{ balance < 0 ? `Pagamentos acima do total em ${money(-balance)}` : `Saldo a receber ${money(balance)}` }}
                    </p>
                </fieldset>
            </template>
            <p v-else class="nfc-muted text-sm">Já recebido {{ money(sale.paid_cents) }}</p>
        </template>

        <template v-else-if="kind === 'payment'">
            <p class="nfc-muted text-sm">Saldo a receber {{ money(sale.balance_cents) }}</p>
            <label class="nfc-field">Forma de pagamento
                <select v-model="form.method" class="nfc-control" required>
                    <option value="" disabled>Selecione</option>
                    <option v-for="(label, value) in paymentMethods" :key="value" :value="value">{{ label }}</option>
                </select>
            </label>
            <label class="nfc-field">Valor (R$)
                <input v-model.trim="form.amount" class="nfc-control" inputmode="decimal" :pattern="decimalPattern" placeholder="0,00" required />
            </label>
        </template>

        <template v-else-if="kind === 'plate'">
            <label class="nfc-field">Identificação
                <input v-model.trim="form.label" class="nfc-control" placeholder="Ex.: plaquinha do caixa" />
            </label>
            <label class="nfc-field">Tipo
                <select v-model="form.type" class="nfc-control" required @change="form.type === 'standard' && (form.customizing = false)">
                    <option v-for="(label, value) in plateTypes" :key="value" :value="value">{{ label }}</option>
                </select>
            </label>
            <label v-if="form.type === 'custom'" class="flex min-h-11 items-center gap-2 text-sm font-medium">
                <input v-model="form.customizing" type="checkbox" class="nfc-check" /> Em customização
            </label>
            <label class="flex min-h-11 items-center gap-2 text-sm font-medium">
                <input v-model="form.defective" type="checkbox" class="nfc-check" /> Plaquinha com defeito
            </label>
            <label v-if="form.defective" class="nfc-field">Possível prejuízo (R$)
                <input v-model.trim="form.estimated_loss" class="nfc-control" inputmode="decimal" :pattern="decimalPattern" placeholder="Ainda não estimado" />
            </label>
            <p v-if="form.defective" class="nfc-muted text-xs">Estimativa informativa: não altera o valor da venda nem os pagamentos recebidos.</p>
            <label class="nfc-field">Observação
                <textarea v-model.trim="form.notes" class="nfc-control" rows="3" />
            </label>
        </template>

        <template v-else-if="kind === 'batch'">
            <label class="nfc-field">Fabricante
                <select v-model="form.manufacturer_id" class="nfc-control">
                    <option :value="null">Sem fabricante vinculado</option>
                    <option v-for="item in manufacturers" :key="item.id" :value="item.id">{{ item.name }}</option>
                </select>
            </label>
            <label class="nfc-field">Etapa do lote
                <select v-model="form.status" class="nfc-control" required>
                    <option value="in_progress">Em fabricação</option>
                    <option value="completed">Fabricado</option>
                </select>
            </label>
            <label class="nfc-field">Data da fabricação
                <input v-model="form.produced_on" class="nfc-control" type="date" :required="!initialBatch && form.status === 'completed'" />
            </label>
            <div class="grid min-w-0 gap-4 sm:grid-cols-2">
                <label class="nfc-field">Quantidade fabricada
                    <input v-model.number="form.quantity" class="nfc-control" type="number" inputmode="numeric" min="1" step="1" required />
                </label>
                <label class="nfc-field">Quantas são personalizadas?
                    <input v-model.number="form.customized_quantity" class="nfc-control" type="number" inputmode="numeric" min="0" :max="form.quantity" step="1" :required="!initialBatch" />
                </label>
            </div>
            <label class="nfc-field">Custo do lote (R$)
                <input v-model.trim="form.cost" class="nfc-control" inputmode="decimal" :pattern="decimalPattern" placeholder="A informar" />
            </label>
        </template>

        <template v-else-if="kind === 'notice'">
            <label class="nfc-field">Observação
                <textarea v-model.trim="form.text" class="nfc-control" rows="3" required />
            </label>
            <label class="nfc-field">Empresa (opcional)
                <select v-model="form.company_id" class="nfc-control" @change="form.plate_id = ''">
                    <option value="">Selecione uma empresa</option>
                    <option v-for="item in companies" :key="item.id" :value="item.id">{{ item.name }}</option>
                </select>
            </label>
            <label v-if="noticeSales.length" class="nfc-field">Plaquinha (opcional)
                <select v-model="form.plate_id" class="nfc-control">
                    <option value="">Selecione uma plaquinha</option>
                    <optgroup v-for="item in noticeSales" :key="item.id" :label="`${companyForNotice.units.find(unit => unit.id === item.unit_id)?.name ?? 'Unidade'} · ${date(item.sold_on)}`">
                        <option v-for="(plate, index) in item.plates" :key="plate.id" :value="plate.id">{{ plate.label || `${plateTypes[plate.type]} ${index + 1}` }}</option>
                    </optgroup>
                </select>
            </label>
            <label class="flex min-h-11 items-center gap-2 text-sm font-medium">
                <input v-model="form.important" type="checkbox" class="nfc-check" /> Importante
            </label>
        </template>

        <div class="nfc-rule flex flex-wrap justify-end gap-2 border-t pt-4">
            <button type="button" class="nfc-button" :disabled="form.processing" @click="emit('cancel')">Cancelar</button>
            <button type="submit" class="nfc-button nfc-primary" :disabled="form.processing">{{ form.processing ? 'Salvando…' : 'Salvar' }}</button>
        </div>
    </form>
</template>
