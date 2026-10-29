<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { formatDate } from '@/formatDate';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

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

const money = (value) =>
    `GHS ${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

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

const setPreset = (days) => {
    const to = new Date();
    const from = new Date();
    from.setDate(to.getDate() - (days - 1));
    form.to = to.toISOString().slice(0, 10);
    form.from = from.toISOString().slice(0, 10);
    apply();
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

const maxSeriesValue = computed(() =>
    Math.max(1, ...(props.summary.series || []).map((row) => Number(row.value) || 0)),
);

const insightTone = (tone) =>
    ({
        amber: 'border-amber-200 bg-amber-50 text-amber-950',
        red: 'border-red-200 bg-red-50 text-red-950',
        blue: 'border-sky-200 bg-sky-50 text-sky-950',
        green: 'border-emerald-200 bg-emerald-50 text-emerald-950',
        slate: 'border-slate-200 bg-slate-50 text-slate-900',
    })[tone] || 'border-gray-200 bg-white text-gray-900';

const lineValue = (req) =>
    (req.items || []).reduce((sum, item) => {
        const qty = item.fulfilled_qty ?? item.requested_qty ?? 0;
        return sum + Number(item.unit_price || 0) * Number(qty);
    }, 0);
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
                    <h2 class="mt-1 text-2xl font-semibold text-bedita-navy">Intelligence reports</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Value, fill rate, shortfalls, and 30-day demand projection.
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

        <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
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
                    <div class="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-5">
                        <button
                            type="submit"
                            class="rounded-xl bg-bedita-red px-5 py-2.5 text-sm font-semibold text-white hover:bg-red-700"
                        >
                            Apply filters
                        </button>
                        <button type="button" class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-semibold text-bedita-navy" @click="setPreset(7)">
                            Last 7 days
                        </button>
                        <button type="button" class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-semibold text-bedita-navy" @click="setPreset(30)">
                            Last 30 days
                        </button>
                    </div>
                </form>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <div class="rounded-2xl border border-gray-100 bg-gradient-to-br from-white to-slate-50 p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Requested value</p>
                    <p class="mt-2 text-2xl font-semibold text-bedita-navy">{{ money(summary.kpis?.requested_value) }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ summary.total }} requisitions</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-gradient-to-br from-white to-emerald-50/60 p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Released value</p>
                    <p class="mt-2 text-2xl font-semibold text-emerald-800">{{ money(summary.kpis?.released_value) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Fill rate {{ summary.kpis?.fill_rate ?? 0 }}%</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-gradient-to-br from-white to-amber-50/70 p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pipeline value</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-800">{{ money(summary.kpis?.pipeline_value) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Still in approval / warehouse</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-gradient-to-br from-white to-red-50/50 p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Shortfall value</p>
                    <p class="mt-2 text-2xl font-semibold text-red-800">{{ money(summary.kpis?.shortfall_value) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Released under-fulfilled lines</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Rejected</p>
                    <p class="mt-2 text-2xl font-semibold text-bedita-navy">{{ summary.rejected }}</p>
                    <p class="mt-1 text-xs text-gray-500">Rejection rate {{ summary.kpis?.rejection_rate ?? 0 }}%</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pending inventory</p>
                    <p class="mt-2 text-2xl font-semibold text-bedita-navy">{{ summary.pending_inventory }}</p>
                    <p class="mt-1 text-xs text-gray-500">Awaiting inventory decision</p>
                </div>
            </section>

            <section class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-bedita-navy">Daily request value</h3>
                    <div class="mt-4 flex h-40 items-end gap-1">
                        <div
                            v-for="row in summary.series || []"
                            :key="row.date"
                            class="group relative flex-1 rounded-t bg-bedita-navy/80 transition hover:bg-bedita-red"
                            :style="{ height: `${Math.max(4, (Number(row.value) / maxSeriesValue) * 100)}%` }"
                            :title="`${row.date}: ${money(row.value)} (${row.count})`"
                        />
                    </div>
                    <p class="mt-2 text-xs text-gray-500">Hover bars for date and value.</p>
                </div>

                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-bedita-navy">Insights</h3>
                    <ul class="mt-4 space-y-3">
                        <li
                            v-for="(card, index) in summary.insights || []"
                            :key="index"
                            class="rounded-xl border px-4 py-3 text-sm"
                            :class="insightTone(card.tone)"
                        >
                            <p class="font-semibold">{{ card.title }}</p>
                            <p class="mt-1 opacity-90">{{ card.detail }}</p>
                        </li>
                    </ul>
                </div>
            </section>

            <section class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-bedita-navy">30-day projection (top SKUs)</h3>
                    <ul class="mt-4 space-y-3">
                        <li
                            v-for="row in summary.forecast || []"
                            :key="row.sku"
                            class="flex items-start justify-between gap-3 text-sm"
                        >
                            <div>
                                <p class="font-medium text-bedita-navy">{{ row.description }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ row.sku }} · {{ row.avg_daily }}/day
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-bedita-navy">{{ row.projected_30 }}</p>
                                <p class="text-xs text-gray-500">{{ money(row.value_30) }}</p>
                            </div>
                        </li>
                        <li v-if="!(summary.forecast || []).length" class="text-sm text-gray-400">No data</li>
                    </ul>
                </div>

                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-bedita-navy">Shortfalls (released)</h3>
                    <ul class="mt-4 space-y-3">
                        <li
                            v-for="row in summary.shortfalls || []"
                            :key="row.sku"
                            class="flex items-start justify-between gap-3 text-sm"
                        >
                            <div>
                                <p class="font-medium text-bedita-navy">{{ row.description }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ row.sku }} · {{ row.fulfilled }}/{{ row.requested }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-red-700">-{{ row.shortfall }}</p>
                                <p class="text-xs text-gray-500">{{ money(row.value) }}</p>
                            </div>
                        </li>
                        <li v-if="!(summary.shortfalls || []).length" class="text-sm text-gray-400">No shortfalls</li>
                    </ul>
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
                    <h3 class="font-semibold text-bedita-navy">Top products by value</h3>
                    <ul class="mt-4 space-y-3">
                        <li
                            v-for="row in summary.top_products"
                            :key="row.sku"
                            class="text-sm"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-medium text-bedita-navy">{{ row.description }}</p>
                                    <p class="text-xs text-gray-500">{{ row.sku }} · qty {{ row.qty }}</p>
                                </div>
                                <span class="font-semibold text-bedita-navy">{{ money(row.value) }}</span>
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
                                <th class="px-4 py-3 text-left font-semibold text-gray-500">Lines</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-500">Value</th>
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
                                <td class="px-4 py-3 text-gray-700">{{ req.items?.length || 0 }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ money(lineValue(req)) }}</td>
                                <td class="px-4 py-3">
                                    <StatusBadge :status="req.status" :label="statusLabel(req.status)" />
                                </td>
                            </tr>
                            <tr v-if="!requisitions.data.length">
                                <td colspan="7" class="px-4 py-8 text-center text-gray-400">
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
