<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    users: { type: Array, required: true },
});

const clearSignature = (user) => {
    if (!confirm(`Clear signature for ${user.name}?`)) return;
    router.delete(route('admin.users.signature.destroy', user.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Signatures" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-bedita-red">Admin</p>
                <h2 class="text-2xl font-semibold text-bedita-navy">Signature management</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Staff signatures live on user profiles and are stamped onto requisitions at each approval step.
                </p>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="grid gap-4 sm:grid-cols-2">
                <div
                    v-for="user in users"
                    :key="user.id"
                    class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-bedita-navy">{{ user.name }}</p>
                            <p class="text-sm text-gray-500">{{ user.role_label }}</p>
                            <p v-if="user.department" class="text-xs text-gray-400">{{ user.department }}</p>
                        </div>
                        <Link
                            :href="route('admin.users.index')"
                            class="text-xs font-semibold text-bedita-red hover:underline"
                        >
                            Manage user
                        </Link>
                    </div>

                    <div class="mt-4 flex min-h-16 items-center justify-center rounded-xl bg-slate-50 p-3">
                        <img
                            v-if="user.signature_url"
                            :src="user.signature_url"
                            :alt="`${user.name} signature`"
                            class="max-h-14 object-contain"
                        />
                        <p v-else class="text-sm italic text-gray-400">No signature on file</p>
                    </div>

                    <button
                        v-if="user.has_signature"
                        type="button"
                        class="mt-3 text-sm font-medium text-bedita-red"
                        @click="clearSignature(user)"
                    >
                        Clear signature
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
