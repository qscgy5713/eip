<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    webhooks: Array,
    supportedEvents: Object,
});

const showModal = ref(false);

const form = useForm({
    name: '',
    url: '',
    events: ['announcement.published', 'form.submitted'],
    secret: '',
    is_active: true,
});

const openModal = () => {
    form.reset();
    form.events = ['announcement.published', 'form.submitted'];
    showModal.value = true;
};

const submitWebhook = () => {
    form.post(route('webhooks.store'), {
        onSuccess: () => {
            showModal.value = false;
        },
    });
};

const pingWebhook = (id) => {
    router.post(route('webhooks.ping', id));
};

const toggleWebhook = (id) => {
    router.patch(route('webhooks.toggle', id));
};

const deleteWebhook = (id, name) => {
    if (confirm(`確定要刪除外部整合 Webhook「${name}」嗎？`)) {
        router.delete(route('webhooks.destroy', id));
    }
};
</script>

<template>
    <Head title="外部生態 Webhook 整合" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold leading-tight text-gray-900">
                        ⚡ 外部生態 Webhook 整合
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        支援與 Slack、Discord、Microsoft Teams 或自訂內部服務串接，即時推播企業重要公告與審核事件
                    </p>
                </div>
                <div>
                    <button
                        @click="openModal"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-semibold shadow-sm transition"
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        新增 Webhook 端點
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- Webhook 支援格式說明卡片 -->
                <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-5 flex items-start gap-4">
                    <div class="p-2 bg-indigo-600 text-white rounded-lg flex-shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div class="text-sm text-indigo-950">
                        <h4 class="font-bold text-base mb-1">原生相容主流即時通訊軟體</h4>
                        <p class="text-indigo-800 leading-relaxed">
                            EIP 的 Webhook Payload 具備自動格式配接器，原生支援 <strong>Slack Incoming Webhooks</strong> 與 <strong>Discord Webhooks</strong>（支援 <code class="bg-white/60 px-1 py-0.5 rounded font-mono text-xs">text</code> 與 <code class="bg-white/60 px-1 py-0.5 rounded font-mono text-xs">content</code> 訊息欄位），亦提供通用 JSON 與 HMAC-SHA256 數位簽章保護，可直接對接公司自建後端或自動化機器人。
                        </p>
                    </div>
                </div>

                <!-- Webhook 清單 -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div v-if="webhooks.length === 0" class="py-16 text-center text-gray-500">
                        <svg class="mx-auto h-12 w-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <div class="text-base font-semibold text-gray-700">尚未配置任何 Webhook</div>
                        <p class="text-sm text-gray-400 mt-1">點擊右上角「新增 Webhook 端點」開始整合外部通訊軟體</p>
                    </div>

                    <div v-else class="divide-y divide-gray-100">
                        <div
                            v-for="webhook in webhooks"
                            :key="webhook.id"
                            class="p-6 hover:bg-gray-50/60 transition flex flex-col md:flex-row md:items-center justify-between gap-4"
                        >
                            <div class="space-y-2 flex-1 min-w-0">
                                <div class="flex items-center gap-3">
                                    <h3 class="text-lg font-bold text-gray-900 truncate">
                                        {{ webhook.name }}
                                    </h3>
                                    <span
                                        :class="[
                                            'px-2 py-0.5 text-xs font-semibold rounded-full border',
                                            webhook.is_active
                                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                                : 'bg-gray-100 text-gray-500 border-gray-200'
                                        ]"
                                    >
                                        {{ webhook.is_active ? '啟用中' : '已停用' }}
                                    </span>
                                    <span
                                        v-if="webhook.last_status"
                                        :class="[
                                            'px-2 py-0.5 text-[11px] font-mono rounded border flex items-center gap-1',
                                            webhook.last_status === 'success'
                                                ? 'bg-green-50 text-green-700 border-green-200'
                                                : 'bg-red-50 text-red-700 border-red-200'
                                        ]"
                                    >
                                        <span :class="['w-1.5 h-1.5 rounded-full', webhook.last_status === 'success' ? 'bg-green-500' : 'bg-red-500']"></span>
                                        {{ webhook.last_status === 'success' ? '最近傳送成功' : '傳送失敗' }}
                                    </span>
                                </div>

                                <div class="text-xs font-mono text-gray-500 truncate bg-gray-50 px-2 py-1 rounded border border-gray-100 max-w-2xl">
                                    {{ webhook.url }}
                                </div>

                                <!-- 訂閱事件標籤 -->
                                <div class="flex flex-wrap items-center gap-1.5 pt-1">
                                    <span class="text-xs text-gray-400 mr-1">訂閱事件：</span>
                                    <span
                                        v-for="ev in webhook.events"
                                        :key="ev"
                                        class="px-2 py-0.5 text-xs rounded bg-gray-100 text-gray-700 border border-gray-200"
                                    >
                                        {{ supportedEvents[ev] || ev }}
                                    </span>
                                </div>
                            </div>

                            <!-- 右側操作按鈕列 -->
                            <div class="flex items-center gap-2 pt-2 md:pt-0">
                                <button
                                    @click="pingWebhook(webhook.id)"
                                    class="inline-flex items-center px-3 py-1.5 border border-indigo-200 text-indigo-700 hover:bg-indigo-50 rounded-lg text-xs font-semibold transition"
                                    title="發送一筆測試訊號檢查端點連線"
                                >
                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    測試連線 (Ping)
                                </button>

                                <button
                                    @click="toggleWebhook(webhook.id)"
                                    :class="[
                                        'px-3 py-1.5 border rounded-lg text-xs font-semibold transition',
                                        webhook.is_active
                                            ? 'border-gray-300 text-gray-700 hover:bg-gray-100'
                                            : 'border-emerald-300 text-emerald-700 hover:bg-emerald-50'
                                    ]"
                                >
                                    {{ webhook.is_active ? '停用' : '啟用' }}
                                </button>

                                <button
                                    @click="deleteWebhook(webhook.id, webhook.name)"
                                    class="px-3 py-1.5 text-red-600 hover:bg-red-50 border border-red-200 rounded-lg text-xs font-semibold transition"
                                >
                                    刪除
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 新增 Webhook 彈窗 Modal -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4"
        >
            <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full p-6 text-left">
                <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                    <h3 class="text-lg font-bold text-gray-900">
                        ⚡ 配置新外部 Webhook 端點
                    </h3>
                    <button @click="showModal = false" class="text-gray-400 hover:text-gray-600 text-xl font-bold">
                        ×
                    </button>
                </div>

                <form @submit.prevent="submitWebhook" class="mt-5 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Webhook 名稱 *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="例如：公司全體 Slack #general 頻道"
                            class="w-full text-sm rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Webhook URL 端點 *</label>
                        <input
                            v-model="form.url"
                            type="url"
                            required
                            placeholder="https://hooks.slack.com/services/... 或 Discord Webhook"
                            class="w-full text-sm rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-2">訂閱觸發事件 (至少勾選一項) *</label>
                        <div class="space-y-2 bg-gray-50 p-3 rounded-lg border border-gray-200">
                            <label
                                v-for="(label, key) in supportedEvents"
                                :key="key"
                                class="flex items-center gap-2.5 text-xs text-gray-700 cursor-pointer"
                            >
                                <input
                                    type="checkbox"
                                    :value="key"
                                    v-model="form.events"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span class="font-medium">{{ label }}</span>
                                <span class="text-gray-400 font-mono text-[11px]">({{ key }})</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">簽章密鑰 Secret (選填)</label>
                        <input
                            v-model="form.secret"
                            type="text"
                            placeholder="用於生成 X-EIP-Signature 簽章（選填）"
                            class="w-full text-sm rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 font-mono text-xs"
                        />
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <input
                            id="is_active_check"
                            type="checkbox"
                            v-model="form.is_active"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <label for="is_active_check" class="text-xs font-semibold text-gray-700 cursor-pointer">
                            建立後立即啟用
                        </label>
                    </div>

                    <div class="pt-4 border-t border-gray-100 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50"
                        >
                            取消
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing || form.events.length === 0"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-semibold shadow-sm transition disabled:opacity-50"
                        >
                            確認儲存 Webhook
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
