<script setup>
defineProps({
    title: { type: String, required: true },
    name: { type: String, default: null },
    url: { type: String, default: null },
    signedAt: { type: String, default: null },
});
</script>

<template>
    <div class="rounded-xl border border-gray-100 bg-slate-50/80 p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ title }}</p>
        <div class="mt-3 flex min-h-16 items-end">
            <img
                v-if="url"
                :src="url"
                :alt="title"
                class="max-h-16 max-w-full object-contain"
            />
            <p v-else class="text-sm italic text-gray-400">Awaiting signature</p>
        </div>
        <div class="mt-3 border-t border-gray-200 pt-2">
            <p class="text-sm font-medium text-bedita-navy">{{ name || '—' }}</p>
            <p v-if="signedAt" class="text-xs text-gray-500">{{ signedAt }}</p>
        </div>
    </div>
</template>
