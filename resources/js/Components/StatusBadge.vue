<script setup>
const props = defineProps({
    status: { type: String, required: true },
    label: { type: String, required: true },
});

const colors = {
    draft: 'bg-gray-100 text-gray-800',
    pending_manager: 'bg-amber-100 text-amber-900',
    changes_requested: 'bg-orange-100 text-orange-900',
    pending_inventory: 'bg-amber-100 text-amber-900',
    ready_for_warehouse: 'bg-blue-100 text-blue-900',
    pending_inventory_change_review: 'bg-amber-100 text-amber-900',
    rejected: 'bg-red-100 text-red-900',
    released: 'bg-green-100 text-green-900',
};
</script>

<template>
    <span
        class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-semibold"
        :class="colors[status] || 'bg-gray-100 text-gray-800'"
    >
        {{ label }}
    </span>
</template>
