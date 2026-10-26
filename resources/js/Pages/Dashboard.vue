<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    myRequests: { type: Array, default: () => [] },
    queue: { type: Array, default: () => [] },
    role: { type: String, required: true },
    roleLabel: { type: String, required: true },
    hasSignature: { type: Boolean, default: false },
    stats: { type: Object, default: null },
});

const statusLabel = (status) =>
    ({
        draft: 'Draft',
        pending_manager: 'Pending Manager',
        changes_requested: 'Changes Requested',
        pending_inventory: 'Pending Inventory',
        ready_for_warehouse: 'Ready for Warehouse',
        pending_inventory_change_review: 'Pending Change Review',
        rejected: 'Rejected',
        released: 'Released',
    })[status] || status;

const queueTitle = {
    manager: 'Pending my approval',
    inventory_manager: 'Head of Inventory queue',
    warehouse: 'Warehouse queue',
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-[0.18em] text-bedita-red">
                        BEDITA eREQUISITION
                    </p>
                    <h2 class="mt-1 text-2xl font-semibold text-bedita-navy">{{ roleLabel }}</h2>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-if="role === 'inventory_manager'"
                        :href="route('inventory.reports')"
                        class="inline-flex items-center justify-center rounded-xl border border-bedita-navy/15 bg-white px-5 py-3 text-sm font-semibold text-bedita-navy hover:border-bedita-red/30"
                    >
                        Reports
                    </Link>
                    <Link
                        v-if="role === 'requester'"
                        :href="route('requisitions.create')"
                        class="inline-flex items-center justify-center rounded-xl bg-bedita-red px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-red-700"
                    >
                        New requisition
                    </Link>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <div
                v-if="!hasSignature"
                class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900"
            >
                <p class="font-semibold">Signature required</p>
                <p class="mt-1">
                    Add your signature in
                    <Link :href="route('profile.edit')" class="font-semibold underline">Profile</Link>
                    before submitting or approving requests.
                </p>
            </div>

            <section v-if="stats" class="space-y-4">
                <div class="flex items-end justify-between gap-3">
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Overview · {{ stats.month_label }}
                        </h3>
                    </div>
                    <Link
                        :href="route('inventory.reports')"
                        class="text-sm font-semibold text-bedita-red hover:underline"
                    >
                        Open full reports
                    </Link>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Awaiting inventory</p>
                        <p class="mt-2 text-3xl font-semibold text-amber-700">{{ stats.pending_inventory }}</p>
                    </div>
                    <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Change reviews</p>
                        <p class="mt-2 text-3xl font-semibold text-orange-700">{{ stats.change_reviews }}</p>
                    </div>
                    <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Ready for warehouse</p>
                        <p class="mt-2 text-3xl font-semibold text-blue-700">{{ stats.ready_for_warehouse }}</p>
                    </div>
                    <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Released this month</p>
                        <p class="mt-2 text-3xl font-semibold text-green-700">{{ stats.released_this_month }}</p>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-3">
                    <div class="rounded-2xl border border-gray-100 bg-gradient-to-br from-bedita-navy to-bedita-navy-dark p-5 text-white shadow-sm lg:col-span-1">
                        <p class="text-xs font-semibold uppercase tracking-wide text-white/70">This month</p>
                        <p class="mt-3 text-3xl font-semibold">{{ stats.units_requested_month }}</p>
                        <p class="mt-1 text-sm text-white/80">Units requested</p>
                        <p class="mt-4 text-sm text-white/70">{{ stats.total_open }} open across pipeline</p>
                    </div>
                    <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <h4 class="font-semibold text-bedita-navy">By department</h4>
                        <ul class="mt-3 space-y-2">
                            <li
                                v-for="row in stats.by_department"
                                :key="row.label"
                                class="flex justify-between text-sm"
                            >
                                <span class="text-gray-600">{{ row.label }}</span>
                                <span class="font-semibold text-bedita-navy">{{ row.total }}</span>
                            </li>
                            <li v-if="!stats.by_department?.length" class="text-sm text-gray-400">No activity yet</li>
                        </ul>
                    </div>
                    <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <h4 class="font-semibold text-bedita-navy">By request type</h4>
                        <ul class="mt-3 space-y-2">
                            <li
                                v-for="row in stats.by_type"
                                :key="row.label"
                                class="flex justify-between text-sm"
                            >
                                <span class="capitalize text-gray-600">{{ String(row.label).replaceAll('_', ' ') }}</span>
                                <span class="font-semibold text-bedita-navy">{{ row.total }}</span>
                            </li>
                            <li v-if="!stats.by_type?.length" class="text-sm text-gray-400">No activity yet</li>
                        </ul>
                    </div>
                </div>
            </section>

            <section v-if="queue.length" class="space-y-3">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                    {{ queueTitle[role] || 'Queue' }}
                </h3>
                <div class="grid gap-3">
                    <Link
                        v-for="req in queue"
                        :key="req.id"
                        :href="route('requisitions.show', req.id)"
                        class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm transition hover:border-bedita-red/20 hover:shadow-md"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-bedita-navy">{{ req.number }}</p>
                                <p class="mt-1 text-sm text-gray-600">
                                    {{ req.requester?.name }} · {{ req.department?.name }}
                                </p>
                            </div>
                            <StatusBadge :status="req.status" :label="statusLabel(req.status)" />
                        </div>
                    </Link>
                </div>
            </section>

            <section class="space-y-3">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                    {{ role === 'requester' ? 'My request history' : 'Recent requisitions' }}
                </h3>
                <div
                    v-if="!myRequests.length"
                    class="rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-12 text-center text-sm text-gray-500"
                >
                    No requisitions yet.
                </div>
                <div class="grid gap-3 md:hidden">
                    <Link
                        v-for="req in myRequests"
                        :key="req.id"
                        :href="route('requisitions.show', req.id)"
                        class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-bedita-navy">{{ req.number }}</p>
                                <p class="text-sm text-gray-500">
                                    {{ req.requested_on?.slice?.(0, 10) || req.requested_on }}
                                </p>
                            </div>
                            <StatusBadge :status="req.status" :label="statusLabel(req.status)" />
                        </div>
                    </Link>
                </div>
                <div class="hidden overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm md:block">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-slate-50/80">
                            <tr>
                                <th class="px-5 py-3 text-left font-semibold text-gray-500">Number</th>
                                <th class="px-5 py-3 text-left font-semibold text-gray-500">Requester</th>
                                <th class="px-5 py-3 text-left font-semibold text-gray-500">Department</th>
                                <th class="px-5 py-3 text-left font-semibold text-gray-500">Date</th>
                                <th class="px-5 py-3 text-left font-semibold text-gray-500">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="req in myRequests" :key="req.id" class="hover:bg-slate-50/60">
                                <td class="px-5 py-3.5">
                                    <Link
                                        :href="route('requisitions.show', req.id)"
                                        class="font-semibold text-bedita-navy hover:text-bedita-red"
                                    >
                                        {{ req.number }}
                                    </Link>
                                </td>
                                <td class="px-5 py-3.5 text-gray-700">{{ req.requester?.name }}</td>
                                <td class="px-5 py-3.5 text-gray-700">{{ req.department?.name }}</td>
                                <td class="px-5 py-3.5 text-gray-700">
                                    {{ req.requested_on?.slice?.(0, 10) || req.requested_on }}
                                </td>
                                <td class="px-5 py-3.5">
                                    <StatusBadge :status="req.status" :label="statusLabel(req.status)" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
