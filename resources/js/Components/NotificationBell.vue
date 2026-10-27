<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { playNotificationSound } from '@/notificationSound';
import { enablePushNotifications } from '@/pushNotifications';

const props = defineProps({
    unreadCount: { type: Number, default: 0 },
    vapidPublicKey: { type: String, default: null },
});

const open = ref(false);
const items = ref([]);
const unread = ref(props.unreadCount);
const pushStatus = ref('');
let pollTimer = null;
let lastSeenId = null;

watch(
    () => props.unreadCount,
    (value) => {
        unread.value = value;
    },
);

const fetchNotifications = async ({ playSound = false } = {}) => {
    try {
        const response = await window.axios.get(route('notifications.index'));
        const list = response.data.notifications || [];
        const previousUnread = unread.value;
        unread.value = response.data.unread_count || 0;
        items.value = list;

        const newestId = list[0]?.id ?? null;
        if (
            playSound &&
            newestId &&
            newestId !== lastSeenId &&
            unread.value > previousUnread
        ) {
            playNotificationSound();
        }
        if (newestId) {
            lastSeenId = newestId;
        }
    } catch {
        // Ignore poll failures (session expiry, etc.)
    }
};

const markRead = async (item) => {
    if (!item.read_at) {
        await window.axios.post(route('notifications.read', item.id));
        item.read_at = new Date().toISOString();
        unread.value = Math.max(0, unread.value - 1);
    }
    open.value = false;
    if (item.data?.url) {
        router.visit(item.data.url);
    }
};

const markAll = async () => {
    await window.axios.post(route('notifications.read-all'));
    items.value = items.value.map((n) => ({ ...n, read_at: n.read_at || new Date().toISOString() }));
    unread.value = 0;
};

const enablePush = async () => {
    pushStatus.value = 'Enabling…';
    try {
        await enablePushNotifications(props.vapidPublicKey);
        pushStatus.value = 'Push enabled';
    } catch (e) {
        pushStatus.value = e?.message || 'Could not enable push';
    }
};

onMounted(() => {
    fetchNotifications();
    pollTimer = window.setInterval(() => fetchNotifications({ playSound: true }), 20000);

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data?.type === 'PUSH_RECEIVED') {
                playNotificationSound();
                fetchNotifications();
            }
        });
    }
});

onUnmounted(() => {
    if (pollTimer) {
        window.clearInterval(pollTimer);
    }
});
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="relative inline-flex items-center rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-bedita-navy"
            aria-label="Notifications"
            @click="open = !open"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.8"
                    d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"
                />
            </svg>
            <span
                v-if="unread > 0"
                class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-bedita-red px-1 text-[10px] font-bold text-white"
            >
                {{ unread > 9 ? '9+' : unread }}
            </span>
        </button>

        <div
            v-if="open"
            class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg"
        >
            <div class="flex items-center justify-between border-b border-gray-100 px-3 py-2">
                <p class="text-sm font-semibold text-bedita-navy">Notifications</p>
                <button
                    v-if="unread > 0"
                    type="button"
                    class="text-xs font-medium text-bedita-red"
                    @click="markAll"
                >
                    Mark all read
                </button>
            </div>

            <div class="max-h-80 overflow-y-auto">
                <button
                    v-for="item in items"
                    :key="item.id"
                    type="button"
                    class="block w-full border-b border-gray-50 px-3 py-3 text-left hover:bg-slate-50"
                    :class="{ 'bg-sky-50/60': !item.read_at }"
                    @click="markRead(item)"
                >
                    <p class="text-sm text-gray-900">{{ item.data?.message }}</p>
                    <p class="mt-1 text-xs text-gray-400">
                        {{ item.data?.requisition_number }}
                    </p>
                </button>
                <p v-if="!items.length" class="px-3 py-6 text-center text-sm text-gray-500">
                    No notifications yet.
                </p>
            </div>

            <div class="space-y-2 border-t border-gray-100 px-3 py-3">
                <button
                    v-if="vapidPublicKey"
                    type="button"
                    class="w-full rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-bedita-navy hover:bg-gray-50"
                    @click="enablePush"
                >
                    Enable push notifications
                </button>
                <p v-if="pushStatus" class="text-center text-xs text-gray-500">{{ pushStatus }}</p>
                <Link
                    :href="route('dashboard')"
                    class="block text-center text-xs font-medium text-gray-500"
                    @click="open = false"
                >
                    Close
                </Link>
            </div>
        </div>
    </div>
</template>
