<script setup>
import SignaturePad from '@/Components/SignaturePad.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    signatureUrl: { type: String, default: null },
    hasSignature: { type: Boolean, default: false },
});

const padData = ref('');
const fileInput = ref(null);

const form = useForm({
    signature: null,
    signature_data: '',
});

const saveDrawn = () => {
    if (!padData.value) return;
    form.signature = null;
    form.signature_data = padData.value;
    form.post(route('profile.signature.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            padData.value = '';
        },
    });
};

const onFile = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    form.signature_data = '';
    form.signature = file;
    form.post(route('profile.signature.update'), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            if (fileInput.value) fileInput.value.value = '';
        },
    });
};

const remove = () => {
    form.delete(route('profile.signature.destroy'), { preserveScroll: true });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-semibold text-bedita-navy">Your signature</h2>
            <p class="mt-1 text-sm text-gray-600">
                This signature is applied when you submit or approve a requisition.
            </p>
        </header>

        <div v-if="hasSignature && signatureUrl" class="mt-4 rounded-xl border border-gray-100 bg-slate-50 p-4">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Current</p>
            <img :src="signatureUrl" alt="Current signature" class="h-20 object-contain" />
            <button
                type="button"
                class="mt-3 text-sm font-medium text-bedita-red"
                @click="remove"
            >
                Remove signature
            </button>
        </div>

        <div class="mt-6 space-y-4">
            <div>
                <p class="mb-2 text-sm font-medium text-bedita-navy">Draw signature</p>
                <SignaturePad v-model="padData" />
                <PrimaryButton
                    class="mt-3"
                    :disabled="!padData || form.processing"
                    @click="saveDrawn"
                >
                    Save drawn signature
                </PrimaryButton>
            </div>

            <div class="border-t border-gray-100 pt-4">
                <p class="mb-2 text-sm font-medium text-bedita-navy">Or upload image</p>
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/*"
                    class="block w-full text-sm text-gray-600"
                    @change="onFile"
                />
            </div>

            <InputError :message="form.errors.signature || form.errors.signature_data" />
        </div>
    </section>
</template>
