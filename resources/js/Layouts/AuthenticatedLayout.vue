<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
</script>

<template>
    <div>
        <div class="min-h-screen bg-slate-50">
            <nav class="border-b border-slate-200 bg-white">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex min-w-0 items-center gap-4">
                            <Link :href="route('dashboard')" class="flex shrink-0 items-center">
                                <ApplicationLogo class="h-9 w-auto sm:h-10" />
                            </Link>

                            <div class="hidden space-x-6 sm:ms-4 sm:flex">
                                <NavLink
                                    v-if="$page.props.auth.user?.role === 'admin'"
                                    :href="route('admin.dashboard')"
                                    :active="route().current('admin.*')"
                                >
                                    Admin
                                </NavLink>
                                <NavLink
                                    v-else
                                    :href="route('dashboard')"
                                    :active="route().current('dashboard')"
                                >
                                    Dashboard
                                </NavLink>
                                <NavLink
                                    v-if="$page.props.auth.user?.role === 'requester'"
                                    :href="route('requisitions.create')"
                                    :active="route().current('requisitions.create')"
                                >
                                    New request
                                </NavLink>
                                <NavLink
                                    v-if="$page.props.auth.user?.role === 'inventory_manager'"
                                    :href="route('inventory.reports')"
                                    :active="route().current('inventory.reports*')"
                                >
                                    Reports
                                </NavLink>
                                <template v-if="$page.props.auth.user?.role === 'admin'">
                                    <NavLink
                                        :href="route('admin.users.index')"
                                        :active="route().current('admin.users.*')"
                                    >
                                        Users
                                    </NavLink>
                                    <NavLink
                                        :href="route('admin.departments.index')"
                                        :active="route().current('admin.departments.*')"
                                    >
                                        Departments
                                    </NavLink>
                                    <NavLink
                                        :href="route('admin.signatures.index')"
                                        :active="route().current('admin.signatures.*')"
                                    >
                                        Signatures
                                    </NavLink>
                                </template>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-bedita-navy transition duration-150 ease-in-out hover:text-bedita-red focus:outline-none"
                                            >
                                                {{ $page.props.auth.user.name }}
                                                <span
                                                    v-if="$page.props.auth.user.role_label"
                                                    class="ms-1 hidden text-xs text-gray-400 lg:inline"
                                                >
                                                    ({{ $page.props.auth.user.role_label }})
                                                </span>
                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')">
                                            Profile
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:outline-none"
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                            >
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex': !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex': showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            v-if="$page.props.auth.user?.role === 'admin'"
                            :href="route('admin.dashboard')"
                            :active="route().current('admin.dashboard')"
                        >
                            Admin
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-else
                            :href="route('dashboard')"
                            :active="route().current('dashboard')"
                        >
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="$page.props.auth.user?.role === 'requester'"
                            :href="route('requisitions.create')"
                            :active="route().current('requisitions.create')"
                        >
                            New request
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="$page.props.auth.user?.role === 'inventory_manager'"
                            :href="route('inventory.reports')"
                            :active="route().current('inventory.reports*')"
                        >
                            Reports
                        </ResponsiveNavLink>
                        <template v-if="$page.props.auth.user?.role === 'admin'">
                            <ResponsiveNavLink :href="route('admin.users.index')">Users</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('admin.departments.index')">Departments</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('admin.signatures.index')">Signatures</ResponsiveNavLink>
                        </template>
                    </div>

                    <div class="border-t border-gray-200 pb-1 pt-4">
                        <div class="px-4">
                            <div class="text-base font-medium text-bedita-navy">
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <header v-if="$slots.header" class="border-b border-slate-200 bg-white">
                <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main>
                <div
                    v-if="$page.props.flash?.success || $page.props.flash?.error"
                    class="mx-auto max-w-7xl space-y-2 px-4 pt-4 sm:px-6 lg:px-8"
                >
                    <div
                        v-if="$page.props.flash?.success"
                        class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
                    >
                        {{ $page.props.flash.success }}
                    </div>
                    <div
                        v-if="$page.props.flash?.error"
                        class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                    >
                        {{ $page.props.flash.error }}
                    </div>
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
