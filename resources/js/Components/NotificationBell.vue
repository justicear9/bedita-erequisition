<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    isNotificationSoundUnlocked,
    playNotificationSound,
    unlockNotificationSound,
} from '@/notificationSound';
import { enablePushNotifications } from '@/pushNotifications';

const props = defineProps({
    unreadCount: { type: Number, default: 0 },
    vapidPublicKey: { type: String, default: null },
});

const open = ref(false);
const items = ref([]);
const unread = ref(props.unreadCount);
const pushStatus = ref('');
const soundReady = ref(false);
let pollTimer = null;
let knownIds = new Set();
let primed = false;

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
        unread.value = response.data.unread_count || 0;
        items.value = list;

        const incoming = list.filter((n) => !knownIds.has(n.id));
        if (!primed) {
            list.forEach((n) => knownIds.add(n.id));
            primed = true;
            return;
        }

        if (incoming.length) {
            incoming.forEach((n) => knownIds.add(n.id));
            if (playSound) {
                playNotificationSound();
            }
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

const enableSound = async () => {
    const ok = await unlockNotificationSound();
    soundReady.value = ok || isNotificationSoundUnlocked();
    if (ok) {
        playNotificationSound();
        pushStatus.value = 'Sound enabled';
    } else {
        pushStatus.value = 'Could not enable sound in this browser';
    }
};

const enablePush = async () => {
    pushStatus.value = 'Enabling…';
    await unlockNotificationSound();
    soundReady.value = true;
    try {
        await enablePushNotifications(props.vapidPublicKey);
        pushStatus.value = 'Push enabled';
        playNotificationSound();
    } catch (e) {
        pushStatus.value = e?.message || 'Could not enable push';
    }
};

const toggleOpen = async () => {
    open.value = !open.value;
    if (open.value) {
        await unlockNotificationSound();
        soundReady.value = isNotificationSoundUnlocked();
        fetchNotifications();
    }
};

onMounted(() => {
    soundReady.value = isNotificationSoundUnlocked();
    fetchNotifications();
    // Faster poll so in-app alerts feel responsive on live.
    pollTimer = window.setInterval(() => fetchNotifications({ playSound: true }), 10000);

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
            @click="toggleOpen"
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
                    class="w-full rounded-md bg-bedita-red px-3 py-2.5 text-xs font-semibold text-white hover:bg-red-700"
                    @click="enablePush"
                >
                    Enable browser notifications
                </button>
                <button
                    v-if="!soundReady"
                    type="button"
                    class="w-full rounded-md bg-bedita-navy px-3 py-2 text-xs font-semibold text-white hover:bg-bedita-navy-dark"
                    @click="enableSound"
                >
                    Enable notification sounds
                </button>
                <p v-if="!vapidPublicKey" class="rounded-md bg-amber-50 px-2 py-2 text-center text-[11px] text-amber-800">
                    Browser push is off until VAPID keys are set in server .env.
                    In-app sound still works after enabling above.
                </p>
                <p v-if="pushStatus" class="text-center text-xs text-gray-600">{{ pushStatus }}</p>
                <button
                    type="button"
                    class="block w-full text-center text-xs font-medium text-gray-500"
                    @click="open = false"
                >
                    Close
                </button>
            </div>
        </div>
    </div>
</template>
