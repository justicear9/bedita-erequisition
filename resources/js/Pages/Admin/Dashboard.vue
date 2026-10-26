<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    stats: { type: Object, required: true },
});
</script>

<template>
    <Head title="Admin" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-bedita-red">Administration</p>
                <h2 class="mt-1 text-2xl font-semibold text-bedita-navy">Control centre</h2>
            </div>
        </template>

        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="card in [
                        { label: 'Users', value: stats.users, href: route('admin.users.index') },
                        { label: 'Departments', value: stats.departments, href: route('admin.departments.index') },
                        { label: 'Requisitions', value: stats.requisitions, href: route('dashboard') },
                        { label: 'Signatures on file', value: stats.with_signatures, href: route('admin.signatures.index') },
                    ]"
                    :key="card.label"
                    class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ card.label }}</p>
                    <p class="mt-3 text-3xl font-semibold text-bedita-navy">{{ card.value }}</p>
                    <Link :href="card.href" class="mt-4 inline-block text-sm font-medium text-bedita-red hover:underline">
                        Manage
                    </Link>
                </div>
            </div>

            <div class="mt-8 grid gap-4 md:grid-cols-3">
                <Link
                    :href="route('admin.users.index')"
                    class="rounded-2xl border border-bedita-navy/10 bg-gradient-to-br from-bedita-navy to-bedita-navy-dark p-6 text-white shadow-sm transition hover:shadow-md"
                >
                    <h3 class="text-lg font-semibold">Users</h3>
                    <p class="mt-2 text-sm text-white/80">Create accounts, assign roles and departments.</p>
                </Link>
                <Link
                    :href="route('admin.departments.index')"
                    class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:border-bedita-red/30 hover:shadow-md"
                >
                    <h3 class="text-lg font-semibold text-bedita-navy">Departments</h3>
                    <p class="mt-2 text-sm text-gray-600">Set department managers for approval routing.</p>
                </Link>
                <Link
                    :href="route('admin.signatures.index')"
                    class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:border-bedita-red/30 hover:shadow-md"
                >
                    <h3 class="text-lg font-semibold text-bedita-navy">Signatures</h3>
                    <p class="mt-2 text-sm text-gray-600">Review and manage staff signature records.</p>
                </Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
