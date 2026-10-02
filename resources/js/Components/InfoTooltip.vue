<script setup>
import { CircleAlert } from '@lucide/vue';

defineProps({
    text: { type: String, required: true },
    label: { type: String, default: 'Mais informações' },
});
</script>

<template>
    <span class="group relative">
        <button
            type="button"
            class="flex h-7 w-7 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
            :aria-label="label"
        >
            <CircleAlert :size="16" />
        </button>
        <span
            role="tooltip"
            class="pointer-events-none absolute right-0 top-8 z-30 hidden w-64 rounded-xl bg-slate-950 px-3 py-2 text-left text-[11px] font-normal leading-4 text-white shadow-xl group-hover:block group-focus-within:block"
        >
            {{ text }}
        </span>
    </span>
</template>
