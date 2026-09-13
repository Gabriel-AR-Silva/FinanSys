<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Check, Circle } from '@lucide/vue';
import { computed } from 'vue';

let checklistSequence = 0;

const props = defineProps({
    essentialSteps: {
        type: Array,
        default: () => [],
    },
    recommendedSteps: {
        type: Array,
        default: () => [],
    },
    progress: {
        type: Object,
        default: () => ({
            completed: 0,
            total: 0,
            percent: 0,
            essentialReady: false,
        }),
    },
});

const emit = defineEmits(['navigate']);
const componentId = `onboarding-checklist-${++checklistSequence}`;

const completed = computed(() => Number(props.progress?.completed ?? 0));
const total = computed(() => Number(props.progress?.total ?? 0));
const percent = computed(() => {
    const value = Number(props.progress?.percent ?? 0);

    return Math.min(100, Math.max(0, Number.isFinite(value) ? value : 0));
});
const essentialReady = computed(() => Boolean(props.progress?.essentialReady));

const sections = computed(() => [
    {
        key: 'essential',
        title: 'Essencial',
        description: 'O mínimo para registrar e interpretar sua vida financeira.',
        steps: props.essentialSteps,
    },
    {
        key: 'recommended',
        title: 'Recomendado',
        description: 'Melhora suas projeções, mas não impede o uso do FinanSys.',
        steps: props.recommendedSteps,
    },
]);
</script>

<template>
    <section class="flex flex-col gap-5" :aria-labelledby="`${componentId}-title`">
        <header class="flex flex-col gap-3">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Primeiros passos</p>
                    <h2 :id="`${componentId}-title`" class="mt-1 text-lg font-semibold tracking-tight text-slate-950">
                        Prepare seu FinanSys
                    </h2>
                </div>

                <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                    {{ completed }}/{{ total }}
                </span>
            </div>

            <div
                class="h-2 overflow-hidden rounded-full bg-slate-100"
                role="progressbar"
                aria-label="Progresso dos primeiros passos"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-valuenow="Math.round(percent)"
                :aria-valuetext="`${completed} de ${total} etapas concluídas`"
            >
                <div
                    class="h-full rounded-full bg-emerald-600 transition-[width] duration-300 motion-reduce:transition-none"
                    :style="{ width: `${percent}%` }"
                />
            </div>

            <p class="text-sm leading-5" :class="essentialReady ? 'text-emerald-700' : 'text-slate-600'" aria-live="polite">
                <span class="font-semibold">{{ essentialReady ? 'Essencial pronto.' : 'Configuração essencial pendente.' }}</span>
                {{ essentialReady ? ' Você já pode usar o núcleo financeiro.' : ' Conclua os passos essenciais para começar com contexto.' }}
            </p>
        </header>

        <div v-if="total === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm leading-6 text-slate-600">
            Seus primeiros passos aparecerão aqui assim que o estado financeiro for carregado.
        </div>

        <div v-else class="flex flex-col gap-5">
            <section v-for="section in sections" :key="section.key" class="flex flex-col gap-3" :aria-labelledby="`${componentId}-${section.key}-title`">
                <div>
                    <h3 :id="`${componentId}-${section.key}-title`" class="text-sm font-semibold text-slate-900">{{ section.title }}</h3>
                    <p class="mt-0.5 text-xs leading-5 text-slate-500">{{ section.description }}</p>
                </div>

                <p v-if="section.steps.length === 0" class="rounded-xl bg-slate-50 px-3 py-2.5 text-xs leading-5 text-slate-500">
                    Nenhuma etapa {{ section.key === 'essential' ? 'essencial' : 'recomendada' }} pendente ou disponível.
                </p>

                <ol v-else class="flex flex-col gap-2.5">
                    <li
                        v-for="step in section.steps"
                        :key="step.key"
                        class="flex items-start gap-3 rounded-2xl border p-3"
                        :class="step.completed ? 'border-emerald-100 bg-emerald-50/70' : 'border-slate-200 bg-white'"
                    >
                        <span
                            class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full"
                            :class="step.completed ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-500'"
                            aria-hidden="true"
                        >
                            <Check v-if="step.completed" :size="14" :stroke-width="3" />
                            <Circle v-else :size="12" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-slate-900">{{ step.title }}</p>
                                    <p class="mt-1 text-xs leading-5 text-slate-600">{{ step.description }}</p>
                                </div>

                                <span class="shrink-0 text-[0.7rem] font-semibold uppercase tracking-wide" :class="step.completed ? 'text-emerald-700' : 'text-slate-500'">
                                    {{ step.completed ? 'Concluído' : 'Pendente' }}
                                </span>
                            </div>

                            <Link
                                v-if="!step.completed && step.cta?.href"
                                :href="step.cta.href"
                                class="mt-2 inline-flex min-h-10 items-center gap-1.5 rounded-lg px-2 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2"
                                @click="emit('navigate', step)"
                            >
                                {{ step.cta.label || 'Fazer agora' }}
                                <ArrowRight :size="14" aria-hidden="true" />
                            </Link>
                        </div>
                    </li>
                </ol>
            </section>
        </div>
    </section>
</template>
