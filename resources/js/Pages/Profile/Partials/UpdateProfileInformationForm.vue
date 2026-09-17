<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const user = usePage().props.auth.user;
const preview = ref(user.avatar_path ? `/storage/${user.avatar_path}` : null);
const form = useForm({ name: user.name, email: user.email, avatar: null, remove_avatar: false });
const initials = computed(() => (user.name || 'U').trim().charAt(0).toUpperCase());

const chooseAvatar = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.avatar = file;
    form.remove_avatar = false;
    if (file) preview.value = URL.createObjectURL(file);
};

const removeAvatar = () => {
    form.avatar = null;
    form.remove_avatar = true;
    preview.value = null;
};

const submit = () => form.post(route('profile.update'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => form.avatar = null,
});
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">Informações do perfil</h2>
            <p class="mt-1 text-sm text-gray-600">Atualize seu nome, e-mail e a imagem que representa seu usuário no FinanSys.</p>
        </header>

        <form @submit.prevent="submit" class="mt-6 space-y-6">
            <div>
                <InputLabel value="Imagem do usuário" />
                <div class="mt-2 flex flex-wrap items-center gap-4">
                    <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full bg-emerald-950 text-xl font-semibold text-emerald-100 ring-2 ring-emerald-200">
                        <img v-if="preview" :src="preview" alt="Prévia da imagem do usuário" class="h-full w-full object-cover" />
                        <span v-else>{{ initials }}</span>
                    </div>
                    <div class="space-y-2">
                        <label class="inline-flex cursor-pointer items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                            Escolher imagem
                            <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="chooseAvatar" />
                        </label>
                        <button v-if="preview" type="button" class="ml-2 text-sm font-medium text-rose-600 hover:text-rose-700" @click="removeAvatar">Remover</button>
                        <p class="text-xs text-slate-500">JPG, PNG ou WebP, até 2 MB.</p>
                    </div>
                </div>
                <InputError class="mt-2" :message="form.errors.avatar" />
            </div>

            <div>
                <InputLabel for="name" value="Nome" />
                <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" required autocomplete="name" />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="E-mail" />
                <TextInput id="email" type="email" class="mt-1 block w-full" v-model="form.email" required autocomplete="username" />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Salvar</PrimaryButton>
                <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                    <p v-if="form.recentlySuccessful" class="text-sm text-gray-600">Salvo.</p>
                </Transition>
            </div>
        </form>
    </section>
</template>
