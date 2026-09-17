<script setup>
import { X } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import OnboardingChecklist from '@/Components/OnboardingChecklist.vue';

let tsukiSequence = 0;
const DISMISSED_KEY = 'finansys:tsuki-onboarding-dismissed';

const props = defineProps({
    essentialSteps: { type: Array, default: () => [] },
    recommendedSteps: { type: Array, default: () => [] },
    progress: {
        type: Object,
        default: () => ({ completed: 0, total: 0, percent: 0, essentialReady: false }),
    },
    initiallyOpen: { type: Boolean, default: false },
});

const emit = defineEmits(['navigate', 'open', 'close']);
const panelOpen = ref(false);
const dismissed = ref(false);
const trigger = ref(null);
const panel = ref(null);
const closeButton = ref(null);
const componentId = `tsuki-onboarding-${++tsukiSequence}`;
const panelId = `${componentId}-panel`;
const titleId = `${componentId}-title`;

const hasPendingSteps = computed(() => Number(props.progress?.completed ?? 0) < Number(props.progress?.total ?? 0));
const triggerLabel = computed(() => `Abrir primeiros passos. ${Number(props.progress?.completed ?? 0)} de ${Number(props.progress?.total ?? 0)} etapas concluídas.`);

const rememberClosed = () => {
    dismissed.value = true;
    try { window.localStorage.setItem(DISMISSED_KEY, '1'); } catch (_) { /* armazenamento pode estar indisponível */ }
};

const openPanel = async () => {
    panelOpen.value = true;
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
const handleKeydown = (event) => {
    if (event.key === 'Escape' && panelOpen.value) {
        event.preventDefault();
        closePanel();
    }
};
const handlePointerDown = (event) => {
    if (!panelOpen.value || panel.value?.contains(event.target) || trigger.value?.contains(event.target)) return;
    closePanel({ restoreFocus: false });
};
const handleNavigate = (step) => {
    emit('navigate', step);
    closePanel({ restoreFocus: false });
};

onMounted(() => {
    try { dismissed.value = window.localStorage.getItem(DISMISSED_KEY) === '1'; } catch (_) { dismissed.value = false; }
    panelOpen.value = props.initiallyOpen && !dismissed.value;
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
            <aside v-if="panelOpen" :id="panelId" ref="panel" role="dialog" aria-modal="false" :aria-labelledby="titleId" class="pointer-events-auto absolute bottom-16 right-0 flex max-h-[min(70dvh,36rem)] w-[calc(100vw-2rem)] max-w-sm flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl shadow-slate-950/20">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <img src="/tsuki-mark.svg" alt="" width="36" height="36" class="h-9 w-9 shrink-0 rounded-full bg-slate-950 object-contain" />
                        <div class="min-w-0">
                            <p :id="titleId" class="truncate text-sm font-semibold text-slate-950">Tsuki</p>
                            <p class="truncate text-xs text-slate-500">Seu guia de primeiros passos</p>
                        </div>
                    </div>
                    <button ref="closeButton" type="button" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2" aria-label="Fechar primeiros passos" @click="closePanel()">
                        <X :size="19" aria-hidden="true" />
                    </button>
                </div>
                <div class="overflow-y-auto overscroll-contain p-4">
                    <OnboardingChecklist :essential-steps="essentialSteps" :recommended-steps="recommendedSteps" :progress="progress" @navigate="handleNavigate" />
                </div>
            </aside>
        </Transition>

        <Transition enter-active-class="transition duration-200" enter-from-class="translate-y-1 opacity-0" leave-active-class="transition duration-150" leave-to-class="translate-y-1 opacity-0">
            <button v-if="!panelOpen && dismissed && hasPendingSteps" type="button" class="pointer-events-auto absolute bottom-16 right-0 mb-2 w-max max-w-[15rem] rounded-2xl border border-emerald-200 bg-white px-3 py-2 text-left text-xs font-medium text-slate-700 shadow-lg" @click="openPanel">
                <span class="block font-semibold text-emerald-700">Ainda temos etapas pendentes.</span>
                <span class="mt-0.5 block text-slate-500">Quando quiser, continuo daqui com você.</span>
            </button>
        </Transition>

        <button ref="trigger" type="button" class="tsuki-presence pointer-events-auto relative flex h-14 w-14 items-center justify-center rounded-full bg-slate-950 shadow-lg shadow-slate-950/25 transition hover:scale-105 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-4 motion-reduce:transform-none motion-reduce:transition-none" :aria-label="triggerLabel" :aria-expanded="panelOpen" :aria-controls="panelId" @click="togglePanel">
            <span class="tsuki-ring absolute inset-0 rounded-full border border-emerald-300/50" aria-hidden="true" />
            <span v-if="!panelOpen && hasPendingSteps" class="absolute -right-0.5 -top-0.5 z-10 flex h-4 w-4 items-center justify-center rounded-full bg-amber-400 text-[10px] font-black text-slate-950 ring-2 ring-white" aria-hidden="true">!</span>
            <img src="/tsuki-mark.svg" alt="" width="48" height="48" class="relative h-12 w-12 rounded-full object-contain" aria-hidden="true" />
        </button>
    </div>
</template>

<style scoped>
@keyframes tsuki-breathe { 0%,100% { transform: translateY(0) scale(1); } 50% { transform: translateY(-2px) scale(1.025); } }
@keyframes tsuki-ring { 0%,45% { opacity:0; transform:scale(1); } 70% { opacity:.5; } 100% { opacity:0; transform:scale(1.32); } }
.tsuki-presence { animation: tsuki-breathe 4.8s ease-in-out infinite; }
.tsuki-ring { animation: tsuki-ring 4.8s ease-out infinite; }
@media (prefers-reduced-motion: reduce) { .tsuki-presence,.tsuki-ring { animation:none; } }
</style>
