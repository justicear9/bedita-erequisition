<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SignaturePad from '@/Components/SignaturePad.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    users: { type: Array, required: true },
    roles: { type: Array, required: true },
    departments: { type: Array, required: true },
});

const editing = ref(null);
const signatureUser = ref(null);
const padData = ref('');

const form = useForm({
    name: '',
    email: '',
    role: 'requester',
    department_id: '',
    password: '',
});

const isCreate = computed(() => !editing.value);

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.role = 'requester';
    form.department_id = '';
};

const openEdit = (user) => {
    editing.value = user;
    form.name = user.name;
    form.email = user.email;
    form.role = user.role;
    form.department_id = user.department_id ?? '';
    form.password = '';
    form.clearErrors();
};

const save = () => {
    const payload = {
        ...form.data(),
        department_id: form.department_id || null,
    };

    if (isCreate.value) {
        form.transform(() => payload).post(route('admin.users.store'), {
            preserveScroll: true,
            onSuccess: () => openCreate(),
        });
    } else {
        form.transform(() => payload).put(route('admin.users.update', editing.value.id), {
            preserveScroll: true,
            onSuccess: () => openCreate(),
        });
    }
};

const remove = (user) => {
    if (!confirm(`Delete ${user.name}?`)) return;
    router.delete(route('admin.users.destroy', user.id), { preserveScroll: true });
};

const openSignature = (user) => {
    signatureUser.value = user;
    padData.value = '';
};

const saveSignature = () => {
    if (!signatureUser.value || !padData.value) return;
    router.post(
        route('admin.users.signature.update', signatureUser.value.id),
        { signature_data: padData.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                signatureUser.value = null;
                padData.value = '';
            },
        },
    );
};

const clearSignature = (user) => {
    router.delete(route('admin.users.signature.destroy', user.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Users" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-bedita-red">Admin</p>
                    <h2 class="text-2xl font-semibold text-bedita-navy">Users</h2>
                </div>
                <button
                    type="button"
                    class="rounded-lg bg-bedita-red px-4 py-2.5 text-sm font-semibold text-white"
                    @click="openCreate"
                >
                    New user
                </button>
            </div>
        </template>

        <div class="mx-auto grid max-w-6xl gap-6 px-4 py-8 lg:grid-cols-5 lg:px-8">
            <div class="space-y-3 lg:col-span-3">
                <div
                    v-for="user in users"
                    :key="user.id"
                    class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-bedita-navy">{{ user.name }}</p>
                            <p class="text-sm text-gray-500">{{ user.email }}</p>
                            <p class="mt-1 text-xs text-gray-500">
                                {{ user.role_label }}
                                <span v-if="user.department"> · {{ user.department }}</span>
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-semibold text-bedita-navy"
                                @click="openEdit(user)"
                            >
                                Edit
                            </button>
                            <button
                                type="button"
                                class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-semibold text-bedita-navy"
                                @click="openSignature(user)"
                            >
                                Signature
                            </button>
                            <button
                                type="button"
                                class="rounded-md px-3 py-1.5 text-xs font-semibold text-bedita-red"
                                @click="remove(user)"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-3">
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="user.has_signature ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-800'"
                        >
                            {{ user.has_signature ? 'Signature on file' : 'No signature' }}
                        </span>
                        <img
                            v-if="user.signature_url"
                            :src="user.signature_url"
                            class="h-8 object-contain"
                            alt=""
                        />
                        <button
                            v-if="user.has_signature"
                            type="button"
                            class="text-xs text-gray-500 hover:text-bedita-red"
                            @click="clearSignature(user)"
                        >
                            Clear
                        </button>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm lg:col-span-2 lg:sticky lg:top-6 lg:self-start">
                <h3 class="text-lg font-semibold text-bedita-navy">
                    {{ isCreate ? 'Create user' : `Edit ${editing.name}` }}
                </h3>
                <form class="mt-4 space-y-3" @submit.prevent="save">
                    <div>
                        <label class="text-sm font-medium text-bedita-navy">Name</label>
                        <input v-model="form.name" class="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-bedita-navy">Email</label>
                        <input v-model="form.email" type="email" class="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-bedita-navy">Role</label>
                        <select v-model="form.role" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            <option v-for="role in roles" :key="role.value" :value="role.value">
                                {{ role.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-bedita-navy">Department</label>
                        <select v-model="form.department_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            <option value="">None</option>
                            <option v-for="dep in departments" :key="dep.id" :value="dep.id">
                                {{ dep.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-bedita-navy">
                            Password {{ isCreate ? '' : '(optional)' }}
                        </label>
                        <input
                            v-model="form.password"
                            type="password"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm"
                        />
                        <InputError :message="form.errors.password" />
                    </div>
                    <button
                        type="submit"
                        class="w-full rounded-lg bg-bedita-navy px-4 py-2.5 text-sm font-semibold text-white"
                        :disabled="form.processing"
                    >
                        {{ isCreate ? 'Create' : 'Save changes' }}
                    </button>
                </form>
            </div>
        </div>

        <div
            v-if="signatureUser"
            class="fixed inset-0 z-40 flex items-end justify-center bg-black/40 p-4 sm:items-center"
            @click.self="signatureUser = null"
        >
            <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl">
                <h3 class="text-lg font-semibold text-bedita-navy">
                    Signature — {{ signatureUser.name }}
                </h3>
                <div class="mt-4">
                    <SignaturePad v-model="padData" />
                </div>
                <div class="mt-4 flex gap-2">
                    <button
                        type="button"
                        class="flex-1 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold"
                        @click="signatureUser = null"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-lg bg-bedita-red px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-40"
                        :disabled="!padData"
                        @click="saveSignature"
                    >
                        Save signature
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
