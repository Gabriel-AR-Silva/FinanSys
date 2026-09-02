<script setup>
import { Plus } from '@lucide/vue';
defineProps({ icon: Object, title: String, description: String, action: String, note: String });
</script>
<template><div class="mt-8 flex min-h-[28rem] flex-col items-center justify-center rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center shadow-sm"><span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-600"><component :is="icon" :size="29" /></span><h2 class="mt-6 text-xl font-semibold tracking-tight text-slate-950">{{ title }}</h2><p class="mt-2 max-w-md text-sm leading-6 text-slate-500">{{ description }}</p><button type="button" disabled class="mt-6 flex cursor-not-allowed items-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white opacity-50"><Plus :size="18" />{{ action }}</button><p class="mt-3 text-xs text-slate-400">{{ note }}</p></div></template>
