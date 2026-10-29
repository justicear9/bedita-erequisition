<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import ProductSearch from '@/Components/ProductSearch.vue';
import SignatureBlock from '@/Components/SignatureBlock.vue';
import { formatDate, formatDateTime } from '@/formatDate';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const actionLabel = (action) =>
    ({
        created: 'Draft created',
        submitted: 'Submitted for approval',
        updated: 'Draft updated',
        manager_approved: 'Manager approved',
        manager_sent_back: 'Manager sent back',
        manager_rejected: 'Manager rejected',
        inventory_approved: 'Inventory approved',
        inventory_sent_back: 'Inventory sent back',
        inventory_rejected: 'Inventory rejected',
        inventory_approved_changes: 'Inventory approved changes',
        inventory_sent_changes_back: 'Sent back to warehouse',
        warehouse_submitted_changes: 'Warehouse submitted changes',
        warehouse_released: 'Released',
    })[action] || String(action || '').replaceAll('_', ' ');

const actionTone = (action) => {
    if (String(action).includes('reject')) return 'red';
    if (String(action).includes('sent_back') || String(action).includes('changes')) return 'amber';
    if (String(action).includes('approved') || action === 'released' || action === 'warehouse_released') {
        return 'green';
    }
    if (action === 'submitted') return 'blue';
    return 'slate';
};

const props = defineProps({
    requisition: { type: Object, required: true },
    declarationText: { type: String, required: true },
    permissions: { type: Object, required: true },
});

const noteForm = useForm({ note: '' });
const reasonForm = useForm({ reason: '' });
const modal = ref(null);
const warehouseError = ref('');
const releasing = ref(false);

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

const requestTypeLabel = computed(() => {
    const map = {
        promotion: 'Promotion',
        donation: 'Donation',
        product_demonstration: 'Product demonstration',
        customer_hcp_engagement: 'Customer/HCP engagement',
        other: props.requisition.request_type_other || 'Other',
    };
    return map[props.requisition.request_type] || props.requisition.request_type;
});

const warehouseLines = reactive(
    props.requisition.items.map((item) => ({
        id: item.id,
        product_id: item.product_id,
        original_product_id: item.product_id,
        product: item.product,
        requested_qty: item.requested_qty,
        fulfilled_qty: item.fulfilled_qty ?? item.requested_qty,
        change_reason: item.change_reason || '',
        uom: item.uom,
    })),
);

const lineChanged = (line) =>
    Number(line.fulfilled_qty) !== Number(line.requested_qty) ||
    Number(line.product_id) !== Number(line.original_product_id);

const replaceProduct = (line, product) => {
    if (!product) return;
    line.product_id = product.id;
    line.product = product;
    line.uom = product.uom;
};

const hasStickyActions = computed(
    () =>
        props.permissions.managerDecide ||
        props.permissions.inventoryDecide ||
        props.permissions.inventoryReviewChanges ||
        props.permissions.warehouseFulfill ||
        props.permissions.update,
);

const hasWarehouseChanges = computed(() => warehouseLines.some((line) => lineChanged(line)));

const warehouseActionLabel = computed(() => {
    if (releasing.value) {
        return hasWarehouseChanges.value ? 'Submitting changes…' : 'Releasing…';
    }
    return hasWarehouseChanges.value
        ? 'Submit changes for approval'
        : 'Release';
});

const postAction = (routeName, form) => {
    form.post(route(routeName, props.requisition.id), {
        preserveScroll: true,
        onSuccess: () => {
            modal.value = null;
            form.reset();
        },
    });
};

const release = () => {
    warehouseError.value = '';

    const missingReason = warehouseLines.some(
        (line) => lineChanged(line) && !String(line.change_reason || '').trim(),
    );

    if (missingReason) {
        warehouseError.value = 'Enter a change reason for every line you changed.';
        return;
    }

    releasing.value = true;
    router.post(
        route('requisitions.warehouse.release', props.requisition.id),
        {
            lines: warehouseLines.map((line) => ({
                id: line.id,
                fulfilled_qty: Number(line.fulfilled_qty),
                product_id: line.product_id,
                change_reason: line.change_reason || null,
            })),
        },
        {
            preserveScroll: true,
            onFinish: () => {
                releasing.value = false;
            },
            onError: (errors) => {
                warehouseError.value =
                    errors.lines ||
                    errors.signature ||
                    errors.status ||
                    Object.values(errors)[0] ||
                    'Could not release or submit changes.';
            },
        },
    );
};
</script>

<template>
    <Head :title="requisition.number" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ requisition.number }}
                    </h2>
                    <p class="text-sm text-gray-500">{{ formatDate(requisition.requested_on) }}</p>
                </div>
                <StatusBadge
                    :status="requisition.status"
                    :label="statusLabel(requisition.status)"
                />
            </div>
        </template>

        <div
            class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8"
            :class="{ 'pb-36': hasStickyActions }"
        >
            <div
                v-if="warehouseError || $page.props.errors?.lines || $page.props.errors?.signature || $page.props.errors?.status"
                class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
            >
                {{
                    warehouseError ||
                    $page.props.errors.lines ||
                    $page.props.errors.signature ||
                    $page.props.errors.status
                }}
            </div>

            <div class="grid items-start gap-6 lg:grid-cols-12">
                <div class="space-y-6 lg:col-span-7">
                    <section class="space-y-3 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm sm:p-5">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <p class="text-xs uppercase text-gray-500">Requester</p>
                                <p class="font-medium">{{ requisition.requester.name }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase text-gray-500">Department</p>
                                <p class="font-medium">{{ requisition.department.name }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase text-gray-500">Request type</p>
                                <p class="font-medium">{{ requestTypeLabel }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase text-gray-500">Manager</p>
                                <p class="font-medium">{{ requisition.department.manager?.name || '—' }}</p>
                            </div>
                        </div>
                        <div>
                            <p class="text-xs uppercase text-gray-500">Purpose</p>
                            <p class="mt-1 whitespace-pre-wrap text-gray-800">{{ requisition.purpose }}</p>
                        </div>
                        <div v-if="requisition.declaration_at" class="rounded-md bg-gray-50 p-3 text-sm text-gray-700">
                            <p class="font-medium text-gray-900">Declaration accepted</p>
                            <p class="mt-1">{{ declarationText }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ formatDateTime(requisition.declaration_at) }}</p>
                        </div>
                    </section>

                    <section class="space-y-3 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm sm:p-5">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="font-semibold text-bedita-navy">Line items</h3>
                            <div class="flex flex-wrap gap-2 text-sm">
                                <Link
                                    v-if="permissions.printPickList"
                                    :href="route('requisitions.print.pick-list', requisition.id)"
                                    class="font-medium text-indigo-600"
                                    target="_blank"
                                >
                                    Pick list
                                </Link>
                                <Link
                                    v-if="permissions.printFinal"
                                    :href="route('requisitions.print.final', requisition.id)"
                                    class="font-medium text-indigo-600"
                                    target="_blank"
                                >
                                    Final copy
                                </Link>
                            </div>
                        </div>

                        <div
                            v-if="!permissions.warehouseFulfill"
                            class="grid gap-3 sm:grid-cols-2"
                        >
                            <div
                                v-for="item in requisition.items"
                                :key="item.id"
                                class="rounded-xl border border-gray-100 p-3"
                            >
                                <p class="font-medium text-gray-900">{{ item.product.description }}</p>
                                <p class="text-xs text-gray-500">{{ item.product.sku }} · {{ item.uom }}</p>
                                <p class="mt-1 text-sm">
                                    Requested:
                                    <span class="font-semibold">{{ item.requested_qty }} {{ item.uom }}</span>
                                </p>
                                <p
                                    v-if="item.fulfilled_qty !== null && Number(item.fulfilled_qty) !== Number(item.requested_qty)"
                                    class="mt-1 text-sm font-medium text-amber-800"
                                >
                                    Warehouse qty: {{ item.fulfilled_qty }} {{ item.uom }}
                                </p>
                                <p v-if="item.change_reason" class="mt-1 text-sm text-orange-700">
                                    Reason: {{ item.change_reason }}
                                </p>
                            </div>
                        </div>

                        <div v-else class="space-y-4">
                            <p class="text-sm text-gray-600">
                                Adjust quantities or products if needed. Any change requires a reason and goes to Head of Inventory for approval before final release.
                            </p>
                            <div
                                v-for="line in warehouseLines"
                                :key="line.id"
                                class="space-y-3 rounded-xl border border-gray-200 p-3"
                                :class="{ 'border-amber-300 bg-amber-50/40': lineChanged(line) }"
                            >
                                <p class="font-medium text-gray-900">{{ line.product.description }}</p>
                                <p class="text-xs text-gray-500">
                                    Requested {{ line.requested_qty }} {{ line.uom }}
                                </p>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Fulfill qty</label>
                                    <input
                                        v-model.number="line.fulfilled_qty"
                                        type="number"
                                        min="0"
                                        class="mt-1 block w-full rounded-md border-gray-300 text-base shadow-sm"
                                    />
                                </div>
                                <ProductSearch
                                    :model-value="line.product"
                                    @update:model-value="(p) => replaceProduct(line, p)"
                                />
                                <div v-if="lineChanged(line)">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Change reason <span class="text-bedita-red">*</span>
                                    </label>
                                    <textarea
                                        v-model="line.change_reason"
                                        rows="2"
                                        required
                                        class="mt-1 block w-full rounded-md border-gray-300 text-base shadow-sm"
                                        placeholder="Required for quantity or product changes"
                                    />
                                    <p
                                        v-if="!String(line.change_reason || '').trim()"
                                        class="mt-1 text-xs text-bedita-red"
                                    >
                                        Reason required for this change.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="space-y-3 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm sm:p-5">
                        <h3 class="font-semibold text-bedita-navy">Approvals & signatures</h3>
                        <div
                            v-if="!$page.props.auth.user?.has_signature && (permissions.managerDecide || permissions.inventoryDecide || permissions.warehouseFulfill || permissions.submit)"
                            class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                        >
                            Your profile signature is required to approve or release.
                            <Link :href="route('profile.edit')" class="font-semibold underline">Add it in Profile</Link>.
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <SignatureBlock
                                title="Requester"
                                :name="requisition.requester.name"
                                :url="requisition.requester_signature_url"
                                :signed-at="requisition.requester_signed_at"
                            />
                            <SignatureBlock
                                title="Manager"
                                :name="requisition.department.manager?.name"
                                :url="requisition.manager_signature_url"
                                :signed-at="requisition.manager_signed_at"
                            />
                            <SignatureBlock
                                title="Head of Inventory"
                                :url="requisition.inventory_signature_url"
                                :signed-at="requisition.inventory_signed_at"
                            />
                            <SignatureBlock
                                title="Warehouse"
                                :url="requisition.warehouse_signature_url"
                                :signed-at="requisition.warehouse_signed_at"
                            />
                        </div>
                    </section>
                </div>

                <aside class="lg:col-span-5 lg:sticky lg:top-20 lg:self-start">
                    <section class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm sm:p-5">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="font-semibold text-bedita-navy">Activity</h3>
                            <p class="text-xs text-gray-400">
                                {{ requisition.events?.length || 0 }} events
                            </p>
                        </div>

                        <ol
                            v-if="requisition.events?.length"
                            class="relative mt-4 max-h-[70vh] space-y-0 overflow-y-auto pr-1 lg:max-h-[calc(100vh-10rem)]"
                        >
                            <li
                                v-for="(event, index) in requisition.events"
                                :key="event.id"
                                class="relative flex gap-3 pb-4 last:pb-0"
                            >
                                <div class="relative flex w-4 shrink-0 flex-col items-center">
                                    <span
                                        class="z-10 mt-1.5 h-2.5 w-2.5 rounded-full ring-4 ring-white"
                                        :class="{
                                            'bg-red-500': actionTone(event.action) === 'red',
                                            'bg-amber-500': actionTone(event.action) === 'amber',
                                            'bg-emerald-500': actionTone(event.action) === 'green',
                                            'bg-sky-500': actionTone(event.action) === 'blue',
                                            'bg-slate-400': actionTone(event.action) === 'slate',
                                        }"
                                    />
                                    <span
                                        v-if="index < requisition.events.length - 1"
                                        class="absolute top-4 bottom-0 w-px bg-slate-200"
                                    />
                                </div>
                                <div class="min-w-0 flex-1 rounded-xl bg-slate-50/80 px-3 py-2">
                                    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                                        <p class="text-sm font-semibold text-bedita-navy">
                                            {{ actionLabel(event.action) }}
                                        </p>
                                        <p class="text-[11px] text-gray-400">
                                            {{ formatDateTime(event.created_at) }}
                                        </p>
                                    </div>
                                    <p class="mt-0.5 text-xs text-gray-600">
                                        {{ event.user?.name || 'System' }}
                                    </p>
                                    <p
                                        v-if="event.note"
                                        class="mt-1.5 whitespace-pre-line rounded-lg border border-slate-200/80 bg-white px-2.5 py-1.5 text-xs leading-snug text-gray-700"
                                    >
                                        {{ event.note }}
                                    </p>
                                </div>
                            </li>
                        </ol>
                        <p v-else class="mt-3 text-sm text-gray-500">No activity yet.</p>
                    </section>
                </aside>
            </div>
        </div>

        <!-- Sticky mobile action bar -->
        <div
            v-if="hasStickyActions"
            class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white/95 p-3 backdrop-blur"
        >
            <div class="mx-auto flex max-w-6xl flex-col gap-2 sm:flex-row">
                <Link
                    v-if="permissions.update"
                    :href="route('requisitions.edit', requisition.id)"
                    class="rounded-md border border-gray-300 px-4 py-3 text-center text-sm font-semibold text-gray-700"
                >
                    Edit
                </Link>
                <button
                    v-if="permissions.submit && requisition.status !== 'draft'"
                    type="button"
                    class="rounded-md bg-bedita-navy px-4 py-3 text-sm font-semibold text-white"
                    @click="router.post(route('requisitions.submit', requisition.id))"
                >
                    Resubmit
                </button>

                <template v-if="permissions.managerDecide || permissions.inventoryDecide">
                    <button
                        type="button"
                        class="rounded-md bg-green-600 px-4 py-3 text-sm font-semibold text-white"
                        @click="
                            permissions.managerDecide
                                ? router.post(route('requisitions.manager.approve', requisition.id))
                                : router.post(route('requisitions.inventory.approve', requisition.id))
                        "
                    >
                        Approve
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-amber-500 px-4 py-3 text-sm font-semibold text-white"
                        @click="modal = 'send_back'"
                    >
                        Send back
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-red-600 px-4 py-3 text-sm font-semibold text-white"
                        @click="modal = 'reject'"
                    >
                        Reject
                    </button>
                </template>

                <template v-if="permissions.inventoryReviewChanges">
                    <button
                        type="button"
                        class="rounded-md bg-green-600 px-4 py-3 text-sm font-semibold text-white"
                        @click="
                            router.post(
                                route('requisitions.inventory.approve-changes', requisition.id),
                            )
                        "
                    >
                        Approve changes
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-amber-500 px-4 py-3 text-sm font-semibold text-white"
                        @click="modal = 'send_changes_back'"
                    >
                        Send to warehouse
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-red-600 px-4 py-3 text-sm font-semibold text-white"
                        @click="modal = 'reject'"
                    >
                        Reject
                    </button>
                </template>

                <button
                    v-if="permissions.warehouseFulfill"
                    type="button"
                    class="rounded-md px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                    :class="hasWarehouseChanges ? 'bg-amber-600 hover:bg-amber-700' : 'bg-bedita-red hover:bg-red-700'"
                    :disabled="releasing"
                    @click="release"
                >
                    {{ warehouseActionLabel }}
                </button>
            </div>
        </div>

        <!-- Note / reason modal -->
        <div
            v-if="modal"
            class="fixed inset-0 z-40 flex items-end justify-center bg-black/40 p-4 sm:items-center"
            @click.self="modal = null"
        >
            <div class="w-full max-w-md rounded-lg bg-white p-4 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900">
                    {{
                        modal === 'reject'
                            ? 'Reject requisition'
                            : modal === 'send_changes_back'
                              ? 'Send back to warehouse'
                              : 'Send back with changes'
                    }}
                </h3>
                <textarea
                    v-if="modal === 'reject'"
                    v-model="reasonForm.reason"
                    rows="4"
                    class="mt-3 block w-full rounded-md border-gray-300 text-base shadow-sm"
                    placeholder="Reason required"
                />
                <textarea
                    v-else
                    v-model="noteForm.note"
                    rows="4"
                    class="mt-3 block w-full rounded-md border-gray-300 text-base shadow-sm"
                    placeholder="Note required"
                />
                <div class="mt-4 flex gap-2">
                    <button
                        type="button"
                        class="flex-1 rounded-md border border-gray-300 px-4 py-3 text-sm font-semibold"
                        @click="modal = null"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-md bg-bedita-red px-4 py-3 text-sm font-semibold text-white"
                        @click="
                            modal === 'reject'
                                ? postAction(
                                      permissions.managerDecide
                                          ? 'requisitions.manager.reject'
                                          : 'requisitions.inventory.reject',
                                      reasonForm,
                                  )
                                : modal === 'send_changes_back'
                                  ? postAction(
                                        'requisitions.inventory.send-changes-back',
                                        noteForm,
                                    )
                                  : postAction(
                                        permissions.managerDecide
                                            ? 'requisitions.manager.send-back'
                                            : 'requisitions.inventory.send-back',
                                        noteForm,
                                    )
                        "
                    >
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
