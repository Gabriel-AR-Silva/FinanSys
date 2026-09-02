<script setup>
import { usePage } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, CircleAlert, X } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const page = usePage();
const toasts = ref([]);
const timers = new Map();
let nextId = 1;

const styles = {
    success: { icon: CheckCircle2, title: 'Tudo certo', classes: 'border-emerald-200 bg-white text-emerald-700' },
    error: { icon: CircleAlert, title: 'Não foi possível concluir', classes: 'border-rose-200 bg-white text-rose-700' },
    warning: { icon: AlertTriangle, title: 'Atenção', classes: 'border-amber-200 bg-white text-amber-700' },
};

const dismiss = (id) => {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
    clearTimeout(timers.get(id));
    timers.delete(id);
};

const add = ({ type = 'success', message, title = null }) => {
    if (!message) return;
    const duplicate = toasts.value.find((toast) => toast.type === type && toast.message === message);
    if (duplicate) dismiss(duplicate.id);
    const id = nextId++;
    toasts.value.push({ id, type, message, title: title || styles[type].title });
    timers.set(id, setTimeout(() => dismiss(id), type === 'success' ? 4000 : 7000));
};

watch(() => page.props.flash?.success, (message) => add({ type: 'success', message }), { immediate: true });
watch(() => page.props.flash?.error, (message) => add({ type: 'error', message }), { immediate: true });
watch(() => page.props.flash?.warning, (message) => add({ type: 'warning', message }), { immediate: true });
watch(() => page.props.errors, (errors) => {
    if (errors && Object.keys(errors).length) add({ type: 'error', message: 'Revise os campos destacados e tente novamente.' });
}, { deep: true });

const handleCustomToast = (event) => add(event.detail ?? {});
onMounted(() => window.addEventListener('finansys:toast', handleCustomToast));
onBeforeUnmount(() => {
    window.removeEventListener('finansys:toast', handleCustomToast);
    timers.forEach((timer) => clearTimeout(timer));
});
</script>

<template>
    <div class="pointer-events-none fixed bottom-5 right-4 z-[70] flex w-[calc(100%-2rem)] max-w-sm flex-col gap-3 sm:bottom-6 sm:right-6" aria-live="polite" aria-atomic="true">
        <TransitionGroup enter-active-class="transition duration-300 ease-out" enter-from-class="translate-y-3 opacity-0 sm:translate-x-4 sm:translate-y-0" enter-to-class="translate-x-0 translate-y-0 opacity-100" leave-active-class="transition duration-200 ease-in" leave-from-class="opacity-100" leave-to-class="translate-y-2 opacity-0">
            <article v-for="toast in toasts" :key="toast.id" class="pointer-events-auto flex items-start gap-3 rounded-2xl border p-4 shadow-xl shadow-slate-950/10" :class="styles[toast.type].classes" :role="toast.type === 'error' ? 'alert' : 'status'">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-current/10"><component :is="styles[toast.type].icon" :size="20" /></span>
                <div class="min-w-0 flex-1"><p class="text-sm font-semibold text-slate-900">{{ toast.title }}</p><p class="mt-1 text-sm leading-5 text-slate-600">{{ toast.message }}</p></div>
                <button type="button" class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Fechar mensagem" @click="dismiss(toast.id)"><X :size="17" /></button>
            </article>
        </TransitionGroup>
    </div>
</template>
