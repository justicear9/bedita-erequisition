<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-gradient-to-b from-slate-50 to-slate-100 px-4 pt-10 sm:justify-center sm:pt-0"
    >
        <div class="flex flex-col items-center">
            <Link href="/">
                <ApplicationLogo class="h-16 w-auto sm:h-20" />
            </Link>
            <p class="mt-3 text-sm font-medium tracking-wide text-bedita-navy">
                eRequisition
            </p>
        </div>

        <div
            class="mt-6 w-full overflow-hidden rounded-xl border border-gray-200 bg-white px-6 py-6 shadow-sm sm:max-w-md"
        >
            <slot />
        </div>
    </div>
</template>
