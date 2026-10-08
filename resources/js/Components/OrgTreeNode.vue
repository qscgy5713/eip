<script setup>
import { computed } from 'vue';

const props = defineProps({
    node: {
        type: Object,
        required: true,
    },
    hasParent: {
        type: Boolean,
        default: false,
    },
    isFirst: {
        type: Boolean,
        default: false,
    },
    isLast: {
        type: Boolean,
        default: false,
    },
    isSingleChild: {
        type: Boolean,
        default: false,
    },
    collapsedIds: {
        type: Object,
        required: true,
    },
    draggingDeptId: {
        type: [Number, String, null],
        default: null,
    },
    dropTargetId: {
        type: [Number, String, null],
        default: null,
    },
    disabledDropIds: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits([
    'create-child',
    'edit',
    'delete',
    'view-members',
    'toggle-collapse',
    'drag-start',
    'drag-over',
    'drag-leave',
    'drop',
]);

const isCollapsed = computed(() => {
    return props.collapsedIds.has(props.node.id);
});

const hasChildren = computed(() => {
    return props.node.childrenNodes && props.node.childrenNodes.length > 0;
});

const isBeingDragged = computed(() => {
    return props.draggingDeptId === props.node.id;
});

const isCurrentDropTarget = computed(() => {
    return props.dropTargetId === props.node.id;
});

const isDropDisabled = computed(() => {
    return props.disabledDropIds.includes(props.node.id);
});

// 拖曳事件處理
const handleDragStart = (e) => {
    emit('drag-start', props.node, e);
};

const handleDragOver = (e) => {
    if (isDropDisabled.value) return;
    emit('drag-over', props.node, e);
};

const handleDragLeave = (e) => {
    emit('drag-leave', props.node, e);
};

const handleDrop = (e) => {
    if (isDropDisabled.value) return;
    emit('drop', props.node, e);
};
</script>

<template>
    <div class="flex flex-col items-center relative">
        <!-- 頂部與水平匯流排連接線 (非頂層節點時顯示) -->
        <div v-if="hasParent" class="w-full flex justify-center relative h-6">
            <!-- 橫向分支線 (若為多個子節點中之一) -->
            <div
                v-if="!isSingleChild"
                :class="[
                    'absolute top-0 h-0.5 bg-slate-300',
                    isFirst ? 'left-1/2 w-1/2' : (isLast ? 'right-1/2 w-1/2' : 'left-0 w-full')
                ]"
            ></div>
            <!-- 垂直連線連至卡片頂部 -->
            <div class="w-0.5 h-6 bg-slate-300"></div>
        </div>

        <!-- 部門節點卡片 -->
        <div
            :draggable="true"
            @dragstart="handleDragStart"
            @dragover.prevent="handleDragOver"
            @dragleave="handleDragLeave"
            @drop.prevent="handleDrop"
            :class="[
                'w-64 rounded-xl border transition-all duration-200 select-none relative z-10 group bg-white shadow-sm',
                isBeingDragged ? 'opacity-40 border-dashed border-indigo-400 scale-95 shadow-none' : '',
                isCurrentDropTarget
                    ? 'ring-4 ring-indigo-500 ring-offset-2 border-indigo-500 bg-indigo-50/70 scale-105 shadow-xl'
                    : 'border-slate-200 hover:border-indigo-400 hover:shadow-md',
                !node.is_active ? 'opacity-75 bg-slate-50' : '',
                draggingDeptId && isDropDisabled && !isBeingDragged ? 'opacity-50 cursor-not-allowed' : 'cursor-grab active:cursor-grabbing'
            ]"
        >
            <!-- 拖曳高亮懸浮提示 -->
            <div
                v-if="isCurrentDropTarget"
                class="absolute -top-3 left-1/2 -translate-x-1/2 px-2.5 py-0.5 bg-indigo-600 text-white text-3xs font-black rounded-full shadow-md uppercase tracking-wider whitespace-nowrap z-20"
            >
                移至此部門下
            </div>

            <!-- 卡片頂部條：代碼與人數 -->
            <div class="px-3.5 py-2.5 bg-slate-50 border-b border-slate-100 rounded-t-xl flex items-center justify-between">
                <div class="flex items-center gap-1.5 min-w-0">
                    <span class="px-2 py-0.5 text-2xs font-bold font-mono rounded bg-indigo-100 text-indigo-700 shrink-0">
                        {{ node.code }}
                    </span>
                    <span v-if="!node.is_active" class="px-1.5 py-0.5 text-3xs font-semibold rounded bg-amber-100 text-amber-700 shrink-0">
                        停用
                    </span>
                </div>
                <button
                    @click.stop="emit('view-members', node)"
                    class="inline-flex items-center gap-1 text-2xs font-semibold text-slate-500 hover:text-indigo-600 transition shrink-0"
                    title="點擊查看在職成員名冊"
                >
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    <span>{{ node.users_count || (node.users?.length || 0) }} 人</span>
                </button>
            </div>

            <!-- 卡片主體：名稱與主管 -->
            <div class="p-3.5 space-y-2.5">
                <div class="flex items-start justify-between gap-1">
                    <h4 class="font-bold text-slate-900 text-sm truncate flex-1" :title="node.name">
                        {{ node.name }}
                    </h4>
                    <!-- 拖曳握把小圖示 -->
                    <span class="text-slate-300 group-hover:text-slate-400 shrink-0" title="可拖曳此部門調整上級隸屬">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg>
                    </span>
                </div>

                <!-- 負責主管資訊卡 -->
                <div class="flex items-center gap-2 p-2 rounded-lg bg-slate-50/80 border border-slate-100">
                    <div class="w-7 h-7 rounded-full bg-indigo-500 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-2xs">
                        {{ node.leader?.name ? node.leader.name.charAt(0) : '無' }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold text-slate-800 truncate">
                            {{ node.leader?.name || '未指定主管' }}
                        </p>
                        <p class="text-3xs text-slate-400 truncate">
                            {{ node.leader?.job_title || '部門管理者' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- 卡片操作按鈕列 (快捷工具列) -->
            <div class="px-2.5 py-2 bg-slate-50/50 border-t border-slate-100 rounded-b-xl flex items-center justify-between text-xs">
                <button
                    @click.stop="emit('create-child', node)"
                    class="inline-flex items-center gap-1 px-2 py-1 text-2xs font-semibold text-indigo-600 hover:text-indigo-700 hover:bg-indigo-50 rounded transition"
                    title="在此部門下建立子部門"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    子部門
                </button>

                <div class="flex items-center gap-0.5">
                    <button
                        @click.stop="emit('view-members', node)"
                        class="p-1 text-slate-500 hover:text-indigo-600 hover:bg-slate-100 rounded transition"
                        title="查看部門成員"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </button>
                    <button
                        @click.stop="emit('edit', node)"
                        class="p-1 text-slate-500 hover:text-indigo-600 hover:bg-slate-100 rounded transition"
                        title="編輯部門資訊"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    <button
                        @click.stop="emit('delete', node)"
                        class="p-1 text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded transition"
                        title="刪除部門"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>
            </div>

            <!-- 折疊 / 展開圓形切換按鈕 (有子部門時顯示) -->
            <button
                v-if="hasChildren"
                @click.stop="emit('toggle-collapse', node.id)"
                :class="[
                    'absolute -bottom-3 left-1/2 -translate-x-1/2 w-6 h-6 rounded-full border flex items-center justify-center text-3xs font-extrabold shadow-sm z-20 transition-transform',
                    isCollapsed
                        ? 'bg-indigo-600 text-white border-indigo-700 hover:bg-indigo-700 scale-105'
                        : 'bg-white text-slate-600 border-slate-300 hover:border-indigo-400 hover:text-indigo-600'
                ]"
                :title="isCollapsed ? `展開 ${node.childrenNodes.length} 個子部門` : '收合子部門'"
            >
                <span v-if="isCollapsed">+{{ node.childrenNodes.length }}</span>
                <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
        </div>

        <!-- 子部門分支區域 (若未折疊且有子節點) -->
        <div v-if="hasChildren && !isCollapsed" class="flex flex-col items-center">
            <!-- 從父卡片向下延伸的主幹引線 -->
            <div class="w-0.5 h-6 bg-slate-300"></div>

            <!-- 子部門橫向並排容器 -->
            <div class="flex items-start justify-center gap-6 pt-0">
                <OrgTreeNode
                    v-for="(child, index) in node.childrenNodes"
                    :key="child.id"
                    :node="child"
                    :has-parent="true"
                    :is-first="index === 0"
                    :is-last="index === node.childrenNodes.length - 1"
                    :is-single-child="node.childrenNodes.length === 1"
                    :collapsed-ids="collapsedIds"
                    :dragging-dept-id="draggingDeptId"
                    :drop-target-id="dropTargetId"
                    :disabled-drop-ids="disabledDropIds"
                    @create-child="$emit('create-child', $event)"
                    @edit="$emit('edit', $event)"
                    @delete="$emit('delete', $event)"
                    @view-members="$emit('view-members', $event)"
                    @toggle-collapse="$emit('toggle-collapse', $event)"
                    @drag-start="(dept, ev) => $emit('drag-start', dept, ev)"
                    @drag-over="(dept, ev) => $emit('drag-over', dept, ev)"
                    @drag-leave="(dept, ev) => $emit('drag-leave', dept, ev)"
                    @drop="(dept, ev) => $emit('drop', dept, ev)"
                />
            </div>
        </div>
    </div>
</template>
