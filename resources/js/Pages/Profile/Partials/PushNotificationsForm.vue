<script setup>
import { computed, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    push: { type: Object, required: true },
});

const supported = ref(false);
const permission = ref('default');
const thisEndpoint = ref(null);
const busy = ref(false);
const message = ref('');
const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
const isStandalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;

const available = computed(() => !!props.push.publicKey);
const enabledHere = computed(() => !!thisEndpoint.value && props.push.devices.some((d) => d.endpoint === thisEndpoint.value));

// 瀏覽器要的是 Uint8Array 格式的公鑰
const urlBase64ToUint8Array = (b64) => {
    const pad = '='.repeat((4 - (b64.length % 4)) % 4);
    const raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
};

const refresh = async () => {
    supported.value = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    if (!supported.value) return;
    permission.value = Notification.permission;
    const reg = await navigator.serviceWorker.ready;
    const sub = await reg.pushManager.getSubscription();
    thisEndpoint.value = sub ? sub.endpoint : null;
};

onMounted(() => refresh().catch(() => {}));

const enable = async () => {
    busy.value = true;
    message.value = '';
    try {
        const result = await Notification.requestPermission();
        permission.value = result;
        if (result !== 'granted') {
            message.value = '您沒有允許通知。請到瀏覽器或手機的網站設定中開啟通知權限後再試。';
            return;
        }
        const reg = await navigator.serviceWorker.ready;
        const sub = (await reg.pushManager.getSubscription())
            || (await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(props.push.publicKey) }));
        await window.axios.post(route('push.subscribe'), sub.toJSON());
        await refresh();
        router.reload({ only: ['push'] });
        message.value = '已在這台裝置開啟推播。可以按「傳送測試推播」確認。';
    } catch (e) {
        message.value = '開啟推播失敗:' + (e.response?.data?.message || e.message);
    } finally {
        busy.value = false;
    }
};

const disable = async () => {
    busy.value = true;
    message.value = '';
    try {
        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.getSubscription();
        if (sub) {
            await window.axios.post(route('push.unsubscribe'), { endpoint: sub.endpoint });
            await sub.unsubscribe();
        }
        await refresh();
        router.reload({ only: ['push'] });
        message.value = '已關閉這台裝置的推播。';
    } catch (e) {
        message.value = '關閉失敗:' + e.message;
    } finally {
        busy.value = false;
    }
};

const sendTest = async () => {
    busy.value = true;
    message.value = '';
    try {
        await window.axios.post(route('push.test'));
        message.value = '測試推播已送出,幾秒內應該會跳出通知。';
    } catch (e) {
        message.value = '送出失敗,請先確認已在這台裝置開啟推播。';
    } finally {
        busy.value = false;
    }
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">手機推播通知</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                有待簽核的單據、公告等通知時,直接在這台裝置跳出提醒,不用打開網頁。推播只顯示標題與一行摘要。
            </p>
        </header>

        <p v-if="!available" class="mt-3 text-sm text-amber-700 dark:text-amber-300">系統管理員尚未設定推播金鑰,目前無法使用手機推播。</p>
        <template v-else>
            <p v-if="!supported && isIos && !isStandalone" class="mt-3 text-sm text-amber-700 dark:text-amber-300">
                iPhone 需要先把本站「加入主畫面」(Safari 分享 → 加入主畫面),再從主畫面的圖示開啟,才能開啟推播。(需要 iOS 16.4 以上)
            </p>
            <p v-else-if="!supported" class="mt-3 text-sm text-amber-700 dark:text-amber-300">這個瀏覽器不支援推播,請改用 Chrome、Edge、Safari(加入主畫面後)或 Firefox。</p>
            <p v-else-if="permission === 'denied'" class="mt-3 text-sm text-amber-700 dark:text-amber-300">這個網站的通知權限被封鎖了,請到瀏覽器或手機的網站設定中改成「允許」。</p>

            <div v-if="supported" class="mt-4 flex flex-wrap items-center gap-3">
                <button v-if="!enabledHere" type="button" :disabled="busy || permission === 'denied'" @click="enable"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50">
                    在這台裝置開啟推播
                </button>
                <template v-else>
                    <button type="button" :disabled="busy" @click="sendTest"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50">
                        傳送測試推播
                    </button>
                    <button type="button" :disabled="busy" @click="disable"
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                        關閉這台裝置的推播
                    </button>
                </template>
            </div>
            <p v-if="message" class="mt-3 text-sm text-gray-700 dark:text-gray-300" role="status">{{ message }}</p>

            <ul v-if="push.devices.length" class="mt-4 divide-y divide-gray-100 text-sm dark:divide-gray-700">
                <li v-for="d in push.devices" :key="d.id" class="flex justify-between gap-3 py-2 text-gray-700 dark:text-gray-300">
                    <span>{{ d.device }}<span v-if="d.endpoint === thisEndpoint" class="ms-2 text-xs text-indigo-600">(這台)</span></span>
                    <span class="text-xs text-gray-500">{{ d.last_used_at ? '最近送達 ' + d.last_used_at.slice(0, 10) : '尚未送達' }}</span>
                </li>
            </ul>
        </template>
    </section>
</template>
