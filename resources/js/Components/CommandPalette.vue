<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

const isOpen = ref(false);
const searchQuery = ref('');
const searchResults = ref({ shortcuts: [] });
const isLoading = ref(false);
const selectedIndex = ref(0);
const searchInput = ref(null);

let debounceTimer = null;

// 將所有搜尋結果扁平化為單一陣列以利鍵盤上下導航
const flatItems = computed(() => {
    const list = [];
    if (!searchResults.value) return list;

    // 常用捷徑
    if (searchResults.value.shortcuts && searchResults.value.shortcuts.length > 0) {
        searchResults.value.shortcuts.forEach(item => {
            list.push({ ...item, group: '快捷導航' });
        });
    }

    // 各分組搜尋結果
    const groupKeys = ['employees', 'forms', 'announcements', 'meeting_rooms', 'documents'];
    for (const key of groupKeys) {
        const group = searchResults.value[key];
        if (group && group.items && group.items.length > 0) {
            group.items.forEach(item => {
                list.push({ ...item, group: group.title });
            });
        }
    }

    return list;
});

const open = () => {
    isOpen.value = true;
    selectedIndex.value = 0;
    fetchResults();
    nextTick(() => {
        searchInput.value?.focus();
    });
};

const close = () => {
    isOpen.value = false;
    searchQuery.value = '';
};

const fetchResults = async () => {
    isLoading.value = true;
    try {
        const res = await axios.get(route('global-search'), {
            params: { q: searchQuery.value }
        });
        searchResults.value = res.data.results || {};
        selectedIndex.value = 0;
    } catch (e) {
        console.error('全站搜尋失敗', e);
    } finally {
        isLoading.value = false;
    }
};

const handleInput = () => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        fetchResults();
    }, 200);
};

const selectItem = (item) => {
    if (!item || !item.url) return;
    close();
    router.visit(item.url);
};

const handleKeyDown = (e) => {
    // 全域快速鍵 Cmd+K / Ctrl+K
    if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) {
        e.preventDefault();
        if (isOpen.value) {
            close();
        } else {
            open();
        }
        return;
    }

    if (!isOpen.value) return;

    if (e.key === 'Escape') {
        e.preventDefault();
        close();
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (flatItems.value.length === 0) return;
        selectedIndex.value = (selectedIndex.value + 1) % flatItems.value.length;
        scrollSelectedIntoView();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (flatItems.value.length === 0) return;
        selectedIndex.value = (selectedIndex.value - 1 + flatItems.value.length) % flatItems.value.length;
        scrollSelectedIntoView();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (flatItems.value.length > 0 && flatItems.value[selectedIndex.value]) {
            selectItem(flatItems.value[selectedIndex.value]);
        }
    }
};

const scrollSelectedIntoView = () => {
    nextTick(() => {
        const el = document.getElementById(`cmd-palette-item-${selectedIndex.value}`);
        if (el) {
            el.scrollIntoView({ block: 'nearest' });
        }
    });
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
    if (debounceTimer) clearTimeout(debounceTimer);
});

defineExpose({
    open,
    close,
});
</script>

<template>
    <!-- 全站指揮中心對話框 -->
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-xs flex items-start justify-center p-4 sm:p-6 md:p-20"
        @click.self="close"
    >
        <div
            class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl border border-gray-100 overflow-hidden transform transition-all flex flex-col max-h-[85vh] animate-in fade-in zoom-in-95 duration-150"
        >
            <!-- 搜尋輸入列 -->
            <div class="relative flex items-center px-4 border-b border-gray-100 bg-gray-50/50">
                <svg
                    class="w-5 h-5 text-gray-400 shrink-0 ml-1 mr-3"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                    />
                </svg>

                <input
                    ref="searchInput"
                    v-model="searchQuery"
                    type="text"
                    placeholder="搜尋同仁、表單單號、公告、會議室或文件... (支援上下鍵導航)"
                    class="w-full py-4 text-sm bg-transparent border-0 focus:ring-0 focus:outline-none text-gray-900 placeholder-gray-400"
                    @input="handleInput"
                />

                <!-- 載入中 Spinner -->
                <div v-if="isLoading" class="shrink-0 mr-2">
                    <svg class="animate-spin h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>

                <!-- 關閉快速鍵標籤 -->
                <button
                    type="button"
                    @click="close"
                    class="px-2 py-1 text-2xs font-semibold text-gray-500 bg-gray-100 hover:bg-gray-200 border border-gray-200 rounded-md transition"
                >
                    ESC
                </button>
            </div>

            <!-- 搜尋結果展示區 -->
            <div class="overflow-y-auto p-3 space-y-4 max-h-[60vh]">
                <!-- 空搜尋結果反饋 -->
                <div
                    v-if="!isLoading && searchQuery.trim() !== '' && flatItems.length === 0"
                    class="py-12 text-center"
                >
                    <div class="inline-flex p-3 bg-gray-100 rounded-full text-gray-400 mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-gray-700">找不到符合「{{ searchQuery }}」的項目</p>
                    <p class="text-xs text-gray-400 mt-1">請嘗試縮短關鍵字、搜尋其他同仁姓名或單號。</p>
                </div>

                <!-- 扁平列表渲染 -->
                <div v-if="flatItems.length > 0" class="space-y-1">
                    <template v-for="(item, idx) in flatItems" :key="item.id || idx">
                        <!-- 當分組改變時，顯示分組標題 -->
                        <div
                            v-if="idx === 0 || item.group !== flatItems[idx - 1].group"
                            class="px-3 pt-3 pb-1 text-2xs font-bold uppercase tracking-wider text-gray-400 flex items-center justify-between"
                        >
                            <span>{{ item.group }}</span>
                        </div>

                        <!-- 項目按鈕 -->
                        <div
                            :id="`cmd-palette-item-${idx}`"
                            @click="selectItem(item)"
                            @mouseenter="selectedIndex = idx"
                            :class="[
                                'flex items-center justify-between p-3 rounded-xl cursor-pointer transition select-none group',
                                selectedIndex === idx
                                    ? 'bg-indigo-50 border border-indigo-200 text-indigo-900 shadow-xs'
                                    : 'hover:bg-gray-50 border border-transparent text-gray-800'
                            ]"
                        >
                            <div class="flex items-center space-x-3 min-w-0 mr-3">
                                <!-- 項目圖示 -->
                                <div
                                    :class="[
                                        'p-2 rounded-lg shrink-0 transition',
                                        item.type === 'shortcut' ? 'bg-indigo-100 text-indigo-600' :
                                        item.type === 'employee' ? 'bg-blue-100 text-blue-600' :
                                        item.type === 'form' ? 'bg-amber-100 text-amber-700' :
                                        item.type === 'announcement' ? 'bg-rose-100 text-rose-600' :
                                        item.type === 'meeting_room' ? 'bg-emerald-100 text-emerald-600' :
                                        'bg-purple-100 text-purple-600'
                                    ]"
                                >
                                    <!-- 捷徑圖示 -->
                                    <svg v-if="item.type === 'shortcut'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    <!-- 員工圖示 -->
                                    <svg v-else-if="item.type === 'employee'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    <!-- 表單圖示 -->
                                    <svg v-else-if="item.type === 'form'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h2.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <!-- 公告圖示 -->
                                    <svg v-else-if="item.type === 'announcement'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                                    <!-- 會議室圖示 -->
                                    <svg v-else-if="item.type === 'meeting_room'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    <!-- 文件圖示 -->
                                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                </div>

                                <div class="truncate">
                                    <p class="text-xs font-bold truncate">{{ item.title }}</p>
                                    <p class="text-2xs text-gray-500 truncate mt-0.5">{{ item.subtitle }}</p>
                                </div>
                            </div>

                            <div class="flex items-center space-x-2 shrink-0">
                                <span
                                    v-if="item.badge"
                                    class="text-2xs font-semibold px-2 py-0.5 rounded-md bg-gray-100 text-gray-600"
                                >
                                    {{ item.badge }}
                                </span>
                                <svg
                                    :class="[
                                        'w-4 h-4 transition',
                                        selectedIndex === idx ? 'text-indigo-600 translate-x-0.5' : 'text-gray-300'
                                    ]"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- 底部鍵盤操作指引列 -->
            <div class="px-4 py-2.5 bg-gray-50 border-t border-gray-100 flex items-center justify-between text-2xs text-gray-400">
                <div class="flex items-center space-x-4">
                    <span class="flex items-center space-x-1">
                        <kbd class="px-1.5 py-0.5 bg-white border border-gray-200 rounded font-mono text-gray-600">↑</kbd>
                        <kbd class="px-1.5 py-0.5 bg-white border border-gray-200 rounded font-mono text-gray-600">↓</kbd>
                        <span>選擇</span>
                    </span>
                    <span class="flex items-center space-x-1">
                        <kbd class="px-1.5 py-0.5 bg-white border border-gray-200 rounded font-mono text-gray-600">↵</kbd>
                        <span>跳轉</span>
                    </span>
                    <span class="flex items-center space-x-1">
                        <kbd class="px-1.5 py-0.5 bg-white border border-gray-200 rounded font-mono text-gray-600">esc</kbd>
                        <span>關閉</span>
                    </span>
                </div>
                <div class="text-gray-400">
                    EIP 全站快捷指揮中心
                </div>
            </div>
        </div>
    </div>
</template>
