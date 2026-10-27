<script setup>
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { router, useForm } from '@inertiajs/vue3';

defineProps({
    delegations: { type: Array, default: () => [] },
    delegateCandidates: { type: Array, default: () => [] },
});

const form = useForm({
    delegate_id: '',
    starts_at: '',
    ends_at: '',
});

const submit = () => {
    form.post(route('delegations.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('delegate_id', 'starts_at', 'ends_at'),
    });
};

const revoke = (id) => {
    if (!confirm('Revoke this delegation?')) return;
    router.delete(route('delegations.destroy', id), { preserveScroll: true });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-semibold text-bedita-navy">Approval delegation</h2>
            <p class="mt-1 text-sm text-gray-600">
                Temporarily let another person with your role approve on your behalf.
            </p>
        </header>

        <form class="mt-4 space-y-3" @submit.prevent="submit">
            <div>
                <label class="block text-sm font-medium text-bedita-navy">Delegate to</label>
                <select
                    v-model="form.delegate_id"
                    required
                    class="mt-1 block w-full rounded-md border-gray-300 text-base shadow-sm focus:border-bedita-red focus:ring-bedita-red"
                >
                    <option value="" disabled>Select colleague</option>
                    <option
                        v-for="candidate in delegateCandidates"
                        :key="candidate.id"
                        :value="candidate.id"
                    >
                        {{ candidate.name }} ({{ candidate.email }})
                    </option>
                </select>
                <InputError :message="form.errors.delegate_id" class="mt-1" />
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-bedita-navy">Starts</label>
                    <input
                        v-model="form.starts_at"
                        type="date"
                        class="mt-1 block w-full rounded-md border-gray-300 text-base shadow-sm"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-bedita-navy">Ends</label>
                    <input
                        v-model="form.ends_at"
                        type="date"
                        class="mt-1 block w-full rounded-md border-gray-300 text-base shadow-sm"
                    />
                </div>
            </div>
            <PrimaryButton :disabled="form.processing || !form.delegate_id">
                Create delegation
            </PrimaryButton>
        </form>

        <ul v-if="delegations.length" class="mt-6 divide-y divide-gray-100 rounded-xl border border-gray-100">
            <li
                v-for="item in delegations"
                :key="item.id"
                class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="text-sm">
                    <p class="font-medium text-gray-900">
                        <template v-if="item.is_mine">
                            You → {{ item.delegate?.name }}
                        </template>
                        <template v-else>
                            {{ item.delegator?.name }} → You
                        </template>
                    </p>
                    <p class="text-gray-500">
                        {{ item.starts_at || 'Immediate' }}
                        →
                        {{ item.ends_at || 'No end date' }}
                        ·
                        <span :class="item.active ? 'text-green-700' : 'text-gray-400'">
                            {{ item.active ? 'Active' : 'Inactive' }}
                        </span>
                    </p>
                </div>
                <button
                    v-if="item.active"
                    type="button"
                    class="text-sm font-medium text-bedita-red"
                    @click="revoke(item.id)"
                >
                    Revoke
                </button>
            </li>
        </ul>
        <p v-else class="mt-4 text-sm text-gray-500">No delegations yet.</p>
    </section>
</template>
