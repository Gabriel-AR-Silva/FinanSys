<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { Camera, Trash2 } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';

const user = usePage().props.auth.user;
const preview = ref(user.avatar_path ? `/storage/${user.avatar_path}` : null);
const temporaryPreview = ref(null);
const fileInput = ref(null);
const form = useForm({ _method: 'patch', name: user.name, email: user.email, avatar: null, remove_avatar: false });
const initials = computed(() => (user.name || 'Usuário').trim().split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join(''));

const clearTemporaryPreview = () => {
    if (temporaryPreview.value) URL.revokeObjectURL(temporaryPreview.value);
    temporaryPreview.value = null;
};
const openFilePicker = () => fileInput.value?.click();
const chooseAvatar = (event) => {
    const file = event.target.files?.[0] ?? null;
    if (!file) return;
    clearTemporaryPreview();
    temporaryPreview.value = URL.createObjectURL(file);
    preview.value = temporaryPreview.value;
    form.avatar = file;
    form.remove_avatar = false;
};
const removeAvatar = () => {
    clearTemporaryPreview();
    form.avatar = null;
    form.remove_avatar = true;
    preview.value = null;
    if (fileInput.value) fileInput.value.value = '';
};
const submit = () => form.post(route('profile.update'), { forceFormData: true, preserveScroll: true, onSuccess: () => { form.avatar = null; clearTemporaryPreview(); } });
onBeforeUnmount(clearTemporaryPreview);
</script>

<template>
    <section>
        <header><h2 class="text-lg font-semibold text-gray-900">Perfil do usuário</h2><p class="mt-1 text-sm leading-6 text-gray-600">Personalize como você aparece no FinanSys e mantenha seus dados principais atualizados.</p></header>
        <form class="mt-6 space-y-7" @submit.prevent="submit">
            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 sm:p-5">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                    <div class="relative w-fit shrink-0">
                        <button type="button" class="group relative flex h-24 w-24 items-center justify-center overflow-hidden rounded-full bg-slate-950 text-xl font-bold text-emerald-200 ring-4 ring-white shadow-md outline-none transition focus-visible:ring-emerald-400" aria-label="Alterar foto de perfil" @click="openFilePicker">
                            <img v-if="preview" :src="preview" alt="Foto de perfil" class="h-full w-full object-cover" /><span v-else>{{ initials }}</span>
                            <span class="absolute inset-0 grid place-items-center bg-slate-950/55 text-white opacity-0 transition group-hover:opacity-100 group-focus-visible:opacity-100"><Camera :size="23" /></span>
                        </button>
                        <span class="pointer-events-none absolute bottom-0 right-0 grid h-8 w-8 place-items-center rounded-full border-2 border-white bg-emerald-500 text-slate-950 shadow-sm"><Camera :size="15" /></span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-900">Foto de perfil</p><p class="mt-1 text-sm leading-5 text-slate-500">Ela será usada para identificar seu usuário nas áreas do FinanSys.</p>
                        <div class="mt-3 flex flex-wrap gap-2"><button type="button" class="inline-flex items-center gap-2 rounded-xl bg-slate-950 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-slate-800" @click="openFilePicker"><Camera :size="16" />{{ preview ? 'Trocar foto' : 'Adicionar foto' }}</button><button v-if="preview" type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700" @click="removeAvatar"><Trash2 :size="16" />Remover</button></div>
                        <p class="mt-2 text-xs text-slate-500">JPG, PNG ou WebP · máximo de 2 MB.</p>
                    </div>
                </div>
                <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="chooseAvatar" /><InputError class="mt-3" :message="form.errors.avatar" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2"><div><InputLabel for="name" value="Nome" /><TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required autocomplete="name" /><InputError class="mt-2" :message="form.errors.name" /></div><div><InputLabel for="email" value="E-mail" /><TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required autocomplete="username" /><InputError class="mt-2" :message="form.errors.email" /></div></div>
            <div class="flex items-center gap-4 border-t border-slate-100 pt-5"><PrimaryButton :disabled="form.processing">Salvar perfil</PrimaryButton><Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0"><p v-if="form.recentlySuccessful" class="text-sm font-medium text-emerald-700">Perfil atualizado.</p></Transition></div>
        </form>
    </section>
</template>
