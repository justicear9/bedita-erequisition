<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    departments: { type: Array, required: true },
    managers: { type: Array, required: true },
});

const editing = ref(null);

const form = useForm({
    name: '',
    manager_id: '',
});

const isCreate = computed(() => !editing.value);

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.manager_id = '';
    form.clearErrors();
};

const openEdit = (department) => {
    editing.value = department;
    form.name = department.name;
    form.manager_id = department.manager_id ?? '';
    form.clearErrors();
};

const save = () => {
    const payload = {
        name: form.name,
        manager_id: form.manager_id || null,
    };

    if (isCreate.value) {
        form.transform(() => payload).post(route('admin.departments.store'), {
            preserveScroll: true,
            onSuccess: () => openCreate(),
        });
    } else {
        form.transform(() => payload).put(route('admin.departments.update', editing.value.id), {
            preserveScroll: true,
            onSuccess: () => openCreate(),
        });
    }
};

const remove = (department) => {
    if (!confirm(`Delete ${department.name}?`)) return;
    router.delete(route('admin.departments.destroy', department.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Departments" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-bedita-red">Admin</p>
                    <h2 class="text-2xl font-semibold text-bedita-navy">Departments</h2>
                </div>
                <button
                    type="button"
                    class="rounded-lg bg-bedita-red px-4 py-2.5 text-sm font-semibold text-white"
                    @click="openCreate"
                >
                    New department
                </button>
            </div>
        </template>

        <div class="mx-auto grid max-w-5xl gap-6 px-4 py-8 lg:grid-cols-5 lg:px-8">
            <div class="space-y-3 lg:col-span-3">
                <div
                    v-for="department in departments"
                    :key="department.id"
                    class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-bedita-navy">{{ department.name }}</p>
                            <p class="mt-1 text-sm text-gray-500">
                                Manager: {{ department.manager_name || 'Unassigned' }}
                            </p>
                            <p class="text-xs text-gray-400">{{ department.users_count }} users</p>
                        </div>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-semibold text-bedita-navy"
                                @click="openEdit(department)"
                            >
                                Edit
                            </button>
                            <button
                                type="button"
                                class="rounded-md px-3 py-1.5 text-xs font-semibold text-bedita-red"
                                @click="remove(department)"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm lg:col-span-2 lg:sticky lg:top-6 lg:self-start">
                <h3 class="text-lg font-semibold text-bedita-navy">
                    {{ isCreate ? 'Create department' : `Edit ${editing.name}` }}
                </h3>
                <form class="mt-4 space-y-3" @submit.prevent="save">
                    <div>
                        <label class="text-sm font-medium text-bedita-navy">Name</label>
                        <input v-model="form.name" class="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-bedita-navy">Manager</label>
                        <select v-model="form.manager_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            <option value="">Unassigned</option>
                            <option v-for="manager in managers" :key="manager.id" :value="manager.id">
                                {{ manager.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.manager_id" />
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
    </AuthenticatedLayout>
</template>
