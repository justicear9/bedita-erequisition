<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProductSearch from '@/Components/ProductSearch.vue';
import InputError from '@/Components/InputError.vue';
import { toDateInputValue } from '@/formatDate';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    requisition: { type: Object, default: null },
    declarationText: { type: String, required: true },
    requestTypes: { type: Array, required: true },
    requester: { type: Object, required: true },
    today: { type: String, required: true },
});

const isEdit = computed(() => !!props.requisition);

const emptyRow = () => ({
    product_id: null,
    qty: 1,
    product: null,
});

const minDate = props.today;
const existingDate = toDateInputValue(props.requisition?.requested_on);
const initialRequestedOn =
    existingDate && existingDate >= props.today ? existingDate : props.today;

const form = useForm({
    requested_on: initialRequestedOn,
    request_type: props.requisition?.request_type ?? 'promotion',
    request_type_other: props.requisition?.request_type_other ?? '',
    purpose: props.requisition?.purpose ?? '',
    declaration: !!props.requisition?.declaration_at,
    items: props.requisition?.items?.length
        ? props.requisition.items.map((item) => ({
              product_id: item.product_id,
              qty: item.requested_qty,
              product: item.product,
          }))
        : [emptyRow()],
    should_submit: false,
});

const setProduct = (index, product) => {
    form.items[index].product = product;
    form.items[index].product_id = product?.id ?? null;
};

const addRow = () => {
    form.items.push(emptyRow());
};

const removeRow = (index) => {
    if (form.items.length === 1) {
        form.items.splice(0, 1, emptyRow());
        return;
    }
    form.items.splice(index, 1);
};

const payloadItems = () =>
    form.items
        .filter((item) => item.product_id && item.qty >= 1)
        .map((item) => ({
            product_id: item.product_id,
            qty: Number(item.qty),
        }));

const save = (shouldSubmit) => {
    const options = {
        preserveScroll: true,
        onFinish: () => {
            form.transform((data) => data);
        },
    };

    form
        .transform((data) => ({
            requested_on: data.requested_on,
            request_type: data.request_type,
            request_type_other: data.request_type_other,
            purpose: data.purpose,
            declaration: !!data.declaration,
            should_submit: shouldSubmit,
            items: payloadItems(),
        }));

    if (isEdit.value) {
        form.put(route('requisitions.update', props.requisition.id), options);
    } else {
        form.post(route('requisitions.store'), options);
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Edit requisition' : 'New requisition'" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-bedita-navy">
                {{ isEdit ? 'Edit requisition' : 'New requisition' }}
            </h2>
        </template>

        <div class="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8">
            <form class="space-y-6" @submit.prevent="save(true)">
                <div class="space-y-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="block text-sm font-medium text-bedita-navy">Date of request</label>
                            <input
                                v-model="form.requested_on"
                                type="date"
                                :min="minDate"
                                class="mt-1 block w-full rounded-md border-gray-300 text-base shadow-sm focus:border-bedita-red focus:ring-bedita-red"
                            />
                            <InputError :message="form.errors.requested_on" class="mt-1" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-bedita-navy">Requester</label>
                            <input
                                type="text"
                                :value="requester.name"
                                readonly
                                class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 text-base shadow-sm"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-bedita-navy">Department</label>
                            <input
                                type="text"
                                :value="requester.department || '—'"
                                readonly
                                class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 text-base shadow-sm"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-bedita-navy">Request type</label>
                        <select
                            v-model="form.request_type"
                            class="mt-1 block w-full rounded-md border-gray-300 text-base shadow-sm focus:border-bedita-red focus:ring-bedita-red"
                        >
                            <option
                                v-for="type in requestTypes"
                                :key="type.value"
                                :value="type.value"
                            >
                                {{ type.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.request_type" class="mt-1" />
                    </div>

                    <div v-if="form.request_type === 'other'">
                        <label class="block text-sm font-medium text-bedita-navy">Please specify</label>
                        <input
                            v-model="form.request_type_other"
                            type="text"
                            class="mt-1 block w-full rounded-md border-gray-300 text-base shadow-sm focus:border-bedita-red focus:ring-bedita-red"
                        />
                        <InputError :message="form.errors.request_type_other" class="mt-1" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-bedita-navy">Purpose of request</label>
                        <textarea
                            v-model="form.purpose"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 text-base shadow-sm focus:border-bedita-red focus:ring-bedita-red"
                        />
                        <InputError :message="form.errors.purpose" class="mt-1" />
                    </div>
                </div>

                <div class="space-y-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-semibold text-bedita-navy">Products requested</h3>
                        <button
                            type="button"
                            class="rounded-md bg-bedita-navy px-3 py-2 text-sm font-semibold text-white hover:bg-bedita-navy-dark"
                            @click="addRow"
                        >
                            + Add row
                        </button>
                    </div>

                    <InputError :message="form.errors.items" />

                    <!-- Header (desktop) -->
                    <div class="hidden grid-cols-12 gap-3 px-1 text-xs font-semibold uppercase tracking-wide text-gray-500 sm:grid">
                        <div class="col-span-8">Product</div>
                        <div class="col-span-2">Qty</div>
                        <div class="col-span-2 text-right"> </div>
                    </div>

                    <div
                        v-for="(item, index) in form.items"
                        :key="index"
                        class="grid grid-cols-1 gap-3 rounded-lg border border-gray-100 bg-gray-50/80 p-3 sm:grid-cols-12 sm:items-start"
                    >
                        <div class="sm:col-span-8">
                            <ProductSearch
                                compact
                                :model-value="item.product"
                                @update:model-value="(p) => setProduct(index, p)"
                            />
                            <p v-if="item.product" class="mt-1 text-xs text-gray-500">
                                {{ item.product.sku }} · {{ item.product.uom }}
                            </p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-500 sm:sr-only">Qty</label>
                            <input
                                v-model.number="item.qty"
                                type="number"
                                min="1"
                                class="mt-1 block w-full rounded-md border-gray-300 text-base shadow-sm focus:border-bedita-red focus:ring-bedita-red sm:mt-0"
                            />
                        </div>
                        <div class="flex sm:col-span-2 sm:justify-end">
                            <button
                                type="button"
                                class="text-sm font-medium text-bedita-red hover:underline"
                                @click="removeRow(index)"
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
                    <label class="flex items-start gap-3">
                        <input
                            v-model="form.declaration"
                            type="checkbox"
                            class="mt-1 rounded border-gray-300 text-bedita-red focus:ring-bedita-red"
                        />
                        <span class="text-sm text-gray-700">{{ declarationText }}</span>
                    </label>
                    <InputError :message="form.errors.declaration" class="mt-2" />
                </div>

                <div
                    v-if="Object.keys(form.errors).length"
                    class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                >
                    Please fix the highlighted fields and try again.
                </div>

                <div class="flex flex-col gap-3 pb-8 sm:flex-row">
                    <button
                        type="button"
                        class="w-full rounded-md border border-gray-300 bg-white px-4 py-3 text-sm font-semibold text-bedita-navy shadow-sm hover:bg-gray-50"
                        :disabled="form.processing"
                        @click="save(false)"
                    >
                        Save draft
                    </button>
                    <button
                        type="submit"
                        class="w-full rounded-md bg-bedita-red px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-red-700 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Submitting…' : 'Submit for approval' }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
