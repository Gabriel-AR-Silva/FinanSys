<script setup>
import OnboardingChecklist from '@/Components/OnboardingChecklist.vue';
import tsukiIcon from '@/assets/tsuki.png';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, CheckCircle2, Info, ListChecks, Sparkles, X } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';

let tsukiSequence = 0;
const DISMISSED_KEY = 'finansys:tsuki-onboarding-dismissed';
const HINT_DISMISSED_KEY = 'finansys:tsuki-minimized-hint-dismissed';
const WIZARD_STARTED_KEY = 'finansys:tsuki-wizard-started';
const WIZARD_COMPLETE_KEY = 'finansys:tsuki-wizard-complete';
const WIZARD_INDEX_KEY = 'finansys:tsuki-wizard-index';

const props = defineProps({
    essentialSteps: { type: Array, default: () => [] },
    recommendedSteps: { type: Array, default: () => [] },
    progress: { type: Object, default: () => ({ completed: 0, total: 0, percent: 0, essentialReady: false }) },
    initiallyOpen: { type: Boolean, default: false },
});
const emit = defineEmits(['navigate', 'open', 'close']);

const panelOpen = ref(false);
const dismissed = ref(false);
const hintDismissed = ref(false);
const wizardStarted = ref(false);
const wizardComplete = ref(false);
const mode = ref('wizard');
const wizardIndex = ref(0);
const trigger = ref(null);
const panel = ref(null);
const closeButton = ref(null);
const componentId = `tsuki-onboarding-${++tsukiSequence}`;
const panelId = `${componentId}-panel`;
const titleId = `${componentId}-title`;

const allSteps = computed(() => [...props.essentialSteps, ...props.recommendedSteps]);
const byKey = computed(() => Object.fromEntries(allSteps.value.map(step => [step.key, step])));
const wizardKeys = ['foundation', 'account', 'income', 'fixed_commitments', 'essentials', 'planning', 'daily_budget'];
const wizardSteps = computed(() => wizardKeys.map(key => byKey.value[key]).filter(Boolean));
const wizardFinished = computed(() => wizardIndex.value >= wizardSteps.value.length);
const currentStep = computed(() => wizardFinished.value ? null : wizardSteps.value[wizardIndex.value]);
const hasPendingSteps = computed(() => Number(props.progress?.completed ?? 0) < Number(props.progress?.total ?? 0));
const triggerLabel = computed(() => `Abrir primeiros passos com Tsuki. ${Number(props.progress?.completed ?? 0)} de ${Number(props.progress?.total ?? 0)} etapas concluídas.`);

const guidance = {
    foundation: {
        eyebrow: '1 · Base',
        title: 'Seu FinanSys já começa no padrão certo',
        text: 'Moeda em real e calendário de Brasília são a base usada pelos cálculos. Você não precisa configurar isso agora.',
        tip: 'Essas preferências evitam que datas e valores sejam interpretados em formatos diferentes.',
    },
    account: {
        eyebrow: '2 · Conta',
        title: 'Comece pelo dinheiro que existe hoje',
        text: 'Cadastre sua primeira conta e informe o saldo atual. O saldo inicial representa sua posição de hoje; ele não é uma receita nova.',
        tip: 'Exemplo: se há R$ 500 na conta agora, informe R$ 500 como saldo inicial.',
    },
    income: {
        eyebrow: '3 · Renda',
        title: 'Mostre o que entra no seu mês',
        text: 'Você pode registrar uma receita já recebida ou uma previsão. Previsão ajuda a planejar, mas não vira saldo antes do recebimento.',
        tip: 'Salário ainda não recebido continua projetado; salário recebido passa a compor o saldo real.',
    },
    fixed_commitments: {
        eyebrow: '4 · Compromissos',
        title: 'Separe o que já está comprometido',
        text: 'Cadastre gastos conhecidos para o sistema não tratar dinheiro comprometido como livre.',
        tip: 'Uma conta de R$ 120 prevista para o dia 20 é compromisso; ela só reduz o saldo quando for efetivamente paga.',
    },
    essentials: {
        eyebrow: '5 · Essenciais',
        title: 'Reserve espaço para o que é necessário',
        text: 'Categorias essenciais ajudam o planejamento a entender despesas importantes sem inventar lançamentos.',
        tip: 'Alimentação pode ter uma reserva mensal mesmo antes de você fazer cada compra.',
    },
    planning: {
        eyebrow: '6 · Planejamento',
        title: 'Confirme as premissas deste mês',
        text: 'O planejamento mensal diferencia ausência de informação de zero confirmado e deixa projeções mais confiáveis.',
        tip: 'Se algo não existe no seu mês, confirme isso no planejamento em vez de deixar o sistema adivinhar.',
    },
    daily_budget: {
        eyebrow: '7 · Planejamento diário',
        title: 'Defina uma referência diária voluntária',
        text: 'O orçamento diário libera os check-ins da V2 e permite comparar intenção com gasto elegível, sem alterar seu saldo bancário.',
        tip: 'Se sua referência for R$ 90 e gastar R$ 80, a folga do dia é +R$ 10; o orçamento de amanhã continua R$ 90.',
    },
};

const stepGuidance = computed(() => currentStep.value ? guidance[currentStep.value.key] : null);
const rememberClosed = () => {
    dismissed.value = true;
    try { window.localStorage.setItem(DISMISSED_KEY, '1'); } catch (_) { /* armazenamento pode estar indisponível */ }
};
const dismissHint = () => {
    hintDismissed.value = true;
    try { window.localStorage.setItem(HINT_DISMISSED_KEY, '1'); } catch (_) { /* armazenamento pode estar indisponível */ }
};
const saveWizardIndex = () => {
    try { window.localStorage.setItem(WIZARD_INDEX_KEY, String(wizardIndex.value)); } catch (_) { /* armazenamento pode estar indisponível */ }
};
const startWizard = () => {
    wizardStarted.value = true;
    wizardComplete.value = false;
    mode.value = 'wizard';
    try {
        window.localStorage.setItem(WIZARD_STARTED_KEY, '1');
        window.localStorage.removeItem(WIZARD_COMPLETE_KEY);
    } catch (_) { /* armazenamento pode estar indisponível */ }
};
const finishWizard = () => {
    wizardComplete.value = true;
    wizardStarted.value = false;
    mode.value = 'checklist';
    try {
        window.localStorage.setItem(WIZARD_COMPLETE_KEY, '1');
        window.localStorage.removeItem(WIZARD_STARTED_KEY);
        window.localStorage.removeItem(WIZARD_INDEX_KEY);
    } catch (_) { /* armazenamento pode estar indisponível */ }
};
const moveWizard = delta => {
    wizardIndex.value = Math.min(wizardSteps.value.length, Math.max(0, wizardIndex.value + delta));
    saveWizardIndex();
};
const openPanel = async () => {
    panelOpen.value = true;
    if (!wizardComplete.value && !wizardStarted.value) startWizard();
    emit('open');
    await nextTick();
    closeButton.value?.focus();
};
const closePanel = async ({ restoreFocus = true, remember = true } = {}) => {
    if (!panelOpen.value) return;
    panelOpen.value = false;
    if (remember) rememberClosed();
    emit('close');
    if (restoreFocus) {
        await nextTick();
        trigger.value?.focus();
    }
};
const togglePanel = () => panelOpen.value ? closePanel() : openPanel();
const handleKeydown = event => {
    if (event.key === 'Escape' && panelOpen.value) { event.preventDefault(); closePanel(); }
};
const handlePointerDown = event => {
    if (panelOpen.value && !panel.value?.contains(event.target) && !trigger.value?.contains(event.target)) closePanel({ restoreFocus: false });
};
const handleNavigate = step => {
    emit('navigate', step);
    if (mode.value === 'wizard') saveWizardIndex();
    closePanel({ restoreFocus: false, remember: false });
};

onMounted(() => {
    try {
        dismissed.value = window.localStorage.getItem(DISMISSED_KEY) === '1';
        hintDismissed.value = window.localStorage.getItem(HINT_DISMISSED_KEY) === '1';
        wizardStarted.value = window.localStorage.getItem(WIZARD_STARTED_KEY) === '1';
        wizardComplete.value = window.localStorage.getItem(WIZARD_COMPLETE_KEY) === '1';
        const storedIndex = Number(window.localStorage.getItem(WIZARD_INDEX_KEY) ?? 0);
        wizardIndex.value = Number.isInteger(storedIndex) && storedIndex >= 0 ? Math.min(storedIndex, wizardSteps.value.length) : 0;
    } catch (_) {
        dismissed.value = false;
        hintDismissed.value = false;
    }

    while (wizardIndex.value < wizardSteps.value.length && wizardSteps.value[wizardIndex.value]?.completed) {
        wizardIndex.value += 1;
    }
    saveWizardIndex();

    if (props.initiallyOpen && !wizardComplete.value) startWizard();
    mode.value = wizardComplete.value ? 'checklist' : 'wizard';
    panelOpen.value = !dismissed.value && !wizardComplete.value && (props.initiallyOpen || wizardStarted.value);

    document.addEventListener('keydown', handleKeydown);
    document.addEventListener('pointerdown', handlePointerDown);
});
onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
    document.removeEventListener('pointerdown', handlePointerDown);
});
</script>

<template>
    <div class="pointer-events-none fixed bottom-[max(5rem,env(safe-area-inset-bottom))] right-4 z-[60] sm:bottom-6 sm:right-6">
        <Transition enter-active-class="transition duration-200 ease-out motion-reduce:transition-none" enter-from-class="translate-y-2 scale-95 opacity-0" enter-to-class="translate-y-0 scale-100 opacity-100" leave-active-class="transition duration-150 ease-in motion-reduce:transition-none" leave-from-class="translate-y-0 scale-100 opacity-100" leave-to-class="translate-y-2 scale-95 opacity-0">
            <aside v-if="panelOpen" :id="panelId" ref="panel" role="dialog" aria-modal="false" :aria-labelledby="titleId" class="pointer-events-auto absolute bottom-16 right-0 flex max-h-[min(78dvh,42rem)] w-[calc(100vw-2rem)] max-w-md flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl shadow-slate-950/20">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <img :src="tsukiIcon" alt="" width="40" height="40" class="h-10 w-10 shrink-0 object-contain" />
                        <div class="min-w-0">
                            <p :id="titleId" class="truncate text-sm font-semibold text-slate-950">Tsuki</p>
                            <p class="truncate text-xs text-slate-500">{{ mode === 'wizard' ? 'Configuração guiada do FinanSys' : 'Seus primeiros passos' }}</p>
                        </div>
                    </div>
                    <button ref="closeButton" type="button" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2" aria-label="Fechar primeiros passos" @click="closePanel()"><X :size="19" aria-hidden="true" /></button>
                </div>

                <div class="border-b border-slate-100 px-4 py-2">
                    <div class="grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-1">
                        <button type="button" class="rounded-lg px-3 py-2 text-xs font-semibold transition" :class="mode === 'wizard' ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-600'" @click="mode = 'wizard'; startWizard()"><Sparkles :size="14" class="mr-1 inline" />Guia</button>
                        <button type="button" class="rounded-lg px-3 py-2 text-xs font-semibold transition" :class="mode === 'checklist' ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-600'" @click="mode = 'checklist'"><ListChecks :size="14" class="mr-1 inline" />Checklist</button>
                    </div>
                </div>

                <div class="overflow-y-auto overscroll-contain p-4">
                    <OnboardingChecklist v-if="mode === 'checklist'" :essential-steps="essentialSteps" :recommended-steps="recommendedSteps" :progress="progress" @navigate="handleNavigate" />

                    <section v-else class="flex flex-col gap-4">
                        <template v-if="!wizardFinished && currentStep">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">{{ stepGuidance?.eyebrow }}</p>
                                <h2 class="mt-1 text-xl font-semibold tracking-tight text-slate-950">{{ stepGuidance?.title || currentStep.title }}</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ stepGuidance?.text || currentStep.description }}</p>
                            </div>

                            <div class="h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-label="Progresso do guia" aria-valuemin="0" :aria-valuemax="wizardSteps.length" :aria-valuenow="wizardIndex + 1">
                                <div class="h-full rounded-full bg-emerald-600 transition-[width] duration-300 motion-reduce:transition-none" :style="{ width: `${((wizardIndex + 1) / wizardSteps.length) * 100}%` }" />
                            </div>

                            <div class="flex items-start gap-3 rounded-2xl border border-sky-200 bg-sky-50 p-3 text-sm leading-5 text-sky-950">
                                <Info :size="18" class="mt-0.5 shrink-0" aria-hidden="true" />
                                <div><p class="font-semibold">Por que isso importa?</p><p class="mt-1 text-xs leading-5 text-sky-900">{{ stepGuidance?.tip }}</p></div>
                            </div>

                            <div class="rounded-2xl border p-4" :class="currentStep.completed ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white'">
                                <div class="flex items-start gap-3">
                                    <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full" :class="currentStep.completed ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-500'"><CheckCircle2 :size="16" /></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-semibold text-slate-950">{{ currentStep.title }}</p>
                                        <p class="mt-1 text-xs leading-5 text-slate-600">{{ currentStep.description }}</p>
                                        <Link v-if="!currentStep.completed && currentStep.cta?.href" :href="currentStep.cta.href" class="mt-3 inline-flex min-h-10 items-center gap-2 rounded-xl bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2" @click="handleNavigate(currentStep)">
                                            {{ currentStep.cta.label || 'Fazer agora' }} <ArrowRight :size="14" />
                                        </Link>
                                        <p v-else class="mt-3 text-xs font-semibold text-emerald-700">Esta etapa já está configurada.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-2">
                                <button type="button" :disabled="wizardIndex === 0" class="inline-flex min-h-10 items-center gap-1 rounded-xl px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 disabled:opacity-30" @click="moveWizard(-1)"><ArrowLeft :size="14" />Anterior</button>
                                <button type="button" class="inline-flex min-h-10 items-center gap-1 rounded-xl px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50" @click="moveWizard(1)">{{ currentStep.completed ? 'Continuar' : 'Pular por agora' }}<ArrowRight :size="14" /></button>
                            </div>
                        </template>

                        <template v-else>
                            <div class="rounded-3xl bg-slate-950 p-5 text-white">
                                <img :src="tsukiIcon" alt="" class="h-16 w-16 object-contain" />
                                <p class="mt-4 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-300">Configuração principal concluída</p>
                                <h2 class="mt-1 text-xl font-semibold">Você já sabe onde cada peça entra.</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-300">O FinanSys usa dados reais para saldo, planejamento e V2. Cartão e meta continuam opcionais e aparecem no checklist para quando fizerem sentido.</p>
                            </div>
                            <button type="button" class="min-h-11 rounded-xl bg-emerald-700 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-600" @click="finishWizard">Concluir e abrir checklist</button>
                        </template>
                    </section>
                </div>
            </aside>
        </Transition>

        <Transition enter-active-class="transition duration-200" enter-from-class="translate-y-1 opacity-0" leave-active-class="transition duration-150" leave-to-class="translate-y-1 opacity-0">
            <div v-if="!panelOpen && dismissed && hasPendingSteps && !hintDismissed" class="pointer-events-auto absolute bottom-16 right-0 mb-2 flex w-max max-w-[min(15rem,calc(100vw-2rem))] items-start rounded-2xl border border-emerald-200 bg-white text-left text-xs font-medium text-slate-700 shadow-lg">
                <button type="button" class="min-w-0 flex-1 rounded-l-2xl py-2 pl-3 pr-1 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" @click="openPanel"><span class="block font-semibold text-emerald-700">Ainda temos etapas pendentes.</span><span class="mt-0.5 block text-slate-500">Quando quiser, continuo daqui com você.</span></button>
                <button type="button" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-r-2xl text-slate-500 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" aria-label="Dispensar mensagem da Tsuki" @click="dismissHint"><X :size="16" aria-hidden="true" /></button>
            </div>
        </Transition>

        <button ref="trigger" type="button" class="tsuki-presence pointer-events-auto relative flex h-14 w-14 items-center justify-center rounded-full bg-transparent transition hover:scale-105 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-4 motion-reduce:transform-none motion-reduce:transition-none" :aria-label="triggerLabel" :aria-expanded="panelOpen" :aria-controls="panelId" @click="togglePanel">
            <span v-if="!panelOpen && hasPendingSteps" class="absolute -right-0.5 -top-0.5 z-10 flex h-4 w-4 items-center justify-center rounded-full bg-amber-400 text-[10px] font-black text-slate-950 ring-2 ring-white" aria-hidden="true">!</span>
            <img :src="tsukiIcon" alt="" width="56" height="56" class="h-14 w-14 object-contain drop-shadow-lg" aria-hidden="true" />
        </button>
    </div>
</template>

<style scoped>
@keyframes tsuki-breathe { 0%,100% { transform: translateY(0) scale(1); } 50% { transform: translateY(-2px) scale(1.025); } }
.tsuki-presence { animation: tsuki-breathe 4.8s ease-in-out infinite; }
@media (prefers-reduced-motion: reduce) { .tsuki-presence { animation: none; } }
</style>
