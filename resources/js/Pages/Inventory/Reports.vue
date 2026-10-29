<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { formatDate } from '@/formatDate';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    filters: { type: Object, required: true },
    statuses: { type: Array, required: true },
    requestTypes: { type: Array, required: true },
    departments: { type: Array, required: true },
    requisitions: { type: Object, required: true },
    summary: { type: Object, required: true },
});

const form = reactive({
    from: props.filters.from,
    to: props.filters.to,
    status: props.filters.status || '',
    department_id: props.filters.department_id || '',
    request_type: props.filters.request_type || '',
});

const statusLabel = (status) =>
    props.statuses.find((s) => s.value === status)?.label || status;

const openRequisition = (id) => {
    router.visit(route('requisitions.show', id));
};

const apply = () => {
    router.get(route('inventory.reports'), {
        from: form.from,
        to: form.to,
        status: form.status || undefined,
        department_id: form.department_id || undefined,
        request_type: form.request_type || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const exportUrl = () => {
    const params = new URLSearchParams();
    Object.entries({
        from: form.from,
        to: form.to,
        status: form.status,
        department_id: form.department_id,
        request_type: form.request_type,
    }).forEach(([key, value]) => {
        if (value) params.set(key, value);
    });
    return `${route('inventory.reports.export')}?${params.toString()}`;
};
</script>

<template>
    <Head title="Inventory Reports" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-bedita-red">
                        Head of Inventory
                    </p>
                    <h2 class="mt-1 text-2xl font-semibold text-bedita-navy">Reports</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Filter requisitions and export line-level CSV for analysis.
                    </p>
                </div>
                <a
                    :href="exportUrl()"
                    class="inline-flex items-center justify-center rounded-xl bg-bedita-navy px-5 py-3 text-sm font-semibold text-white hover:bg-bedita-navy-dark"
                >
                    Export CSV
                </a>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="apply">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">From</label>
                        <input v-model="form.from" type="date" class="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">To</label>
                        <input v-model="form.to" type="date" class="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status</label>
                        <select v-model="form.status" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            <option value="">All</option>
                            <option v-for="status in statuses" :key="status.value" :value="status.value">
                                {{ status.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Department</label>
                        <select v-model="form.department_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            <option value="">All</option>
                            <option v-for="dep in departments" :key="dep.id" :value="dep.id">
                                {{ dep.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Request type</label>
                        <select v-model="form.request_type" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            <option value="">All</option>
                            <option v-for="type in requestTypes" :key="type.value" :value="type.value">
                                {{ type.label }}
                            </option>
                        </select>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-5">
                        <button
                            type="submit"
                            class="rounded-xl bg-bedita-red px-5 py-2.5 text-sm font-semibold text-white hover:bg-red-700"
                        >
                            Apply filters
                        </button>
                    </div>
                </form>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total in range</p>
                    <p class="mt-2 text-3xl font-semibold text-bedita-navy">{{ summary.total }}</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Released</p>
                    <p class="mt-2 text-3xl font-semibold text-green-700">{{ summary.released }}</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pending inventory</p>
                    <p class="mt-2 text-3xl font-semibold text-amber-700">{{ summary.pending_inventory }}</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Rejected</p>
                    <p class="mt-2 text-3xl font-semibold text-red-700">{{ summary.rejected }}</p>
                </div>
            </section>

            <section class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-bedita-navy">By department</h3>
                    <ul class="mt-4 space-y-2">
                        <li
                            v-for="row in summary.by_department"
                            :key="row.department"
                            class="flex items-center justify-between text-sm"
                        >
                            <span class="text-gray-700">{{ row.department }}</span>
                            <span class="font-semibold text-bedita-navy">{{ row.total }}</span>
                        </li>
                        <li v-if="!summary.by_department.length" class="text-sm text-gray-400">No data</li>
                    </ul>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-bedita-navy">Top products (requested qty)</h3>
                    <ul class="mt-4 space-y-3">
                        <li
                            v-for="row in summary.top_products"
                            :key="row.sku"
                            class="text-sm"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-medium text-bedita-navy">{{ row.description }}</p>
                                    <p class="text-xs text-gray-500">{{ row.sku }}</p>
                                </div>
                                <span class="font-semibold text-bedita-navy">{{ row.qty }}</span>
                            </div>
                        </li>
                        <li v-if="!summary.top_products.length" class="text-sm text-gray-400">No data</li>
                    </ul>
                </div>
            </section>

            <section class="space-y-3">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Requisitions</h3>
                <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-slate-50/80">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-500">Number</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-500">Date</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-500">Requester</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-500">Department</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-500">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr
                                v-for="req in requisitions.data"
                                :key="req.id"
                                class="cursor-pointer hover:bg-slate-50/60 active:bg-slate-100"
                                role="link"
                                tabindex="0"
                                @click="openRequisition(req.id)"
                                @keydown.enter.prevent="openRequisition(req.id)"
                            >
                                <td class="px-4 py-3 font-semibold text-bedita-navy">
                                    {{ req.number }}
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ formatDate(req.requested_on) }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ req.requester?.name }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ req.department?.name }}</td>
                                <td class="px-4 py-3">
                                    <StatusBadge :status="req.status" :label="statusLabel(req.status)" />
                                </td>
                            </tr>
                            <tr v-if="!requisitions.data.length">
                                <td colspan="5" class="px-4 py-8 text-center text-gray-400">
                                    No requisitions match these filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="requisitions.prev_page_url || requisitions.next_page_url"
                    class="flex justify-between"
                >
                    <Link
                        v-if="requisitions.prev_page_url"
                        :href="requisitions.prev_page_url"
                        class="text-sm font-semibold text-bedita-navy"
                    >
                        Previous
                    </Link>
                    <span v-else />
                    <Link
                        v-if="requisitions.next_page_url"
                        :href="requisitions.next_page_url"
                        class="text-sm font-semibold text-bedita-navy"
                    >
                        Next
                    </Link>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
