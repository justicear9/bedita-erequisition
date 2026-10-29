<script setup>
import { formatDate, formatDateTime } from '@/formatDate';
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';

const props = defineProps({
    mode: { type: String, required: true },
    title: { type: String, required: true },
    requisition: { type: Object, required: true },
});

onMounted(() => {
    setTimeout(() => window.print(), 300);
});

const qty = (item) =>
    props.mode === 'final' && item.fulfilled_qty !== null
        ? item.fulfilled_qty
        : item.requested_qty;
</script>

<template>
    <Head :title="title" />

    <div class="mx-auto max-w-3xl bg-white p-6 text-gray-900 print:p-0">
        <div class="mb-6 flex items-start justify-between gap-4 border-b border-gray-200 pb-4 print:mb-4">
            <div class="flex items-start gap-4">
                <img src="/images/bedita-logo.png" alt="Bedita" class="h-12 w-auto object-contain" />
                <div>
                    <p class="text-xs font-semibold tracking-[0.18em] text-bedita-red">
                        BEDITA eREQUISITION
                    </p>
                    <h1 class="text-2xl font-bold text-bedita-navy">{{ title }}</h1>
                    <p class="mt-1 text-sm text-gray-600">{{ requisition.number }}</p>
                </div>
            </div>
            <button
                type="button"
                class="rounded-lg bg-bedita-navy px-4 py-2 text-sm font-semibold text-white print:hidden"
                @click="window.print()"
            >
                Print
            </button>
        </div>

        <dl class="mb-6 grid grid-cols-2 gap-3 text-sm">
            <div>
                <dt class="text-gray-500">Date</dt>
                <dd class="font-medium text-bedita-navy">{{ formatDate(requisition.requested_on) }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Status</dt>
                <dd class="font-medium text-bedita-navy">{{ requisition.status }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Requester</dt>
                <dd class="font-medium text-bedita-navy">{{ requisition.requester.name }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Department</dt>
                <dd class="font-medium text-bedita-navy">{{ requisition.department.name }}</dd>
            </div>
        </dl>

        <p class="mb-4 text-sm">
            <span class="text-gray-500">Purpose:</span> {{ requisition.purpose }}
        </p>

        <table class="w-full border-collapse text-sm">
            <thead>
                <tr class="border-b-2 border-bedita-navy text-left">
                    <th class="py-2 pr-2">SKU</th>
                    <th class="py-2 pr-2">Description</th>
                    <th class="py-2 pr-2">UoM</th>
                    <th class="py-2 text-right">Qty</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="item in requisition.items"
                    :key="item.id"
                    class="border-b border-gray-100"
                >
                    <td class="py-2 pr-2 align-top">{{ item.product.sku }}</td>
                    <td class="py-2 pr-2 align-top">
                        {{ item.product.description }}
                        <p v-if="item.change_reason" class="text-xs text-gray-500">
                            Note: {{ item.change_reason }}
                        </p>
                        <ul v-if="item.lots?.length" class="mt-1 space-y-0.5 text-xs text-gray-600">
                            <li v-for="lot in item.lots" :key="`${lot.id || lot.lot_no}-${lot.expires_on}`">
                                Lot {{ lot.lot_no }} · Exp {{ (lot.expires_on || '').toString().slice(0, 10) }} · Qty {{ lot.qty }}
                            </li>
                        </ul>
                    </td>
                    <td class="py-2 pr-2 align-top">{{ item.uom }}</td>
                    <td class="py-2 text-right align-top font-semibold">{{ qty(item) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="mt-10 grid grid-cols-2 gap-4">
            <div
                v-for="block in [
                    {
                        title: 'Requester',
                        name: requisition.requester?.name,
                        url: requisition.requester_signature_url,
                        at: requisition.requester_signed_at,
                    },
                    {
                        title: 'Manager',
                        name: requisition.department?.manager?.name,
                        url: requisition.manager_signature_url,
                        at: requisition.manager_signed_at,
                    },
                    {
                        title: 'Head of Inventory',
                        name: null,
                        url: requisition.inventory_signature_url,
                        at: requisition.inventory_signed_at,
                    },
                    {
                        title: 'Warehouse',
                        name: null,
                        url: requisition.warehouse_signature_url,
                        at: requisition.warehouse_signed_at,
                    },
                ]"
                :key="block.title"
                class="rounded-lg border border-gray-200 p-3"
            >
                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                    {{ block.title }}
                </p>
                <div class="mt-2 flex h-14 items-end">
                    <img
                        v-if="block.url"
                        :src="block.url"
                        class="max-h-12 max-w-full object-contain"
                        alt=""
                    />
                    <span v-else class="text-xs italic text-gray-400">________________</span>
                </div>
                <p class="mt-2 border-t border-gray-100 pt-1 text-xs font-medium text-bedita-navy">
                    {{ block.name || ' ' }}
                </p>
                <p v-if="block.at" class="text-[10px] text-gray-500">{{ formatDateTime(block.at) }}</p>
            </div>
        </div>

        <p v-if="mode === 'final'" class="mt-8 text-xs text-gray-500">
            Final release copy — goods issued against this requisition.
        </p>
        <p v-else class="mt-8 text-xs text-gray-500">
            Warehouse pick list — final signed copy available after release.
        </p>
    </div>
</template>
