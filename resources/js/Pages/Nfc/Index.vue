<script setup>
import Modal from '@/Components/Modal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import NfcSharedLayout from '@/Layouts/NfcSharedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Bell, Building2, ChevronRight, Factory, History, Plus, Eye, EyeOff, EllipsisVertical, Share2, Trash2, FileDown, X } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import CompanyDetails from './CompanyDetails.vue';
import ManufacturerDetails from './ManufacturerDetails.vue';
import Editor from './Editor.vue';
import Notices from './Notices.vue';
import PerformanceChart from './PerformanceChart.vue';
import { date, money as formatMoney } from './format';
import './nfc.css';

const props = defineProps({
    schema_pending: { type: Boolean, default: false },
    shared: { type: Boolean, default: false },
    read_only: { type: Boolean, default: false },
    permissions: { type: Object, default: () => ({ create: false, update: false, delete: false }) },
    privacy_key: { type: String, default: '' },
    share_token: { type: String, default: null },
    share_links: { type: Array, default: () => [] },
    share_links_enabled: { type: Boolean, default: false },
    created_share_url: { type: String, default: null },
    today: { type: String, default: '' },
    summary: { type: Object, default: () => ({}) },
    companies: { type: Array, default: () => [] },
    production: { type: Array, default: () => [] },
    reinvestments: { type: Array, default: () => [] },
    damages: { type: Array, default: () => [] },
    expenses: { type: Array, default: () => [] },
    manufacturers: { type: Array, default: () => [] },
    notices: { type: Array, default: () => [] },
    warranty_notices: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
});

const filters = [
    { key: 'all', label: 'Todas' },
    { key: 'interested', label: 'Interessadas' },
    { key: 'not_interested', label: 'Não interessadas' },
    { key: 'sold', label: 'Com vendas' },
];
const filter = ref('all');
const search = ref('');
const productionSearch = ref('');
const manufacturerSearch = ref('');
const dashboardTabs = [
    { key: 'finance', label: 'Financeiro' },
    { key: 'evolution', label: 'Evolução do negócio' },
    { key: 'registry', label: 'Cadastros' },
];
const activeDashboardTab = ref('finance');
async function onDashboardTabKeydown(event, current) {
    const order = dashboardTabs.map(tab => tab.key);
    const index = order.indexOf(current);
    let next = null;
    if (event.key === 'ArrowRight') next = order[(index + 1) % order.length];
    if (event.key === 'ArrowLeft') next = order[(index - 1 + order.length) % order.length];
    if (event.key === 'Home') next = order[0];
    if (event.key === 'End') next = order[order.length - 1];
    if (next === null) return;
    event.preventDefault();
    activeDashboardTab.value = next;
    await nextTick();
    document.getElementById(`nfc-dashboard-tab-${next}`)?.focus();
}
const activeRegistryTab = ref('companies');
const registryTabOrder = ['companies', 'production', 'manufacturers'];
async function onRegistryTabKeydown(event, current) {
    const index = registryTabOrder.indexOf(current);
    let next = null;
    if (event.key === 'ArrowRight') next = registryTabOrder[(index + 1) % registryTabOrder.length];
    if (event.key === 'ArrowLeft') next = registryTabOrder[(index - 1 + registryTabOrder.length) % registryTabOrder.length];
    if (event.key === 'Home') next = registryTabOrder[0];
    if (event.key === 'End') next = registryTabOrder[registryTabOrder.length - 1];
    if (next === null) return;
    event.preventDefault();
    activeRegistryTab.value = next;
    await nextTick();
    document.getElementById(`nfc-tab-${next}`)?.focus();
}
const page = usePage();
const authenticatedUser = computed(() => page.props.auth?.user ?? null);
const dialog = ref(null);
const dialogHeading = ref(null);
const busy = ref(false);
const shareBusy = ref(false);
const shareScope = ref('view');
const settingsOpen = ref(false);
const settingsButton = ref(null);
const resetConfirmation = ref('');
const reportFrom = ref('');
const reportName = ref('');
const reportTo = ref('');
const reportChart = ref(true);
const reportReinvestments = ref(true);
const reinvestmentAmount = ref('');
const reinvestmentForm = useForm({
    operation_id: crypto.randomUUID(),
    spent_on: props.today,
    amount_cents: 0,
    description: '',
    production_id: '',
});
function openReinvestment() {
    if (props.shared || !authenticatedUser.value || !props.permissions.create || busy.value) return;
    reinvestmentForm.reset();
    reinvestmentForm.clearErrors();
    reinvestmentForm.operation_id = crypto.randomUUID();
    reinvestmentForm.spent_on = props.today;
    reinvestmentAmount.value = '';
    dialog.value = { kind: 'reinvestment' };
}
function saveReinvestment() {
    if (props.shared || !authenticatedUser.value || reinvestmentForm.processing || busy.value) return;
    const amount = reinvestmentAmount.value.trim().replace(',', '.');
    if (!/^\d+(?:\.\d{1,2})?$/.test(amount)) {
        notify({ type: 'error', message: 'Informe um valor válido em reais, com até duas casas decimais.' });
        return;
    }
    const [reais, fraction = ''] = amount.split('.');
    const cents = Number(reais) * 100 + Number(fraction.padEnd(2, '0'));
    if (!Number.isSafeInteger(cents) || cents < 1 || cents > 999999999) {
        notify({ type: 'error', message: 'Informe um valor maior que zero dentro do limite permitido.' });
        return;
    }
    reinvestmentForm.transform(data => ({
        ...data, amount_cents: cents, production_id: data.production_id || null,
    })).post(route('nfc.reinvestments.store'), {
        preserveScroll: true,
        onStart: () => { busy.value = true; },
        onFinish: () => { busy.value = false; },
        onSuccess: () => {
            dialog.value = null;
            reinvestmentForm.reset();
            reinvestmentForm.operation_id = crypto.randomUUID();
            reinvestmentAmount.value = '';
            notify({ message: 'Reinvestimento registrado no caixa NFC.' });
        },
        onError: errors => reportError(errors, 'Não foi possível registrar o reinvestimento.'),
    });
}

const expenseAmount = ref('');
const expenseForm = useForm({
    operation_id: crypto.randomUUID(),
    spent_on: props.today,
    amount_cents: 0,
    description: '',
    category: 'materials',
    production_id: '',
    damage_id: '',
});
const expenseCategories = {
    materials: 'Materiais e insumos',
    tools: 'Ferramentas',
    rework: 'Retrabalho por avaria',
    other: 'Outros gastos',
};
function openExpense() {
    if (props.shared || !authenticatedUser.value || !props.permissions.create || busy.value) return;
    expenseForm.reset();
    expenseForm.clearErrors();
    expenseForm.operation_id = crypto.randomUUID();
    expenseForm.spent_on = props.today;
    expenseAmount.value = '';
    dialog.value = { kind: 'expense' };
}
function saveExpense() {
    if (props.shared || !authenticatedUser.value || expenseForm.processing || busy.value) return;
    const raw = expenseAmount.value.trim().replace(',', '.');
    if (!/^\d+(?:\.\d{1,2})?$/.test(raw)) {
        notify({ type: 'error', message: 'Informe um valor válido em reais.' });
        return;
    }
    const [whole, fraction = ''] = raw.split('.');
    const cents = Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
    if (!Number.isSafeInteger(cents) || cents < 1 || cents > 999999999) {
        notify({ type: 'error', message: 'Valor fora do limite permitido.' });
        return;
    }
    expenseForm.transform(data => ({
        ...data, amount_cents: cents, production_id: data.production_id || null, damage_id: data.damage_id || null,
    })).post(route('nfc.expenses.store'), {
        preserveScroll: true,
        onStart: () => { busy.value = true; },
        onFinish: () => { busy.value = false; },
        onSuccess: () => { dialog.value = null; notify({ message: 'Despesa registrada no caixa NFC.' }); },
        onError: errors => reportError(errors, 'Não foi possível registrar a despesa.'),
    });
}

const damageAmount = ref('');
const damageForm = useForm({
    operation_id: crypto.randomUUID(), target: 'production',
    production_id: '', company_id: '', sale_id: '', plate_id: '',
    quantity: 1, occurred_on: props.today, reason: '', estimated_loss_cents: null,
});
const damageCompanies = computed(() => props.companies.filter(company => company.sales.some(sale => sale.plates.length)));
const damageCompany = computed(() => damageCompanies.value.find(company => String(company.id) === String(damageForm.company_id)));
const damageSales = computed(() => (damageCompany.value?.sales ?? []).filter(sale => sale.plates.some(plate => !plate.defective)));
const damageBatch = computed(() => props.production.find(batch => String(batch.id) === String(damageForm.production_id)));
const damagePlates = computed(() => damageSales.value.find(sale => sale.id === damageForm.sale_id)?.plates.filter(plate => !plate.defective) ?? []);
function openDamage() {
    if (!props.permissions.create || busy.value) return;
    damageForm.reset(); damageForm.clearErrors();
    damageForm.operation_id = crypto.randomUUID();
    damageForm.occurred_on = props.today;
    damageAmount.value = '';
    dialog.value = { kind: 'damage' };
}
function openDamageForBatch(batchId) {
    openDamage();
    if (dialog.value?.kind === 'damage') damageForm.production_id = batchId;
}
function saveDamage() {
    if (busy.value || damageForm.processing) return;
    if (damageForm.target === 'production' && (!damageBatch.value || !Number.isInteger(Number(damageForm.quantity)) || Number(damageForm.quantity) < 1 || Number(damageForm.quantity) > damageBatch.value.usable_quantity)) {
        damageForm.setError('quantity', 'Informe uma quantidade dentro do saldo do lote.');
        return;
    }
    const raw = damageAmount.value.trim().replace(',', '.');
    if (raw && !/^\d+(?:\.\d{1,2})?$/.test(raw)) {
        notify({ type: 'error', message: 'Informe um prejuízo válido, com até duas casas decimais.' });
        return;
    }
    const [whole, fraction = ''] = raw ? raw.split('.') : ['0', ''];
    const cents = Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
    if (!Number.isSafeInteger(cents) || cents > 999999999) {
        notify({ type: 'error', message: 'Valor de prejuízo fora do limite permitido.' });
        return;
    }
    damageForm.transform(data => ({
        ...data, quantity: data.target === 'plate' ? 1 : Number(data.quantity),
        production_id: data.target === 'production' ? Number(data.production_id) : null,
        company_id: data.target === 'plate' ? Number(data.company_id) : null,
        sale_id: data.target === 'plate' ? data.sale_id : null,
        plate_id: data.target === 'plate' ? data.plate_id : null,
        estimated_loss_cents: raw ? cents : null,
    })).post(nfcRoute('nfc.damages.store'), {
        preserveScroll: true,
        onStart: () => { busy.value = true; },
        onFinish: () => { busy.value = false; },
        onSuccess: () => { dialog.value = null; notify({ message: 'Avaria registrada.' }); },
        onError: errors => reportError(errors, 'Não foi possível registrar a avaria.'),
    });
}
const reportOptions = [
    { key: 'sales', label: 'Valor vendido' },
    { key: 'received', label: 'Recebido' },
    { key: 'pending', label: 'A receber' },
    { key: 'cost', label: 'Custo de fabricação' },
    { key: 'loss', label: 'Perdas estimadas' },
    { key: 'result', label: 'Diferença e margem gerencial' },
    { key: 'sold', label: 'Plaquinhas vendidas' },
    { key: 'manufactured', label: 'Plaquinhas fabricadas' },
    { key: 'defective', label: 'Plaquinhas defeituosas' },
];
const reportMetrics = ref(reportOptions.map(item => item.key));
const reportValid = computed(() => !!reportFrom.value && !!reportTo.value && reportFrom.value <= reportTo.value && reportMetrics.value.length > 0 && reportName.value.length <= 80 && /^[\p{L}\p{N}\s._-]*$/u.test(reportName.value));
const reportUrl = computed(() => {
    if (!reportValid.value || props.shared || !authenticatedUser.value || props.schema_pending) return '';
    return route('nfc.report.export', { from: reportFrom.value, to: reportTo.value, metrics: reportMetrics.value, chart: reportChart.value ? 1 : 0, reinvestments: reportReinvestments.value ? 1 : 0, ...(reportName.value.trim() ? { name: reportName.value.trim() } : {}) });
});
function openSettingsAction(kind) {
    if (props.shared || !authenticatedUser.value || busy.value || shareBusy.value) return;
    if (['reset', 'report'].includes(kind) && props.schema_pending) return;
    if (kind === 'report' && !reportFrom.value) {
        reportFrom.value = props.today.slice(0, 7) + '-01';
        reportTo.value = props.today;
    }
    settingsOpen.value = false;
    resetConfirmation.value = '';
    dialog.value = { kind };
}
function closeSettings() {
    if (!settingsOpen.value) return;
    settingsOpen.value = false;
    settingsButton.value?.focus();
}
function resetWorkspace() {
    if (props.shared || !authenticatedUser.value || props.schema_pending || busy.value || resetConfirmation.value !== 'ZERAR') return;
    router.delete(route('nfc.reset'), {
        data: { confirmation: resetConfirmation.value },
        preserveScroll: true,
        onStart: () => { busy.value = true; },
        onFinish: () => { busy.value = false; },
        onSuccess: () => {
            dialog.value = null;
            resetConfirmation.value = '';
            filter.value = 'all';
            search.value = '';
            productionSearch.value = '';
            manufacturerSearch.value = '';
            activeDashboardTab.value = 'finance';
            activeRegistryTab.value = 'companies';
            notify({ message: 'Cadastros NFC zerados. Links compartilhados preservados.' });
        },
        onError: errors => reportError(errors, 'Não foi possível zerar os cadastros.'),
    });
}
const scopeOptions = [
    { value: 'view', label: 'Somente consulta' },
    { value: 'c', label: 'Consultar e cadastrar' },
    { value: 'u', label: 'Consultar e editar' },
    { value: 'd', label: 'Consultar e excluir' },
    { value: 'cu', label: 'Cadastrar e editar' },
    { value: 'cd', label: 'Cadastrar e excluir' },
    { value: 'ud', label: 'Editar e excluir' },
    { value: 'cud', label: 'Acesso completo' },
];
const hiddenValues = ref(false);
const money = value => hiddenValues.value ? '••••••' : formatMoney(value);
watch(() => props.privacy_key, key => {
    try { hiddenValues.value = !!key && localStorage.getItem('nfc-privacy:' + key) === '1'; }
    catch (_) { hiddenValues.value = false; }
}, { immediate: true });
function togglePrivacy() {
    hiddenValues.value = !hiddenValues.value;
    try { if (props.privacy_key) localStorage.setItem('nfc-privacy:' + props.privacy_key, hiddenValues.value ? '1' : '0'); } catch (_) {}
}
function updateShare(link, scope) {
    if (shareBusy.value || props.shared || !authenticatedUser.value || scope === link.scope) return;
    router.patch(route('nfc.share-links.update', { link: link.id }), { scope }, {
        preserveScroll: true,
        onStart: () => { shareBusy.value = true; },
        onFinish: () => { shareBusy.value = false; },
        onSuccess: () => notify({ message: 'Permissões atualizadas.' }),
        onError: errors => reportError(errors, 'Não foi possível alterar as permissões.'),
    });
}
const copied = ref(false);
const feedback = ref(null);
let feedbackTimer = null;

function notify({ type = 'success', message }) {
    if (!message) return;
    if (feedbackTimer) clearTimeout(feedbackTimer);
    feedback.value = { type, message };
    feedbackTimer = setTimeout(() => { feedback.value = null; feedbackTimer = null; }, type === 'error' ? 8000 : 4500);
}

function reportError(errors, fallback) {
    const first = Object.values(errors ?? {}).flat().find(Boolean);
    notify({ type: 'error', message: first ? String(first) : fallback });
}

onBeforeUnmount(() => { if (feedbackTimer) clearTimeout(feedbackTimer); });

const nfcRoute = (name, params = {}) => props.shared
    ? route(name.replace(/^nfc\./, 'nfc.share.'), { token: props.share_token, ...params })
    : route(name, params);

function createShare() {
    if (shareBusy.value || props.shared || !props.share_links_enabled || !authenticatedUser.value) return;
    router.post(route('nfc.share-links.store'), { scope: shareScope.value }, {
        preserveScroll: true,
        onStart: () => { shareBusy.value = true; },
        onFinish: () => { shareBusy.value = false; },
        onSuccess: () => notify({ message: 'Link criado. Copie o endereço para compartilhar.' }),
        onError: errors => reportError(errors, 'Não foi possível gerar o link.'),
    });
}

function revokeShare(link) {
    if (shareBusy.value || busy.value || props.shared || !authenticatedUser.value) return;
    destroy('share-link', { linkId: link.id });
}

async function copyShare() {
    if (!props.created_share_url) return;
    try {
        await navigator.clipboard.writeText(props.created_share_url);
        copied.value = true;
        notify({ message: 'Link copiado.' });
    } catch (_) {
        copied.value = false;
        notify({ type: 'error', message: 'Não foi possível copiar automaticamente. Selecione o link para copiar.' });
    }
}

const companiesShown = computed(() => {
    const text = search.value.trim().toLocaleLowerCase('pt-BR');
    return props.companies.filter(company => {
        if (filter.value === 'interested' && !company.interested) return false;
        if (filter.value === 'not_interested' && company.interested) return false;
        if (filter.value === 'sold' && !company.sold) return false;
        if (!text) return true;
        return [
            company.name, company.cnpj, company.phone,
            ...company.units.map(unit => unit.name),
            ...company.sales.flatMap(sale => [
                sale.id,
                ...sale.plates.map(plate => plate.label ?? ''),
            ]),
        ].some(value => String(value ?? '').toLocaleLowerCase('pt-BR').includes(text));
    });
});
const unitRows = computed(() => companiesShown.value.flatMap(company => {
    const units = company.units.length
        ? company.units
        : [{ id: `empty-${company.id}`, name: 'Sem unidade cadastrada', production_id: null }];
    return units.map(unit => {
        const sales = company.sales.filter(sale => sale.unit_id === unit.id);
        return {
            id: unit.id,
            companyId: company.id,
            companyName: company.name,
            identifier: company.cnpj,
            unitName: unit.name,
            hasUnit: company.units.length > 0,
            productionId: unit.production_id ?? null,
            productionStatus: unit.production_status ?? null,
            sold: unit.sold ?? 0,
            defective: unit.defective ?? 0,
            usableSold: unit.usable_sold ?? 0,
            total: sales.reduce((sum, sale) => sum + sale.total_cents, 0),
            received: sales.reduce((sum, sale) => sum + sale.paid_cents, 0),
            outstanding: sales.reduce((sum, sale) => sum + sale.balance_cents, 0),
        };
    });
}));
const productionShown = computed(() => {
    const text = productionSearch.value.trim().toLocaleLowerCase('pt-BR');
    return props.production.map(batch => ({
        ...batch,
        linkedUnits: props.companies.flatMap(company =>
            company.units.filter(unit => unit.production_id === batch.id)
                .map(unit => `${company.name} · ${unit.name}`)),
    })).filter(batch => !text || [
        batch.id,
        batch.initial ? 'Lote inicial' : 'Lote',
        batch.produced_on,
        batch.quantity,
        batch.customized_quantity,
        batch.cost_cents,
        batch.status === 'in_progress' ? 'Em fabricação' : 'Fabricado',
        ...batch.linkedUnits,
    ].some(value => String(value ?? '').toLocaleLowerCase('pt-BR').includes(text)));
});
const manufacturersShown = computed(() => {
    const text = manufacturerSearch.value.trim().toLocaleLowerCase('pt-BR');
    return props.manufacturers.filter(item => !text || [
        item.name, item.phone, item.notes,
        ...item.linked_units.map(unit => unit.company_name + ' ' + unit.unit_name),
    ].some(value => String(value ?? '').toLocaleLowerCase('pt-BR').includes(text)));
});
const noticeCount = computed(() => props.notices.filter(notice => !notice.resolved_at).length + props.warranty_notices.length);
const hasInitialBatch = computed(() => props.production.some(batch => batch.initial));

const financialSummary = computed(() => {
    const totals = props.summary;
    const received = Number(totals.received_cents ?? 0);
    const outstanding = Number(totals.outstanding_cents ?? 0);
    const revenue = received + outstanding;
    return {
        received, outstanding, revenue,
        receivedPercent: revenue > 0 ? Math.round(received / revenue * 100) : 0,
        outstandingPercent: revenue > 0 ? 100 - Math.round(received / revenue * 100) : 0,
    };
});
const metricGroups = computed(() => {
    const totals = props.summary;
    const finance = financialSummary.value;
    const costsPending = totals.unknown_cost_batches ?? 0;
    return [
        { title: 'Financeiro', metrics: [
            { label: 'Recebido', value: money(finance.received), tone: 'text-[var(--fs-success)]' },
            { label: 'A receber', value: money(finance.outstanding), tone: 'text-[var(--fs-warning)]' },
            { label: 'Total vendido', value: money(finance.revenue) },
            { label: 'Custo de fabricação', value: costsPending && costsPending === props.production.length ? '—' : money(totals.production_cost_cents ?? 0), detail: costsPending ? `${costsPending} lote(s) sem custo informado` : 'Custos informados dos lotes' },
            { label: 'Perda estimada', value: money(totals.estimated_loss_cents ?? 0), tone: 'text-[var(--fs-danger)]', detail: totals.unestimated_defects ? `${totals.unestimated_defects} defeito(s) sem estimativa` : 'Não descontada automaticamente da receita' },
        ] },
        { title: 'Produção', metrics: [
            { label: 'Fabricadas', value: totals.manufactured ?? 0, detail: `${totals.usable_manufactured ?? 0} aproveitáveis após avarias de fabricação` },
            { label: 'Em fabricação', value: totals.in_production ?? 0 },
            { label: 'Em customização', value: totals.in_customization ?? 0 },
            { label: 'Com defeito', value: totals.defective ?? 0, tone: 'text-[var(--fs-danger)]' },
        ] },
        { title: 'Vendas e clientes', metrics: [
            { label: 'Vendidas', value: totals.sold ?? 0, detail: `${totals.usable_sold ?? 0} sem defeito · ${totals.sold_defective ?? 0} com defeito` },
            { label: 'Plaquinhas pagas', value: totals.paid_plates ?? 0, detail: 'Em vendas quitadas' },
            { label: 'Empresas interessadas', value: totals.interested ?? 0 },
        ] },
    ];
});

const company = computed(() => props.companies.find(item => item.id === dialog.value?.companyId));
const sale = computed(() => company.value?.sales.find(item => item.id === dialog.value?.saleId));
const unit = computed(() => company.value?.units.find(item => item.id === dialog.value?.unitId));
const plate = computed(() => sale.value?.plates.find(item => item.id === dialog.value?.plateId));
const batch = computed(() => props.production.find(item => item.id === dialog.value?.batchId));
const manufacturer = computed(() => props.manufacturers.find(item => item.id === dialog.value?.manufacturerId));

const titles = {
    notices: 'Avisos',
    'confirm-delete': 'Confirmar ação',
    history: 'Histórico de alterações',
    share: 'Links de acesso NFC',
    reset: 'Zerar cadastros NFC',
    report: 'Exportar relatório PDF',
    reinvestment: 'Registrar reinvestimento',
    expense: 'Registrar despesa do caixa',
    damage: 'Registrar avaria',
    notice: 'Novo aviso',
    payment: 'Registrar pagamento',
    plate: 'Editar plaquinha',
};
const title = computed(() => {
    switch (dialog.value?.kind) {
        case 'company': return company.value?.name;
        case 'manufacturer': return manufacturer.value?.name;
        case 'manufacturer-form': return manufacturer.value ? 'Editar fabricante' : 'Novo fabricante';
        case 'company-form': return company.value ? 'Editar empresa' : 'Nova empresa';
        case 'unit': return unit.value ? 'Editar unidade' : 'Nova unidade';
        case 'sale': return sale.value ? 'Editar venda' : 'Nova venda';
        case 'batch': return batch.value ? 'Editar lote' : dialog.value.initial ? 'Registrar lote inicial' : 'Novo lote';
        default: return titles[dialog.value?.kind];
    }
});

const historyVerb = { created: 'Cadastrado', updated: 'Atualizado', deleted: 'Excluído', revoked: 'Revogado' };
const historyKind = { manufacturer: 'Fabricante', company: 'Empresa', notice: 'Aviso', production: 'Fabricação', initial_production: 'Lote inicial', reinvestment: 'Reinvestimento', expense: 'Despesa', damage: 'Avaria', share_link: 'Link compartilhado' };
const datetime = text => new Date(text).toLocaleString('pt-BR');
const deleteDescriptions = {
    company: 'A empresa, suas unidades, vendas, pagamentos e plaquinhas deixarão de aparecer no painel.',
    unit: 'A unidade será removida. Se houver vendas vinculadas, a operação será impedida.',
    sale: 'A venda, os pagamentos e as plaquinhas vinculadas serão removidos dos indicadores.',
    plate: 'A plaquinha será removida e os indicadores de vendas e defeitos serão recalculados.',
    payment: 'O pagamento será removido e o valor a receber será recalculado.',
    batch: 'O lote será removido. Se estiver vinculado a unidades, a operação será impedida.',
    manufacturer: 'O fabricante e suas imagens serão removidos. Lotes vinculados podem impedir a exclusão.',
    image: 'Esta imagem será removida permanentemente da galeria do fabricante.',
    notice: 'O aviso será excluído da lista de pendências.',
    reinvestment: 'Esta retirada será removida e o dinheiro voltará a aparecer no saldo disponível do caixa NFC.',
    expense: 'Esta despesa será removida e o valor retornará ao saldo disponível do caixa.',
    damage: 'Esta avaria será removida e os indicadores de defeitos serão recalculados.',
    'share-link': 'O link deixará de permitir acesso imediatamente, inclusive para quem já o recebeu.',
};
const confirmDeleteLabel = computed(() => dialog.value?.deleteKind === 'share-link' ? 'Revogar link' : 'Excluir registro');
function destroy(kind, context = {}) {
    if ((kind === 'reinvestment' && props.shared) || (kind !== 'share-link' && !props.permissions.delete)) return;
    if (busy.value || shareBusy.value || !Object.hasOwn(deleteDescriptions, kind)) return;
    dialog.value = { kind: 'confirm-delete', deleteKind: kind, deleteContext: context, previous: dialog.value };
}
function confirmDestroy() {
    if (busy.value || dialog.value?.kind !== 'confirm-delete') return;
    const { deleteKind: kind, deleteContext: context } = dialog.value;
    const routeNames = {
        company: ['nfc.companies.destroy', { company: context.companyId }],
        unit: ['nfc.units.destroy', { company: context.companyId, unit: context.unitId }],
        sale: ['nfc.sales.destroy', { company: context.companyId, sale: context.saleId }],
        plate: ['nfc.plates.destroy', { company: context.companyId, sale: context.saleId, plate: context.plateId }],
        payment: ['nfc.payments.destroy', { company: context.companyId, sale: context.saleId, payment: context.paymentId }],
        batch: ['nfc.production.destroy', { production: context.batchId }],
        manufacturer: ['nfc.manufacturers.destroy', { manufacturer: context.manufacturerId }],
        image: ['nfc.manufacturers.images.destroy', { manufacturer: context.manufacturerId, image: context.imageId }],
        notice: ['nfc.notices.destroy', { notice: context.noticeId }],
        reinvestment: ['nfc.reinvestments.destroy', { reinvestment: context.reinvestmentId }],
        expense: ['nfc.expenses.destroy', { expense: context.expenseId }],
        damage: ['nfc.damages.destroy', { damage: context.damageId }],
        'share-link': ['nfc.share-links.destroy', { link: context.linkId }],
    };
    const target = routeNames[kind];
    if (!target) return;
    const endpoint = ['share-link', 'reinvestment', 'expense'].includes(kind) ? route(target[0], target[1]) : nfcRoute(target[0], target[1]);
    router.delete(endpoint, {
        preserveScroll: true,
        onStart: () => { busy.value = true; },
        onFinish: () => { busy.value = false; },
        onSuccess: () => {
            dialog.value = ['company', 'manufacturer'].includes(kind) ? null : dialog.value?.previous ?? null;
            notify({ message: kind === 'share-link' ? 'Link revogado.' : 'Registro excluído. Listas e indicadores atualizados.' });
        },
        onError: errors => reportError(errors, 'Não foi possível concluir esta ação. Confira os vínculos e tente novamente.'),
    });
}

function showCompany(companyId, saleId = null) {
    dialog.value = { kind: 'company', companyId, saleId, previous: dialog.value?.kind === 'notices' ? dialog.value : null };
}

function edit(kind, context = {}) {
    const isUpdate = Boolean(context.unitId && kind === 'unit' || context.saleId && kind === 'sale' || context.batchId || context.manufacturerId || context.companyId && kind === 'company-form' || kind === 'plate');
    if (!props.permissions[isUpdate ? 'update' : 'create']) return;
    dialog.value = { kind, ...context, previous: dialog.value };
}

function goBack() {
    if (busy.value) return;
    dialog.value = dialog.value?.previous ?? null;
}

function saved(message = 'Registro salvo.') {
    if (dialog.value?.kind === 'company-form' && !company.value) {
        filter.value = 'all';
        search.value = '';
        activeDashboardTab.value = 'registry';
        activeRegistryTab.value = 'companies';
    }
    if (dialog.value?.kind === 'manufacturer-form') {
        manufacturerSearch.value = '';
        activeDashboardTab.value = 'registry';
        activeRegistryTab.value = 'manufacturers';
    }
    if (dialog.value?.kind === 'batch') {
        productionSearch.value = '';
        activeDashboardTab.value = 'registry';
        activeRegistryTab.value = 'production';
    }
    busy.value = false;
    goBack();
    notify({ message });
}

function closeDialog() {
    if (!busy.value) dialog.value = null;
}

watch(() => props.created_share_url, value => {
    if (value && !props.shared && props.share_links_enabled && authenticatedUser.value) dialog.value = { kind: 'share' };
}, { immediate: true });

watch(dialog, async (current, previous) => {
    if (!current || !previous) return;
    await nextTick();
    dialogHeading.value?.focus();
});
</script>

<template>
    <component :is="shared ? NfcSharedLayout : AuthenticatedLayout">
        <Head title="Plaquinhas NFC" />
        <div v-if="feedback" class="fixed bottom-4 left-4 right-4 z-[90] mx-auto w-[calc(100%-2rem)] max-w-sm rounded-xl border p-3 shadow-lg sm:bottom-6 sm:left-auto sm:right-6 sm:mx-0"
            :class="feedback.type === 'error' ? 'nfc-feedback-error' : 'nfc-feedback-success'"
            :role="feedback.type === 'error' ? 'alert' : 'status'" aria-live="polite">
            <div class="flex items-start gap-2">
                <p class="min-w-0 flex-1 break-words text-sm font-medium">{{ feedback.message }}</p>
                <button type="button" class="shrink-0 rounded-md p-1 hover:opacity-75" aria-label="Fechar mensagem" @click="feedback = null">
                    <X :size="16" aria-hidden="true" />
                </button>
            </div>
        </div>

        <div class="flex min-w-0 flex-col gap-6 sm:gap-8">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Plaquinhas NFC <span v-if="read_only" class="nfc-muted block text-xs font-medium">Acesso somente para consulta</span></h1>
                <div v-if="!schema_pending" class="flex flex-wrap items-center gap-2">
                    <div v-if="!shared && authenticatedUser" class="relative" @keydown.esc.stop="closeSettings" @focusout="event => { if (!event.currentTarget.contains(event.relatedTarget)) settingsOpen = false; }">
                        <button ref="settingsButton" type="button" class="nfc-button nfc-icon-button" aria-label="Configurações NFC" :aria-expanded="settingsOpen" aria-controls="nfc-owner-settings" @click="settingsOpen = !settingsOpen"><EllipsisVertical :size="19" aria-hidden="true" /></button>
                        <button v-if="settingsOpen" type="button" class="fixed inset-0 z-[95] cursor-default bg-transparent sm:hidden" aria-label="Fechar menu de configurações" @click="closeSettings"></button>
                        <div v-if="settingsOpen" id="nfc-owner-settings" class="nfc-panel fixed inset-x-4 bottom-4 z-[100] flex max-h-[70dvh] min-w-0 flex-col gap-1 overflow-y-auto p-2 shadow-xl sm:absolute sm:inset-x-auto sm:bottom-auto sm:right-0 sm:top-full sm:z-30 sm:mt-2 sm:w-48 sm:p-1 sm:shadow-lg">
                            <button type="button" class="nfc-button w-full justify-start" @click="openSettingsAction('share')"><Share2 :size="16" aria-hidden="true" /> Compartilhar</button>
                            <button type="button" class="nfc-button w-full justify-start" :disabled="schema_pending" @click="openSettingsAction('report')"><FileDown :size="16" aria-hidden="true" /> Exportar PDF</button>
                            <button type="button" class="nfc-button nfc-danger w-full justify-start" :disabled="schema_pending || busy || shareBusy" @click="openSettingsAction('reset')"><Trash2 :size="16" aria-hidden="true" /> Zerar</button>
                        </div>
                    </div>
                    <button type="button" class="nfc-button nfc-icon-button" :aria-label="hiddenValues ? 'Mostrar valores' : 'Ocultar valores'" :aria-pressed="hiddenValues" @click="togglePrivacy"><EyeOff v-if="hiddenValues" :size="18" aria-hidden="true" /><Eye v-else :size="18" aria-hidden="true" /></button>
                    <button v-if="!shared" type="button" class="nfc-button" @click="dialog = { kind: 'history' }"><History :size="17" aria-hidden="true" /> Histórico</button>
                    <button type="button" class="nfc-button nfc-icon-button" :aria-label="`Avisos (${noticeCount})`" @click="dialog = { kind: 'notices' }">
                        <Bell :size="18" aria-hidden="true" />
                        <span v-if="noticeCount" class="nfc-badge nfc-warning tabular-nums" aria-hidden="true">{{ noticeCount }}</span>
                    </button>
                </div>
            </header>

            <p v-if="schema_pending" class="nfc-panel nfc-warning p-4 text-sm" role="status">Aplique a migration de Plaquinhas NFC para liberar os cadastros.</p>

            <template v-else>
                <nav role="tablist" aria-label="Áreas do painel NFC" class="nfc-panel grid grid-cols-3 gap-1 p-1">
                    <button v-for="tab in dashboardTabs" :id="'nfc-dashboard-tab-' + tab.key" :key="tab.key" type="button" role="tab"
                        :aria-controls="'nfc-dashboard-panel-' + tab.key"
                        :aria-selected="activeDashboardTab === tab.key" :tabindex="activeDashboardTab === tab.key ? 0 : -1"
                        class="nfc-dashboard-tab min-h-12 min-w-0 rounded-xl border px-2 py-2 text-center text-xs font-semibold sm:text-sm"
                        @keydown="onDashboardTabKeydown($event, tab.key)" @click="activeDashboardTab = tab.key">
                        {{ tab.label }}
                    </button>
                </nav>
                <section v-show="activeDashboardTab === 'finance'" id="nfc-dashboard-panel-finance" role="tabpanel" aria-labelledby="nfc-dashboard-tab-finance" tabindex="0" class="grid min-w-0 gap-3 lg:grid-cols-2">
                    <section class="nfc-panel min-w-0 overflow-hidden lg:col-span-2" aria-labelledby="nfc-finance-heading">
                        <header class="nfc-rule border-b px-4 py-3">
                            <h2 id="nfc-finance-heading" class="text-sm font-semibold">Resumo financeiro</h2>
                            <p class="nfc-muted mt-0.5 text-xs">Valores acumulados das vendas e da fabricação.</p>
                        </header>
                        <dl class="nfc-finance-strip">
                            <div v-for="metric in metricGroups[0].metrics.slice(0, 3)" :key="metric.label" class="nfc-finance-cell">
                                <dt class="nfc-muted text-xs font-medium">{{ metric.label }}</dt>
                                <dd class="break-words text-xl font-semibold tabular-nums sm:text-2xl" :class="metric.tone">{{ metric.value }}</dd>
                            </div>
                        </dl>
                        <div class="nfc-rule border-t px-4 py-3">
                            <div class="mb-2 flex flex-wrap items-center justify-between gap-2 text-xs">
                                <span class="font-semibold">Recebimento das vendas</span>
                                <span v-if="!hiddenValues && financialSummary.revenue > 0" class="tabular-nums text-[var(--fs-success)]">{{ financialSummary.receivedPercent }}% recebido</span>
                            </div>
                            <div v-if="!hiddenValues && financialSummary.revenue > 0" class="flex h-2.5 overflow-hidden rounded-full bg-[var(--fs-surface-subtle)]" aria-hidden="true">
                                <div class="bg-[var(--fs-success)]" :style="{ width: financialSummary.receivedPercent + '%' }"></div>
                                <div class="bg-[var(--fs-warning)]" :style="{ width: financialSummary.outstandingPercent + '%' }"></div>
                            </div>
                            <p v-else class="nfc-muted text-xs">{{ hiddenValues ? 'Valores ocultos.' : 'Registre uma venda para acompanhar os recebimentos.' }}</p>
                            <p v-if="!hiddenValues && financialSummary.revenue > 0" class="nfc-muted mt-2 text-xs">{{ financialSummary.outstandingPercent }}% a receber.</p>
                        </div>
                        <div class="nfc-rule border-t">
                            <h3 class="nfc-muted bg-[var(--fs-surface-subtle)] px-4 py-2 text-xs font-semibold">Custos e perdas</h3>
                            <dl>
                                <div v-for="metric in metricGroups[0].metrics.slice(3)" :key="metric.label" class="nfc-rule grid grid-cols-[minmax(0,1fr)_minmax(0,auto)] items-center gap-3 border-t px-4 py-3">
                                    <dt class="min-w-0 text-sm font-medium">{{ metric.label }}<span v-if="metric.detail" class="nfc-muted mt-0.5 block text-xs font-normal">{{ metric.detail }}</span></dt>
                                    <dd class="max-w-[12rem] break-words text-right text-base font-semibold tabular-nums" :class="metric.tone">{{ metric.value }}</dd>
                                </div>
                            </dl>
                        </div>
                    </section>
                    <section class="nfc-panel min-w-0 overflow-hidden lg:col-span-2" aria-label="Avarias das plaquinhas">
                        <header class="nfc-rule flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
                            <div>
                                <h2 class="text-sm font-semibold">Avarias e defeitos</h2>
                                <p class="nfc-muted text-xs">Registre problemas na fabricação ou em uma plaquinha já vendida. A perda é estimativa, não retirada de caixa.</p>
                            </div>
                            <button v-if="permissions.create" type="button" class="nfc-button nfc-primary w-full sm:w-auto" @click="openDamage"><Plus :size="16" aria-hidden="true" /> Adicionar avaria</button>
                        </header>
                        <div class="p-4">
                            <p class="text-sm">Defeituosas: <strong>{{ summary.defective ?? 0 }}</strong> · Possível prejuízo: <strong>{{ money(summary.estimated_loss_cents ?? 0) }}</strong></p>
                            <ul v-if="damages.length" class="nfc-scrollbar mt-3 max-h-52 space-y-2 overflow-y-auto">
                                <li v-for="entry in damages" :key="entry.id" class="nfc-panel flex flex-wrap items-center justify-between gap-2 p-3 text-sm">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium">{{ entry.quantity }} com defeito · Lote #{{ entry.production_id }}</p>
                                        <p class="nfc-muted break-words text-xs">{{ date(entry.occurred_on) }} · {{ entry.reason }}</p>
                                    </div>
                                    <span class="tabular-nums">{{ entry.estimated_loss_cents == null ? 'Não estimado' : money(entry.estimated_loss_cents) }}</span>
                                    <button v-if="permissions.delete" type="button" class="nfc-button nfc-icon-button nfc-danger" :aria-label="'Excluir avaria do lote ' + entry.production_id" @click="destroy('damage', { damageId: entry.id })"><Trash2 :size="16" aria-hidden="true" /></button>
                                </li>
                            </ul>
                            <p v-else class="nfc-muted mt-2 text-xs">Nenhuma avaria de fabricação cadastrada. Defeitos de plaquinhas vendidas aparecem na empresa correspondente.</p>
                        </div>
                    </section>
                    <section class="nfc-panel min-w-0 overflow-hidden lg:col-span-2" aria-label="Caixa e reinvestimentos NFC">
                        <header class="nfc-rule flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
                            <div class="min-w-0">
                                <h2 class="text-sm font-semibold">Caixa das plaquinhas</h2>
                                <p class="nfc-muted mt-0.5 text-xs">Dinheiro recebido que pode ser usado para produzir mais plaquinhas. Não representa lucro.</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button v-if="!shared && permissions.create" class="nfc-button" type="button" @click="openReinvestment"><Plus :size="16" aria-hidden="true" /> Reinvestimento</button>
                                <button v-if="!shared && permissions.create" class="nfc-button nfc-primary" type="button" @click="openExpense"><Plus :size="16" aria-hidden="true" /> Despesa</button>
                            </div>
                        </header>
                        <dl class="nfc-finance-strip">
                            <div class="nfc-finance-cell">
                                <dt class="nfc-muted text-xs font-medium">Recebido</dt>
                                <dd class="break-words text-xl font-semibold tabular-nums sm:text-2xl">{{ money(summary.received_cents ?? 0) }}</dd>
                            </div>
                            <div class="nfc-finance-cell">
                                <dt class="nfc-muted text-xs font-medium">Reinvestido</dt>
                                <dd class="break-words text-xl font-semibold tabular-nums text-[var(--fs-warning)] sm:text-2xl">{{ money(summary.reinvested_cents ?? 0) }}</dd>
                            </div>
                            <div class="nfc-finance-cell">
                                <dt class="nfc-muted text-xs font-medium">Outras despesas</dt>
                                <dd class="break-words text-xl font-semibold tabular-nums text-[var(--fs-warning)] sm:text-2xl">{{ money(summary.expenses_cents ?? 0) }}</dd>
                            </div>
                            <div class="nfc-finance-cell">
                                <dt class="nfc-muted text-xs font-medium">Disponível no caixa</dt>
                                <dd class="break-words text-xl font-semibold tabular-nums" :class="(summary.cash_available_cents ?? 0) < 0 ? 'text-[var(--fs-danger)]' : 'text-[var(--fs-success)]'">{{ money(summary.cash_available_cents ?? 0) }}</dd>
                            </div>
                        </dl>
                        <div v-if="expenses.length" class="nfc-rule border-t px-4 py-3">
                            <h3 class="mb-2 text-xs font-semibold">Últimas despesas</h3>
                            <ul class="nfc-scrollbar max-h-64 space-y-2 overflow-y-auto">
                                <li v-for="entry in expenses" :key="entry.id" class="nfc-panel flex flex-wrap items-center justify-between gap-2 p-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="break-words text-sm font-medium">{{ entry.description }}</p>
                                        <p class="nfc-muted text-xs">{{ date(entry.spent_on) }} · {{ expenseCategories[entry.category] || entry.category }}<span v-if="entry.production_id"> · Lote #{{ entry.production_id }}</span><span v-if="entry.damage_id"> · Avaria #{{ entry.damage_id }}</span></p>
                                    </div>
                                    <strong class="text-sm tabular-nums text-[var(--fs-warning)]">{{ money(entry.amount_cents) }}</strong>
                                    <button v-if="!shared && permissions.delete" type="button" class="nfc-button nfc-icon-button nfc-danger" :aria-label="'Excluir despesa: ' + entry.description" @click="destroy('expense', { expenseId: entry.id })"><Trash2 :size="16" aria-hidden="true" /></button>
                                </li>
                            </ul>
                        </div>
                        <div v-if="reinvestments.length" class="nfc-rule border-t px-4 py-3">
                            <h3 class="mb-2 text-xs font-semibold">Últimos reinvestimentos</h3>
                            <ul class="nfc-scrollbar max-h-64 space-y-2 overflow-y-auto">
                                <li v-for="entry in reinvestments" :key="entry.id" class="nfc-panel flex min-w-0 flex-wrap items-center justify-between gap-2 p-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="break-words text-sm font-medium">{{ entry.description }}</p>
                                        <p class="nfc-muted text-xs">{{ date(entry.spent_on) }}<span v-if="entry.production_id"> · Lote #{{ entry.production_id }}</span></p>
                                    </div>
                                    <strong class="text-sm tabular-nums text-[var(--fs-warning)]">{{ money(entry.amount_cents) }}</strong>
                                    <button v-if="!shared && permissions.delete" type="button" class="nfc-button nfc-icon-button nfc-danger"
                                        :aria-label="'Excluir reinvestimento: ' + entry.description" @click="destroy('reinvestment', { reinvestmentId: entry.id })">
                                        <Trash2 :size="16" aria-hidden="true" />
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <p v-else class="nfc-muted nfc-rule border-t px-4 py-3 text-xs">Nenhum reinvestimento registrado. Os custos dos lotes não são retirados do caixa automaticamente.</p>
                    </section>
                    <section v-for="(group, index) in metricGroups.slice(1)" :key="group.title" class="nfc-panel min-w-0 overflow-hidden" :aria-labelledby="'nfc-operation-heading-' + index">
                        <header class="nfc-rule border-b px-4 py-3">
                            <h2 :id="'nfc-operation-heading-' + index" class="text-sm font-semibold">{{ group.title }}</h2>
                        </header>
                        <table class="nfc-metric-table w-full table-fixed border-collapse text-center" :aria-labelledby="'nfc-operation-heading-' + index">
                            <thead>
                                <tr>
                                    <th v-for="metric in group.metrics" :key="metric.label" scope="col">{{ metric.label }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td v-for="metric in group.metrics" :key="metric.label">
                                        <span class="block break-words text-lg font-semibold tabular-nums sm:text-xl" :class="metric.tone">{{ metric.value }}</span>
                                        <span v-if="metric.detail" class="nfc-muted mt-1 block break-words text-[11px] leading-tight">{{ metric.detail }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>
                </section>
                <section v-show="activeDashboardTab === 'evolution'" id="nfc-dashboard-panel-evolution" role="tabpanel" aria-labelledby="nfc-dashboard-tab-evolution" tabindex="0" class="min-w-0">
                    <PerformanceChart :hidden-values="hiddenValues" :companies="companies" :production="production" :today="today" />
                </section>
                <section v-show="activeDashboardTab === 'registry'" id="nfc-dashboard-panel-registry" role="tabpanel" aria-labelledby="nfc-dashboard-tab-registry" tabindex="0" class="flex min-w-0 flex-col gap-3">
            <div role="tablist" aria-label="Tipos de cadastro"
                class="nfc-rule flex w-full min-w-0 flex-wrap gap-1 border-b pb-1">
                <button id="nfc-tab-companies" type="button" role="tab" aria-controls="nfc-panel-companies"
                    :aria-selected="activeRegistryTab === 'companies'" :tabindex="activeRegistryTab === 'companies' ? 0 : -1"
                    @keydown="onRegistryTabKeydown($event, 'companies')"
                    class="inline-flex min-h-11 min-w-0 flex-1 flex-wrap items-center justify-center gap-x-2 gap-y-1 border-b-[3px] px-2 py-2 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 sm:flex-none sm:px-5" :class="activeRegistryTab === 'companies' ? 'border-[var(--fs-info)] text-[var(--fs-info)]' : 'border-transparent text-[var(--fs-text-secondary)] hover:bg-[var(--fs-surface-subtle)]'" @click="activeRegistryTab = 'companies'">
                    <Building2 :size="16" aria-hidden="true" /> Empresas
                    <span class="nfc-badge nfc-neutral tabular-nums">{{ companies.length }}</span>
                </button>
                <button id="nfc-tab-production" type="button" role="tab" aria-controls="nfc-panel-production"
                    :aria-selected="activeRegistryTab === 'production'" :tabindex="activeRegistryTab === 'production' ? 0 : -1"
                    @keydown="onRegistryTabKeydown($event, 'production')"
                    class="inline-flex min-h-11 min-w-0 flex-1 flex-wrap items-center justify-center gap-x-2 gap-y-1 border-b-[3px] px-2 py-2 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 sm:flex-none sm:px-5" :class="activeRegistryTab === 'production' ? 'border-[var(--fs-info)] text-[var(--fs-info)]' : 'border-transparent text-[var(--fs-text-secondary)] hover:bg-[var(--fs-surface-subtle)]'" @click="activeRegistryTab = 'production'">
                    <Factory :size="16" aria-hidden="true" /> Fabricação
                    <span class="nfc-badge nfc-neutral tabular-nums">{{ production.length }}</span>
                </button>
                <button id="nfc-tab-manufacturers" type="button" role="tab" aria-controls="nfc-panel-manufacturers"
                    :aria-selected="activeRegistryTab === 'manufacturers'" :tabindex="activeRegistryTab === 'manufacturers' ? 0 : -1"
                    @keydown="onRegistryTabKeydown($event, 'manufacturers')"
                    class="inline-flex min-h-11 min-w-0 flex-1 flex-wrap items-center justify-center gap-x-2 gap-y-1 border-b-[3px] px-2 py-2 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 sm:flex-none sm:px-5" :class="activeRegistryTab === 'manufacturers' ? 'border-[var(--fs-info)] text-[var(--fs-info)]' : 'border-transparent text-[var(--fs-text-secondary)] hover:bg-[var(--fs-surface-subtle)]'" @click="activeRegistryTab = 'manufacturers'">
                    <Factory :size="16" aria-hidden="true" /> Fabricantes
                    <span class="nfc-badge nfc-neutral tabular-nums">{{ manufacturers.length }}</span>
                </button>
            </div>

            <section v-show="activeRegistryTab === 'companies'" id="nfc-panel-companies" role="tabpanel" aria-labelledby="nfc-tab-companies" tabindex="0" class="flex min-w-0 flex-col gap-3">
                <div class="flex min-w-0 items-center justify-between gap-2">
                    <h2 id="nfc-companies" class="flex min-w-0 items-center gap-2 text-lg font-semibold"><Building2 :size="19" aria-hidden="true" /> Empresas</h2>
                    <button v-if="permissions.create" type="button" class="nfc-button nfc-primary shrink-0" @click="edit('company-form')">
                        <Plus :size="16" aria-hidden="true" /> Nova empresa
                    </button>
                </div>
                <div class="flex flex-wrap items-center gap-2" aria-label="Filtrar empresas" role="group">
                    <span class="nfc-muted text-xs font-semibold">Filtrar empresas:</span>
                    <button v-for="option in filters" :key="option.key" type="button" class="inline-flex min-h-10 items-center rounded-full border px-3 py-1.5 text-xs font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2" :class="filter === option.key ? 'border-[var(--fs-info)] bg-[var(--fs-info-soft)] text-[var(--fs-info)]' : 'border-[var(--fs-border-soft)] text-[var(--fs-text-secondary)] hover:bg-[var(--fs-surface-subtle)]'" :aria-pressed="filter === option.key" @click="filter = option.key">{{ option.label }}</button>
                </div>
                <label class="nfc-field max-w-xl">Buscar empresa, CPF/CNPJ, unidade, venda ou plaquinha
                    <input v-model.trim="search" type="search" class="nfc-control" placeholder="Digite para filtrar..." aria-label="Buscar no módulo NFC" />
                </label>
                <div v-if="unitRows.length" class="flex flex-col gap-3">
                    <ul class="nfc-scrollbar flex max-h-[min(58dvh,34rem)] flex-col gap-2 overflow-y-auto overscroll-contain pr-1 md:hidden" aria-label="Empresas NFC">
                        <li v-for="company in companiesShown" :key="company.id" class="nfc-panel min-w-0 overflow-hidden">
                            <button type="button" class="nfc-row flex w-full items-center justify-between gap-3 p-3 text-left" @click="showCompany(company.id)">
                                <span class="min-w-0">
                                    <span class="block truncate font-semibold">{{ company.name }}</span>
                                    <span class="nfc-muted block text-xs">{{ company.cnpj || 'Sem CPF/CNPJ' }} · {{ company.units.length }} {{ company.units.length === 1 ? 'unidade' : 'unidades' }}</span>
                                </span>
                                <ChevronRight :size="18" class="shrink-0" aria-hidden="true" />
                            </button>
                            <div class="nfc-rule border-t px-3 pb-3 pt-2">
                                <div class="grid grid-cols-3 gap-2 text-xs">
                                    <div class="min-w-0"><span class="nfc-muted block">Vendidas</span><strong class="tabular-nums">{{ company.sold }}</strong></div>
                                    <div class="min-w-0"><span class="nfc-muted block">Recebido</span><strong class="tabular-nums">{{ money(company.sales.reduce((sum, sale) => sum + sale.paid_cents, 0)) }}</strong></div>
                                    <div class="min-w-0"><span class="nfc-muted block">A receber</span><strong class="tabular-nums" :class="company.sales.some(sale => sale.balance_cents > 0) ? 'text-[var(--fs-warning)]' : ''">{{ money(company.sales.reduce((sum, sale) => sum + sale.balance_cents, 0)) }}</strong></div>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-1.5" aria-label="Unidades da empresa">
                                    <span v-if="!company.units.length" class="nfc-badge nfc-neutral">Sem unidade</span>
                                    <span v-for="unit in company.units" :key="unit.id" class="nfc-badge nfc-neutral">{{ unit.name }} · {{ unit.sold ?? 0 }} vendida(s) · {{ unit.usable_sold ?? 0 }} sem defeito</span>
                                </div>
                            </div>
                        </li>
                    </ul>
                    <div class="nfc-panel nfc-scrollbar hidden max-h-[min(58dvh,34rem)] min-w-0 overflow-auto overscroll-contain md:block">
                        <table class="w-full min-w-[780px] text-left text-sm">
                            <thead class="nfc-muted nfc-rule sticky top-0 z-10 border-b bg-[var(--fs-surface)] text-xs">
                                <tr><th class="px-4 py-3">Empresa / CPF-CNPJ</th><th class="px-3 py-3">Unidade</th><th class="px-3 py-3">Lote / Status</th><th class="px-3 py-3 text-right">Vendidas</th><th class="px-3 py-3 text-right">Valor</th><th class="px-3 py-3 text-right">Pago</th><th class="px-4 py-3 text-right">A receber</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in unitRows" :key="row.companyId + '-' + row.id" class="nfc-rule border-b last:border-b-0">
                                    <td class="px-4 py-3"><button type="button" class="font-semibold underline-offset-2 hover:underline" @click="showCompany(row.companyId)">{{ row.companyName }}</button><p class="nfc-muted text-xs">{{ row.identifier || '—' }}</p></td>
                                    <td class="px-3 py-3">{{ row.unitName }}</td>
                                    <td class="px-3 py-3"><span :class="row.productionStatus === 'in_progress' ? 'nfc-warning' : row.productionStatus ? 'nfc-success' : 'nfc-neutral'" class="nfc-badge">{{ !row.hasUnit ? 'Sem unidade' : row.productionStatus === 'in_progress' ? 'Em fabricação' : row.productionStatus ? 'Fabricado' : 'Sem lote' }}</span><span v-if="row.productionId" class="nfc-muted block text-xs">Lote #{{ row.productionId }}</span></td>
                                    <td class="px-3 py-3 text-right tabular-nums">{{ row.sold }}<span v-if="row.defective" class="nfc-muted block text-xs">{{ row.usableSold }} sem defeito · {{ row.defective }} avaria(s)</span></td>
                                    <td class="px-3 py-3 text-right tabular-nums">{{ money(row.total) }}</td>
                                    <td class="px-3 py-3 text-right tabular-nums">{{ money(row.received) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ money(row.outstanding) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <p v-else class="nfc-panel nfc-muted p-4 text-sm">{{ companies.length ? 'Nenhuma empresa neste filtro.' : 'Nenhuma empresa cadastrada.' }}</p>
            </section>

            <section v-show="activeRegistryTab === 'production'" id="nfc-panel-production" role="tabpanel" aria-labelledby="nfc-tab-production" tabindex="0" class="flex min-w-0 flex-col gap-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="nfc-production" class="flex items-center gap-2 text-lg font-semibold"><Factory :size="19" aria-hidden="true" /> Fabricação</h2>
                    <div class="flex flex-wrap gap-2">
                        <button v-if="permissions.create" type="button" class="nfc-button nfc-primary" @click="openDamage"><Plus :size="17" aria-hidden="true" /> Adicionar avaria</button>
                        <button v-if="permissions.create && !hasInitialBatch" type="button" class="nfc-button" @click="edit('batch', { initial: true })">Registrar lote inicial</button>
                        <button v-if="permissions.create" type="button" class="nfc-button" @click="edit('batch')"><Plus :size="17" aria-hidden="true" /> Novo lote</button>
                    </div>
                </div>
                <label class="nfc-field max-w-xl">Buscar lote, data, status, empresa ou unidade
                    <input v-model.trim="productionSearch" type="search" class="nfc-control" placeholder="Digite para filtrar a fabricação..." aria-label="Buscar lotes de fabricação" />
                </label>
                <ul v-if="productionShown.length" class="nfc-panel nfc-scrollbar max-h-[min(58dvh,34rem)] min-w-0 overflow-y-auto overscroll-contain">
                    <li v-for="item in productionShown" :key="item.id" class="nfc-rule flex min-w-0 flex-wrap items-center justify-between gap-3 border-b px-4 py-3 last:border-b-0">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold">{{ item.initial ? 'Lote inicial' : item.produced_on ? date(item.produced_on) : 'Lote sem data' }} · {{ item.quantity }} plaquinhas</p>
                            <span class="nfc-badge" :class="item.status === 'in_progress' ? 'nfc-warning' : 'nfc-success'">{{ item.status === 'in_progress' ? 'Em fabricação' : 'Fabricado' }}</span>
                            <p class="nfc-muted text-sm">{{ item.customized_quantity === null ? 'Tipos a informar' : `${item.quantity - item.customized_quantity} normais · ${item.customized_quantity} personalizadas` }}</p>
                            <p class="nfc-muted text-xs">{{ item.usable_quantity }} aproveitáveis · {{ item.damaged_quantity }} com defeito</p>
                            <p v-if="item.linkedUnits.length" class="nfc-muted mt-1 break-words text-xs">Vinculado a: {{ item.linkedUnits.join('; ') }}</p>
                        </div>
                        <span class="nfc-muted text-sm tabular-nums">{{ item.cost_cents === null ? 'Custo a informar' : money(item.cost_cents) }}</span>
                        <div class="flex flex-wrap gap-2">
                            <button v-if="permissions.create" type="button" class="nfc-button" @click="openDamageForBatch(item.id)">Registrar avaria</button>
                            <button v-if="permissions.update" type="button" class="nfc-button" :aria-label="`Editar lote #${item.id}`" @click="edit('batch', { batchId: item.id })">Editar</button>
                            <button v-if="permissions.delete" type="button" class="nfc-button nfc-danger" @click="destroy('batch', { batchId: item.id })">Excluir</button>
                        </div>
                    </li>
                </ul>
                <p v-else class="nfc-panel nfc-muted p-4 text-sm">{{ production.length ? 'Nenhum lote encontrado nesta busca.' : 'Nenhuma fabricação registrada.' }}</p>
            </section>
            <section v-show="activeRegistryTab === 'manufacturers'" id="nfc-panel-manufacturers" role="tabpanel" aria-labelledby="nfc-tab-manufacturers" tabindex="0" class="flex min-w-0 flex-col gap-3">
                <div class="flex min-w-0 items-center justify-between gap-2">
                    <h2 class="flex min-w-0 items-center gap-2 text-lg font-semibold"><Factory :size="19" aria-hidden="true" /> Fabricantes</h2>
                    <button v-if="permissions.create" type="button" class="nfc-button nfc-primary shrink-0" @click="edit('manufacturer-form')"><Plus :size="16" aria-hidden="true" /> Novo fabricante</button>
                </div>
                <label class="nfc-field max-w-xl">Buscar fabricante, contato ou unidade
                    <input v-model.trim="manufacturerSearch" type="search" class="nfc-control" placeholder="Digite para filtrar os fabricantes..." aria-label="Buscar fabricantes" />
                </label>
                <ul v-if="manufacturersShown.length" class="nfc-panel nfc-scrollbar max-h-[min(58dvh,34rem)] min-w-0 overflow-y-auto overscroll-contain">
                    <li v-for="item in manufacturersShown" :key="item.id" class="nfc-rule border-b last:border-b-0">
                        <button type="button" class="nfc-row flex w-full min-w-0 items-center justify-between gap-3 p-4 text-left" @click="dialog = { kind: 'manufacturer', manufacturerId: item.id }">
                            <span class="flex min-w-0 flex-col gap-1">
                                <span class="break-words font-semibold">{{ item.name }}</span>
                                <span class="nfc-muted text-xs">{{ item.phone || 'Sem contato' }} · {{ item.images.length }}/10 imagens</span>
                                <span class="nfc-muted text-sm">{{ item.statistics.batches }} lotes · {{ item.statistics.manufactured }} fabricadas · {{ item.statistics.units }} unidades</span>
                                <span v-if="item.statistics.outstanding_cents" class="nfc-badge nfc-warning">A receber nas unidades: {{ money(item.statistics.outstanding_cents) }}</span>
                            </span>
                            <ChevronRight :size="18" class="shrink-0" aria-hidden="true" />
                        </button>
                    </li>
                </ul>
                <p v-else class="nfc-panel nfc-muted p-4 text-sm">{{ manufacturers.length ? 'Nenhum fabricante neste filtro.' : 'Nenhum fabricante cadastrado. Cadastre um para anexar imagens e vincular lotes.' }}</p>
            </section>
                </section>
            </template>
        </div>

        <Modal class="nfc-dialog" :show="dialog !== null" labelled-by="nfc-dialog-heading" :closeable="!busy" @close="closeDialog">
            <section v-if="dialog" class="nfc-dialog-content flex min-w-0 flex-col gap-4 p-4 sm:gap-5 sm:p-6">
                <header class="flex min-w-0 items-start gap-2">
                    <button v-if="dialog.previous" type="button" class="nfc-button nfc-icon-button shrink-0" aria-label="Voltar" :disabled="busy" @click="goBack"><ArrowLeft :size="18" aria-hidden="true" /></button>
                    <h2 id="nfc-dialog-heading" ref="dialogHeading" tabindex="-1" class="min-w-0 flex-1 self-center text-xl font-semibold tracking-tight [overflow-wrap:anywhere]">{{ title }}</h2>
                    <button type="button" class="nfc-button nfc-icon-button shrink-0" aria-label="Fechar" :disabled="busy" @click="closeDialog"><X :size="18" aria-hidden="true" /></button>
                </header>

                <CompanyDetails v-if="dialog.kind === 'company' && company" :company="company" :permissions="permissions" :hidden-values="hiddenValues" :highlight-sale-id="dialog.saleId" @edit="edit" @destroy="destroy" />
                <ManufacturerDetails v-else-if="dialog.kind === 'manufacturer' && manufacturer" :manufacturer="manufacturer" :permissions="permissions" :hidden-values="hiddenValues" :nfc-route="nfcRoute" @edit="edit" @destroy="destroy" @request-delete-image="image => destroy('image', { manufacturerId: manufacturer.id, imageId: image.id })" @busy="busy = $event" @feedback="notify" />
                <div v-else-if="dialog.kind === 'confirm-delete'" class="flex min-w-0 flex-col gap-4">
                    <p class="text-sm leading-6">{{ deleteDescriptions[dialog.deleteKind] }}</p>
                    <p class="nfc-muted text-sm">Esta ação pode afetar indicadores ou registros vinculados. Confira antes de continuar.</p>
                    <div class="nfc-rule flex flex-wrap justify-end gap-2 border-t pt-4">
                        <button type="button" class="nfc-button" :disabled="busy" @click="goBack">Cancelar</button>
                        <button type="button" class="nfc-button nfc-danger" :disabled="busy" @click="confirmDestroy">{{ busy ? 'Processando…' : confirmDeleteLabel }}</button>
                    </div>
                </div>
                <form v-else-if="dialog.kind === 'damage'" class="flex min-w-0 flex-col gap-4" @submit.prevent="saveDamage">
                    <label class="nfc-field">Onde ocorreu a avaria?
                        <select v-model="damageForm.target" class="nfc-control" :disabled="busy">
                            <option value="production">Em um lote de fabricação</option>
                            <option value="plate">Em uma plaquinha já cadastrada</option>
                        </select>
                    </label>
                    <label v-if="damageForm.target === 'production'" class="nfc-field">Lote de fabricação
                        <select v-model="damageForm.production_id" class="nfc-control" required :disabled="busy">
                            <option value="" disabled>Selecione o lote</option>
                            <option v-for="item in production" :key="item.id" :value="item.id">Lote #{{ item.id }} · {{ item.usable_quantity }} de {{ item.quantity }} sem avaria</option>
                        </select>
                    </label>
                    <template v-else>
                        <label class="nfc-field">Empresa
                            <select v-model="damageForm.company_id" class="nfc-control" required :disabled="busy" @change="damageForm.sale_id = ''; damageForm.plate_id = ''">
                                <option value="" disabled>Selecione a empresa</option>
                                <option v-for="item in damageCompanies" :key="item.id" :value="item.id">{{ item.name }}</option>
                            </select>
                        </label>
                        <label class="nfc-field">Venda
                            <select v-model="damageForm.sale_id" class="nfc-control" required :disabled="busy" @change="damageForm.plate_id = ''">
                                <option value="" disabled>Selecione a venda</option>
                                <option v-for="item in damageSales" :key="item.id" :value="item.id">{{ damageCompany?.units.find(unit => unit.id === item.unit_id)?.name ?? 'Unidade' }} · {{ date(item.sold_on) }} · {{ item.plates.length }} plaquinhas</option>
                            </select>
                        </label>
                        <label class="nfc-field">Plaquinha
                            <select v-model="damageForm.plate_id" class="nfc-control" required :disabled="busy">
                                <option value="" disabled>Selecione a plaquinha</option>
                                <option v-for="item in damagePlates" :key="item.id" :value="item.id">{{ item.label || (item.type === 'custom' ? 'Personalizada' : 'Normal') }}</option>
                            </select>
                        </label>
                    </template>
                    <label v-if="damageForm.target === 'production'" class="nfc-field">Quantidade com defeito
                        <input v-model.number="damageForm.quantity" type="number" min="1" :max="Math.min(10000, damageBatch?.usable_quantity ?? 0)" class="nfc-control" required :disabled="busy" />
                    </label>
                    <label class="nfc-field">Data da avaria
                        <input v-model="damageForm.occurred_on" type="date" :max="today" class="nfc-control" required :disabled="busy" />
                    </label>
                    <label class="nfc-field">Descrição do defeito
                        <input v-model.trim="damageForm.reason" maxlength="160" class="nfc-control" required placeholder="Ex.: NFC não funciona, arte danificada" :disabled="busy" />
                    </label>
                    <label class="nfc-field">Possível prejuízo total (R$, opcional)
                        <input v-model.trim="damageAmount" :type="hiddenValues ? 'password' : 'text'" inputmode="decimal" class="nfc-control" placeholder="Ex.: 15,00" :disabled="busy" />
                    </label>
                    <p class="nfc-muted text-xs">A avaria reduz a quantidade aproveitável, não apaga a venda nem o pagamento. Se houver desconto real ao cliente, ajuste o valor da venda; não duplique custos na estimativa.</p>
                    <p v-for="(error, field) in damageForm.errors" :key="field" class="text-xs text-[var(--fs-danger)]" role="alert">{{ error }}</p>
                    <div class="nfc-rule flex flex-wrap justify-end gap-2 border-t pt-4">
                        <button type="button" class="nfc-button" :disabled="busy" @click="closeDialog">Cancelar</button>
                        <button type="submit" class="nfc-button nfc-primary" :disabled="busy || damageForm.processing">{{ busy ? 'Registrando…' : 'Registrar avaria' }}</button>
                    </div>
                </form>
                <form v-else-if="dialog.kind === 'expense' && !shared && authenticatedUser" class="flex min-w-0 flex-col gap-4" @submit.prevent="saveExpense">
                    <p class="nfc-muted text-sm">Registre gastos efetivamente pagos com o dinheiro recebido, como fita dupla face, tesoura e reposições por avaria.</p>
                    <label class="nfc-field">Tipo de despesa
                        <select v-model="expenseForm.category" class="nfc-control" :disabled="busy" @change="expenseForm.category !== 'rework' && (expenseForm.damage_id = '')">
                            <option v-for="(label, key) in expenseCategories" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </label>
                    <label class="nfc-field">Descrição
                        <input v-model.trim="expenseForm.description" class="nfc-control" maxlength="160" required placeholder="Ex.: Fita dupla face para novas plaquinhas" :disabled="busy" />
                    </label>
                    <label class="nfc-field">Valor gasto (R$)
                        <input v-model.trim="expenseAmount" :type="hiddenValues ? 'password' : 'text'" class="nfc-control" inputmode="decimal" placeholder="Ex.: 12,50" required :disabled="busy" />
                    </label>
                    <label class="nfc-field">Data
                        <input v-model="expenseForm.spent_on" type="date" :max="today" class="nfc-control" required :disabled="busy" />
                    </label>
                    <label class="nfc-field">Lote relacionado (opcional)
                        <select v-model="expenseForm.production_id" class="nfc-control" :disabled="busy">
                            <option value="">Sem lote específico</option>
                            <option v-for="item in production" :key="item.id" :value="item.id">Lote #{{ item.id }} · {{ item.quantity }} unidades</option>
                        </select>
                    </label>
                    <label v-if="expenseForm.category === 'rework'" class="nfc-field">Avaria de fabricação relacionada (opcional)
                        <select v-model="expenseForm.damage_id" class="nfc-control" :disabled="busy">
                            <option value="">Sem avaria vinculada</option>
                            <option v-for="item in damages" :key="item.id" :value="item.id">Avaria #{{ item.id }} · {{ item.reason }}</option>
                        </select>
                    </label>
                    <p class="nfc-muted text-xs">Saldo disponível: {{ money(summary.cash_available_cents ?? 0) }}. Uma despesa é saída de caixa; não altera automaticamente o custo de um lote nem duplica a estimativa de prejuízo.</p>
                    <p v-for="(error, field) in expenseForm.errors" :key="field" class="text-xs text-[var(--fs-danger)]" role="alert">{{ error }}</p>
                    <div class="nfc-rule flex flex-wrap justify-end gap-2 border-t pt-4">
                        <button type="button" class="nfc-button" :disabled="busy" @click="closeDialog">Cancelar</button>
                        <button type="submit" class="nfc-button nfc-primary" :disabled="busy || expenseForm.processing">{{ busy ? 'Registrando…' : 'Registrar despesa' }}</button>
                    </div>
                </form>
                <form v-else-if="dialog.kind === 'reinvestment' && !shared && authenticatedUser" class="flex min-w-0 flex-col gap-4" @submit.prevent="saveReinvestment">
                    <p class="nfc-muted text-sm">Registre o dinheiro que saiu do caixa das vendas para fabricar novas plaquinhas. Não é aporte nem um segundo custo de fabricação.</p>
                    <label class="nfc-field">Valor retirado (R$)
                        <input v-model.trim="reinvestmentAmount" :type="hiddenValues ? 'password' : 'text'" inputmode="decimal" pattern="[0-9]+([.,][0-9]{1,2})?" class="nfc-control" required placeholder="Ex.: 75,50" autocomplete="off" :disabled="reinvestmentForm.processing" />
                        <span v-if="reinvestmentForm.errors.amount_cents" role="alert" class="text-xs text-[var(--fs-danger)]">{{ reinvestmentForm.errors.amount_cents }}</span>
                    </label>
                    <label class="nfc-field">Data da retirada
                        <input v-model="reinvestmentForm.spent_on" type="date" class="nfc-control" required :max="today" :disabled="reinvestmentForm.processing" />
                        <span v-if="reinvestmentForm.errors.spent_on" role="alert" class="text-xs text-[var(--fs-danger)]">{{ reinvestmentForm.errors.spent_on }}</span>
                    </label>
                    <label class="nfc-field">Para que foi utilizado?
                        <input v-model.trim="reinvestmentForm.description" type="text" maxlength="160" class="nfc-control" required placeholder="Ex.: Produção personalizada para loja" :disabled="reinvestmentForm.processing" />
                        <span v-if="reinvestmentForm.errors.description" role="alert" class="text-xs text-[var(--fs-danger)]">{{ reinvestmentForm.errors.description }}</span>
                    </label>
                    <label class="nfc-field">Lote de fabricação (opcional)
                        <select v-model="reinvestmentForm.production_id" class="nfc-control" :disabled="reinvestmentForm.processing">
                            <option value="">Sem lote vinculado</option>
                            <option v-for="item in production" :key="item.id" :value="item.id">Lote #{{ item.id }} · {{ item.quantity }} plaquinhas</option>
                        </select>
                        <span v-if="reinvestmentForm.errors.production_id" role="alert" class="text-xs text-[var(--fs-danger)]">{{ reinvestmentForm.errors.production_id }}</span>
                    </label>
                    <p v-if="reinvestmentForm.errors.operation_id" role="alert" class="text-xs text-[var(--fs-danger)]">{{ reinvestmentForm.errors.operation_id }}</p>
                    <p class="nfc-muted text-xs">Disponível agora: {{ money(summary.cash_available_cents ?? 0) }}. Vincular um lote não altera seu custo de fabricação já registrado.</p>
                    <div class="nfc-rule flex flex-wrap justify-end gap-2 border-t pt-4">
                        <button type="button" class="nfc-button" :disabled="reinvestmentForm.processing" @click="closeDialog">Cancelar</button>
                        <button type="submit" class="nfc-button nfc-primary" :disabled="reinvestmentForm.processing || busy">
                            {{ reinvestmentForm.processing ? 'Registrando…' : 'Registrar retirada' }}
                        </button>
                    </div>
                </form>
                <div v-else-if="dialog.kind === 'report' && !shared && authenticatedUser" class="flex min-w-0 flex-col gap-4">
                    <p class="nfc-muted text-sm">Escolha o período e os indicadores. O arquivo contém somente dados do seu plugin NFC.</p>
                    <label class="nfc-field">Nome do relatório / arquivo (opcional)
                        <input v-model.trim="reportName" type="text" class="nfc-control" maxlength="80" placeholder="Ex.: Vendas NFC - Novembro 2026" autocomplete="off" />
                        <span class="nfc-muted text-xs font-normal">Será usado como título do PDF e nome do arquivo baixado. Se ficar vazio, o nome será automático.</span>
                        <span v-if="reportName && !/^[\p{L}\p{N}\s._-]*$/u.test(reportName)" class="text-xs text-[var(--fs-danger)]">Use apenas letras, números, espaços, ponto, hífen ou sublinhado.</span>
                    </label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="nfc-field">Data inicial<input v-model="reportFrom" type="date" class="nfc-control" :max="reportTo || undefined" /></label>
                        <label class="nfc-field">Data final<input v-model="reportTo" type="date" class="nfc-control" :min="reportFrom || undefined" /></label>
                    </div>
                    <fieldset class="nfc-panel flex flex-col gap-3 p-3">
                        <legend class="px-1 text-sm font-semibold">Indicadores do relatório</legend>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="nfc-button text-xs" @click="reportMetrics = reportOptions.map(item => item.key)">Selecionar todos</button>
                            <button type="button" class="nfc-button text-xs" @click="reportMetrics = []">Limpar seleção</button>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label v-for="item in reportOptions" :key="item.key" class="flex items-start gap-2 text-sm"><input v-model="reportMetrics" type="checkbox" :value="item.key" />{{ item.label }}</label>
                        </div>
                    </fieldset>
                    <label class="flex items-center gap-2 text-sm"><input v-model="reportChart" type="checkbox" /> Incluir gráficos comparativos quando aplicável</label>
                    <label class="flex min-h-11 items-center gap-2 text-sm"><input v-model="reportReinvestments" type="checkbox" /> Incluir demonstrativo de caixa, despesas e reinvestimentos no PDF</label>
                    <p class="nfc-muted text-xs">A margem compara vendas e custos de fabricação no intervalo; não representa lucro contábil. O saldo a receber é o saldo atual das vendas do período. Quando selecionado, o demonstrativo de caixa usa recebimentos, reinvestimentos e despesas com data no período.</p>
                    <p v-if="hiddenValues" class="nfc-warning rounded-lg p-3 text-sm">O PDF inclui os valores reais dos indicadores selecionados, mesmo com o olho fechado.</p>
                    <div class="nfc-rule flex flex-wrap justify-end gap-2 border-t pt-4">
                        <button type="button" class="nfc-button" @click="closeDialog">Fechar</button>
                        <a v-if="reportUrl" :href="reportUrl" class="nfc-button nfc-primary" download><FileDown :size="16" aria-hidden="true" /> Baixar PDF</a>
                        <button v-else type="button" class="nfc-button nfc-primary" disabled>Baixar PDF</button>
                    </div>
                </div>
                <form v-else-if="dialog.kind === 'reset' && !shared && authenticatedUser" class="flex min-w-0 flex-col gap-4" @submit.prevent="resetWorkspace">
                    <p class="text-sm leading-6">Esta ação excluirá permanentemente todas as empresas, unidades, fabricantes, lotes, vendas, plaquinhas, pagamentos, reinvestimentos, despesas, avarias, avisos, imagens e histórico da sua área NFC.</p>
                    <p class="nfc-muted text-sm">Os links de acesso e os dados dos demais módulos do FinanSys serão preservados. Esta ação não pode ser desfeita.</p>
                    <label class="nfc-field">Digite ZERAR para confirmar
                        <input v-model="resetConfirmation" class="nfc-control" type="text" autocomplete="off" :disabled="busy" />
                    </label>
                    <div class="nfc-rule flex flex-wrap justify-end gap-2 border-t pt-4">
                        <button type="button" class="nfc-button" :disabled="busy" @click="closeDialog">Cancelar</button>
                        <button type="submit" class="nfc-button nfc-danger" :disabled="busy || schema_pending || resetConfirmation !== 'ZERAR'">{{ busy ? 'Zerando…' : 'Zerar todos os cadastros' }}</button>
                    </div>
                </form>
                <div v-else-if="dialog.kind === 'share' && !shared && authenticatedUser" class="flex min-w-0 flex-col gap-4">
                    <p v-if="!share_links_enabled" class="nfc-warning rounded-lg p-3 text-sm">Aplique a migration de permissões dos links NFC para habilitar a emissão e edição de links.</p>
                    <p class="nfc-muted text-sm">Links válidos por sete dias. Escolha as operações permitidas. Cada link mantém sua própria configuração.</p>
                    <label class="nfc-field">Permissão do link
                        <select v-model="shareScope" class="nfc-control" :disabled="shareBusy || !share_links_enabled"><option v-for="option in scopeOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select>
                    </label>
                    <button type="button" class="nfc-button nfc-primary self-start" :disabled="shareBusy || !share_links_enabled" @click="createShare">Gerar novo link</button>
                    <div v-if="created_share_url" class="nfc-panel flex min-w-0 flex-col gap-2 p-3">
                        <label class="nfc-field">Link gerado (copie agora)
                            <input class="nfc-control" type="text" readonly :value="created_share_url" @focus="$event.target.select()" />
                        </label>
                        <button type="button" class="nfc-button self-start" @click="copyShare">{{ copied ? 'Copiado' : 'Copiar link' }}</button>
                        <p class="nfc-muted text-xs">Por segurança, o endereço completo não será mostrado novamente.</p>
                    </div>
                    <h3 class="font-semibold">Links emitidos</h3>
                    <ul class="flex flex-col gap-2">
                        <li v-for="link in share_links" :key="link.id" class="nfc-panel flex flex-wrap items-center justify-between gap-3 p-3 text-sm">
                            <div><p class="font-semibold">Link #{{ link.id }} · {{ scopeOptions.find(option => option.value === (link.scope === 'edit' || !link.scope ? 'cud' : link.scope))?.label }}</p>
                                <p class="nfc-muted text-xs">Expira: {{ datetime(link.expires_at) }}</p>
                                <span v-if="link.revoked_at" class="nfc-badge nfc-neutral">Revogado</span>
                                <span v-else-if="new Date(link.expires_at) <= new Date()" class="nfc-badge nfc-warning">Expirado</span>
                                <span v-else class="nfc-badge nfc-success">Ativo</span>
                            </div>
                            <label v-if="!link.revoked_at && new Date(link.expires_at) > new Date() && share_links_enabled" class="nfc-field">Permissões<select class="nfc-control" :value="link.scope === 'edit' ? 'cud' : link.scope" :disabled="shareBusy" @change="updateShare(link, $event.target.value)"><option v-for="option in scopeOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
                            <button v-if="!link.revoked_at" type="button" class="nfc-button nfc-danger" :disabled="shareBusy" @click="revokeShare(link)">Revogar</button>
                        </li>
                    </ul>
                    <p v-if="!share_links.length" class="nfc-muted text-sm">Nenhum link emitido.</p>
                </div>
                <div v-else-if="dialog.kind === 'history'" class="flex max-h-[65dvh] min-w-0 flex-col gap-3 overflow-y-auto">
                    <p class="nfc-muted text-sm">Últimos {{ history.length }} eventos. Criações, edições, resoluções e exclusões são registradas no módulo NFC.</p>
                    <ul class="flex flex-col gap-2">
                        <li v-for="entry in history" :key="entry.id" class="nfc-panel p-3 text-sm">
                            <p class="font-semibold">{{ historyVerb[entry.event] || entry.event }} · {{ historyKind[entry.target_kind] || entry.target_kind }} #{{ entry.target_id }}</p>
                            <p v-if="entry.label" class="min-w-0 break-words">{{ entry.label }}</p>
                            <p class="nfc-muted text-xs">{{ entry.actor }} · {{ datetime(entry.occurred_at) }}</p>
                            <details v-if="!hiddenValues" class="nfc-history-details mt-2 rounded-lg p-2">
                                <summary class="cursor-pointer text-xs font-medium">Detalhes técnicos da alteração</summary>
                                <dl class="mt-2 grid gap-2 sm:grid-cols-2">
                                    <div v-if="entry.before != null" class="min-w-0">
                                        <dt class="nfc-muted mb-1 text-xs font-semibold">Antes</dt>
                                        <dd class="nfc-history-code max-h-44 overflow-auto rounded-md p-2 text-xs"><pre class="whitespace-pre-wrap break-all">{{ JSON.stringify(entry.before, null, 2) }}</pre></dd>
                                    </div>
                                    <div v-if="entry.after != null" class="min-w-0">
                                        <dt class="nfc-muted mb-1 text-xs font-semibold">Depois</dt>
                                        <dd class="nfc-history-code max-h-44 overflow-auto rounded-md p-2 text-xs"><pre class="whitespace-pre-wrap break-all">{{ JSON.stringify(entry.after, null, 2) }}</pre></dd>
                                    </div>
                                </dl>
                            </details>
                        </li>
                    </ul>
                    <p v-if="!history.length" class="nfc-muted text-sm">Nenhuma alteração registrada.</p>
                </div>
                <Notices v-else-if="dialog.kind === 'notices'" :notices="notices" :permissions="permissions" :hidden-values="hiddenValues" :warranty-notices="warranty_notices" :nfc-route="nfcRoute" @create="edit('notice')" @open-company="showCompany" @request-delete="notice => destroy('notice', { noticeId: notice.id })" @busy="busy = $event" @feedback="notify" />
                <Editor
                    v-else
                    :key="dialog.kind"
                    :kind="dialog.kind"
                    :permissions="permissions"
                    :hidden-values="hiddenValues"
                    :context="dialog"
                    :today="today"
                    :company="company"
                    :companies="companies"
                    :unit="unit"
                    :sale="sale"
                    :plate="plate"
                    :batch="batch"
                    :manufacturer="manufacturer"
                    :manufacturers="manufacturers"
                    :production="production"
                    :nfc-route="nfcRoute"
                    @cancel="goBack"
                    @saved="saved"
                    @busy="busy = $event"
                    @feedback="notify"
                />
            </section>
        </Modal>
    </component>
</template>
