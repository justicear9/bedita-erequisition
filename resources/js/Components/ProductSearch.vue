<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: Object, default: null },
    compact: { type: Boolean, default: false },
    excludeIds: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const query = ref('');
const results = ref([]);
const open = ref(false);
const loading = ref(false);
const selecting = ref(false);
let timer = null;

const visibleResults = () => {
    const excluded = new Set(
        (props.excludeIds || [])
            .map((id) => Number(id))
            .filter((id) => Number.isFinite(id) && id > 0),
    );
    const currentId = props.modelValue?.id ? Number(props.modelValue.id) : null;

    return results.value.filter((product) => {
        const id = Number(product.id);
        if (currentId && id === currentId) {
            return true;
        }
        return !excluded.has(id);
    });
};

const search = async () => {
    if (selecting.value) {
        return;
    }

    loading.value = true;
    try {
        const res = await window.axios.get(route('products.search'), {
            params: { q: query.value },
        });
        results.value = res.data;
        open.value = true;
    } finally {
        loading.value = false;
    }
};

watch(query, () => {
    if (selecting.value) {
        return;
    }
    clearTimeout(timer);
    timer = setTimeout(search, 250);
});

watch(
    () => props.modelValue,
    (product) => {
        if (!product) {
            query.value = '';
            return;
        }
        selecting.value = true;
        query.value = product.description;
        open.value = false;
        results.value = [];
        queueMicrotask(() => {
            selecting.value = false;
        });
    },
);

const select = (product) => {
    selecting.value = true;
    emit('update:modelValue', product);
    query.value = product.description;
    open.value = false;
    results.value = [];
    queueMicrotask(() => {
        selecting.value = false;
    });
};

const onDocClick = (e) => {
    if (!e.target.closest?.('[data-product-search]')) {
        open.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', onDocClick);
    if (props.modelValue) {
        query.value = props.modelValue.description;
    }
});

onUnmounted(() => {
    document.removeEventListener('click', onDocClick);
    clearTimeout(timer);
});
</script>

<template>
    <div class="relative" data-product-search>
        <label v-if="!compact" class="block text-sm font-medium text-bedita-navy">Product</label>
        <input
            v-model="query"
            type="search"
            autocomplete="off"
            placeholder="Search product or SKU…"
            class="mt-1 block w-full rounded-md border-gray-300 text-base shadow-sm focus:border-bedita-red focus:ring-bedita-red"
            :class="{ 'mt-0': compact }"
            @focus="search"
        />
        <p v-if="loading" class="mt-1 text-xs text-gray-500">Searching…</p>
        <ul
            v-if="open && visibleResults().length"
            class="absolute z-30 mt-1 max-h-64 w-full overflow-auto rounded-md border border-gray-200 bg-white shadow-lg"
        >
            <li
                v-for="product in visibleResults()"
                :key="product.id"
                class="cursor-pointer border-b border-gray-50 px-3 py-3 text-sm last:border-0 hover:bg-red-50 active:bg-red-50"
                @mousedown.prevent="select(product)"
            >
                <div class="font-medium text-gray-900">{{ product.description }}</div>
                <div class="text-xs text-gray-500">
                    {{ product.sku }} · {{ product.uom }}
                </div>
            </li>
        </ul>
        <p
            v-else-if="open && !loading && query.trim().length > 0"
            class="mt-1 text-xs text-gray-500"
        >
            {{ results.length && !visibleResults().length ? 'Already on this requisition.' : 'No products found.' }}
        </p>
    </div>
</template>
