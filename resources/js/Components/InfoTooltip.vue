<script setup>
import { CircleAlert, X } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({
    text: { type: String, required: true },
    label: { type: String, default: 'Mais informações' },
});

const open = ref(false);
const root = ref(null);

const toggle = () => {
    open.value = !open.value;
};

const close = () => {
    open.value = false;
};

const handlePointerDown = (event) => {
    if (open.value && root.value && !root.value.contains(event.target)) close();
};

const handleKeyDown = (event) => {
    if (event.key === 'Escape') close();
};

onMounted(() => {
    document.addEventListener('pointerdown', handlePointerDown);
    document.addEventListener('keydown', handleKeyDown);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', handlePointerDown);
    document.removeEventListener('keydown', handleKeyDown);
});
</script>

<template>
    <span ref="root" class="group relative inline-flex shrink-0">
        <button
            type="button"
            class="flex h-7 w-7 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
            :aria-label="label"
            :aria-expanded="open"
            aria-haspopup="dialog"
            @click.stop="toggle"
        >
            <CircleAlert :size="16" />
        </button>

        <span
            v-show="open"
            role="dialog"
            :aria-label="label"
            class="absolute right-0 top-9 z-[100] w-64 max-w-[calc(100vw-2rem)] rounded-xl border border-slate-700 bg-slate-950 p-3 text-left text-xs font-normal leading-5 text-white shadow-2xl"
            @click.stop
        >
            <span class="flex items-start gap-2">
                <span class="min-w-0 flex-1">{{ text }}</span>
                <button
                    type="button"
                    class="-mr-1 -mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-slate-400 hover:bg-slate-800 hover:text-white"
                    aria-label="Fechar"
                    @click="close"
                >
                    <X :size="14" />
                </button>
            </span>
        </span>

        <span
            v-if="!open"
            role="tooltip"
            class="pointer-events-none absolute right-0 top-9 z-[100] hidden w-64 max-w-[calc(100vw-2rem)] rounded-xl border border-slate-700 bg-slate-950 p-3 text-left text-xs font-normal leading-5 text-white shadow-2xl group-hover:block"
        >
            {{ text }}
        </span>
    </span>
</template>
