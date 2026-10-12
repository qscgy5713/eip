<script setup>
import { ref, watch, computed, onBeforeUnmount } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import CommandPalette from '@/Components/CommandPalette.vue';
import DialogHost from '@/Components/DialogHost.vue';
import BrandMark from '@/Components/BrandMark.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { Link, usePage } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);

// 手機選單展開時鎖住背景捲動,避免手指滑選單時帶動後面的頁面
watch(showingNavigationDropdown, (open) => {
    document.documentElement.style.overflow = open ? 'hidden' : '';
});
onBeforeUnmount(() => {
    document.documentElement.style.overflow = '';
});

// 全站錯誤提示:許多頁面的表單沒有逐欄顯示驗證錯誤,這裡統一以提示條呈現,避免操作失敗卻毫無回饋
const page = usePage();
const errorToast = ref([]);
let errorToastTimer = null;
const collectErrors = () => {
    const messages = Object.values(page.props.errors || {}).filter(Boolean).map(String);
    const flashError = page.props.flash?.error;
    if (flashError) messages.push(String(flashError));
    return [...new Set(messages)];
};
watch(
    () => [page.props.errors, page.props.flash?.error],
    () => {
        const messages = collectErrors();
        clearTimeout(errorToastTimer);
        errorToast.value = messages;
        if (messages.length) {
            errorToastTimer = setTimeout(() => (errorToast.value = []), 8000);
        }
    },
    { immediate: true, deep: true },
);
onBeforeUnmount(() => clearTimeout(errorToastTimer));
const hasErrorToast = computed(() => errorToast.value.length > 0);
const commandPaletteRef = ref(null);

// 桌機導覽：高頻項目固定於主列，其餘收納進「更多應用」下拉選單
const primaryNav = [
    { route: 'dashboard', label: '總覽儀表板', patterns: ['dashboard'] },
    { route: 'announcements.index', label: '企業公告', patterns: ['announcements.*'] },
    { route: 'forms.index', label: '表單簽核', patterns: ['forms.*'] },
    { route: 'approvals.index', label: '審批中心', patterns: ['approvals.*'] },
    { route: 'directory.index', label: '通訊錄', patterns: ['directory.*'] },
    { route: 'attendance.index', label: '考勤打卡', patterns: ['attendance.*'] },
    { route: 'leave-balances.index', label: '休假額度', patterns: ['leave-balances.*'] },
    { route: 'calendar.index', label: '行事曆', patterns: ['calendar.*'] },
];

const moreNav = [
    {
        group: '協作與知識',
        items: [
            { route: 'meeting-rooms.index', label: '會議室', patterns: ['meeting-rooms.*'] },
            { route: 'documents.index', label: '知識文件', patterns: ['documents.*'] },
            { route: 'polls.index', label: '同仁投票', patterns: ['polls.*'] },
            { route: 'projects.index', label: '專案看板', patterns: ['projects.*'] },
            { route: 'weekly-reports.index', label: '工作週報', patterns: ['weekly-reports.*'] },
        ],
    },
    {
        group: '行政總務',
        items: [
            { route: 'assets.index', label: '資產設備', patterns: ['assets.*'] },
            { route: 'meal-orders.index', label: '訂餐揪團', patterns: ['meal-orders.*'] },
            { route: 'tickets.index', label: '服務叫修', patterns: ['tickets.*'] },
            { route: 'visitor-reservations.index', label: '訪客接待', patterns: ['visitor-reservations.*'] },
            { route: 'vehicles.index', label: '公務派車', patterns: ['vehicles.*'] },
        ],
    },
    {
        group: '人資與福利',
        items: [
            { route: 'payslips.index', label: '薪資明細', patterns: ['payslips.*'] },
            { route: 'trainings.index', label: '企業內訓', patterns: ['trainings.*'] },
            { route: 'recognition.index', label: '感謝牆與福利', patterns: ['recognition.*', 'welfare.*'] },
        ],
    },
];

const triggerClass = (active) => [
    'inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-sm font-medium whitespace-nowrap transition-colors focus:outline-none',
    active
        ? 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-50'
        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100',
];

const isActive = (patterns) => patterns.some((p) => route().current(p));
const isMoreActive = () => moreNav.some((g) => g.items.some((i) => isActive(i.patterns)));
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-50 dark:bg-gray-950 transition-colors duration-150">
            <nav
                class="sticky top-0 z-40 border-b border-gray-200/80 dark:border-gray-800 bg-white/85 dark:bg-gray-900/85 backdrop-blur-md transition-colors duration-150"
            >
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-full px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between items-center">
                        <div class="flex items-center min-w-0">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center mr-2 lg:mr-4">
                                <Link :href="route('dashboard')" class="flex items-center gap-2" :aria-label="$page.props.branding.name">
                                    <BrandMark />
                                    <span class="hidden 2xl:block max-w-40 truncate text-base font-semibold tracking-tight text-gray-900 dark:text-gray-50">{{ $page.props.branding.name }}</span>
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div
                                class="hidden gap-0.5 xl:gap-1 sm:flex items-center flex-nowrap"
                            >
                                <NavLink
                                    v-for="item in primaryNav"
                                    :key="item.route"
                                    :href="route(item.route)"
                                    :active="isActive(item.patterns)"
                                    class="whitespace-nowrap"
                                >
                                    {{ item.label }}
                                </NavLink>

                                <!-- 更多應用 Dropdown -->
                                <div class="relative inline-flex items-center">
                                    <Dropdown align="left" width="48">
                                        <template #trigger>
                                            <button
                                                type="button"
                                                :class="triggerClass(isMoreActive())"
                                            >
                                                <span>更多應用</span>
                                                <svg class="ms-1 h-3.5 w-3.5 fill-current text-gray-400" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </template>
                                        <template #content>
                                            <template v-for="group in moreNav" :key="group.group">
                                                <div class="px-4 pt-2 pb-1 text-[11px] font-bold text-gray-400 dark:text-gray-500">
                                                    {{ group.group }}
                                                </div>
                                                <DropdownLink
                                                    v-for="item in group.items"
                                                    :key="item.route"
                                                    :href="route(item.route)"
                                                    :class="isActive(item.patterns) ? 'font-bold text-indigo-600 dark:text-indigo-400' : ''"
                                                >
                                                    {{ item.label }}
                                                </DropdownLink>
                                            </template>
                                        </template>
                                    </Dropdown>
                                </div>

                                <!-- 系統管理 Dropdown (限管理者與人資) -->
                                <div
                                    v-if="['admin', 'hr'].includes($page.props.auth.user.role)"
                                    class="relative inline-flex items-center"
                                >
                                    <Dropdown align="left" width="48">
                                        <template #trigger>
                                            <button
                                                type="button"
                                                :class="triggerClass(route().current('audit-logs.*') || route().current('webhooks.*') || route().current('attendance.settings') || route().current('org-management.*'))"
                                            >
                                                <span>系統管理</span>
                                                <svg class="ms-1 h-3.5 w-3.5 fill-current text-gray-400" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </template>
                                        <template #content>
                                            <DropdownLink :href="route('org-management.index')">
                                                組織與員工管理
                                            </DropdownLink>
                                            <DropdownLink :href="route('attendance.settings')">
                                                考勤圍欄設定
                                            </DropdownLink>
                                            <DropdownLink v-if="$page.props.auth.user.role === 'admin'" :href="route('audit-logs.index')">
                                                系統日誌 (Audit)
                                            </DropdownLink>
                                            <DropdownLink v-if="$page.props.auth.user.role === 'admin'" :href="route('webhooks.index')">
                                                整合設定 (Webhook)
                                            </DropdownLink>
                                            <DropdownLink v-if="$page.props.auth.user.role === 'admin'" :href="route('system-health.index')">
                                                系統健康
                                            </DropdownLink>
                                            <DropdownLink v-if="$page.props.auth.user.role === 'admin'" :href="route('branding.edit')">
                                                品牌設定
                                            </DropdownLink>
                                        </template>
                                    </Dropdown>
                                </div>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center gap-2">
                            <!-- 全站快捷搜尋 Command Palette 按鈕 -->
                            <button
                                type="button"
                                @click="commandPaletteRef?.open()"
                                class="inline-flex items-center gap-2 px-3 py-1.5 text-xs text-gray-400 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/60 hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg transition text-left cursor-pointer focus:outline-none"
                                title="全站快捷搜尋 (Cmd+K / Ctrl+K)"
                            >
                                <svg class="w-3.5 h-3.5 text-gray-400 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <span class="hidden md:inline font-normal text-gray-500 dark:text-gray-400">搜尋全站...</span>
                                <kbd class="hidden md:inline-block px-1.5 py-0.5 text-[10px] font-mono font-semibold text-gray-500 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded">⌘K</kbd>
                            </button>

                            <!-- 深色模式主題切換器 -->
                            <ThemeToggle />

                            <!-- Notification Bell Dropdown -->
                            <div class="relative">
                                <Dropdown align="right" width="80">
                                    <template #trigger>
                                        <button
                                            type="button"
                                            class="relative p-2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700/60 rounded-full transition focus:outline-none"
                                            title="通知中心"
                                        >
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                            </svg>
                                            <span
                                                v-if="$page.props.auth.unread_notifications_count > 0"
                                                class="absolute top-1 right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white bg-red-600 rounded-full min-w-4.5 h-4.5"
                                            >
                                                {{ $page.props.auth.unread_notifications_count > 99 ? '99+' : $page.props.auth.unread_notifications_count }}
                                            </span>
                                        </button>
                                    </template>

                                    <template #content>
                                        <div class="px-4 py-2 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300">站內通知</span>
                                            <Link
                                                :href="route('notifications.index')"
                                                class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 font-medium"
                                            >
                                                查看全部
                                            </Link>
                                        </div>

                                        <div v-if="$page.props.auth.recent_notifications && $page.props.auth.recent_notifications.length > 0" class="divide-y divide-gray-100 dark:divide-gray-700 max-h-80 overflow-y-auto">
                                            <div
                                                v-for="item in $page.props.auth.recent_notifications"
                                                :key="item.id"
                                                class="p-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition text-left"
                                            >
                                                <div class="flex items-start justify-between gap-2 mb-1">
                                                    <span class="min-w-0 text-xs font-semibold text-gray-900 dark:text-gray-100 leading-snug line-clamp-2">{{ item.title }}</span>
                                                    <span class="shrink-0 whitespace-nowrap text-[10px] text-gray-400 dark:text-gray-500">{{ item.created_at }}</span>
                                                </div>
                                                <p class="text-xs text-gray-600 dark:text-gray-300 line-clamp-2">{{ item.message }}</p>
                                                <div class="mt-2 flex items-center gap-2">
                                                    <Link
                                                        v-if="item.action_url"
                                                        :href="item.action_url"
                                                        class="text-[11px] text-indigo-600 dark:text-indigo-400 font-semibold hover:underline"
                                                    >
                                                        前往檢視
                                                    </Link>
                                                    <Link
                                                        :href="route('notifications.read', item.id)"
                                                        method="post"
                                                        as="button"
                                                        class="text-[11px] text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 ml-auto"
                                                    >
                                                        標記已讀
                                                    </Link>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="p-6 text-center text-xs text-gray-400 dark:text-gray-500">
                                            目前沒有任何未讀通知
                                        </div>

                                        <div class="p-2 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/40 text-center">
                                            <Link
                                                :href="route('notifications.index')"
                                                class="text-xs text-gray-600 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 font-medium block"
                                            >
                                                前往通知中心
                                            </Link>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>

                            <!-- Settings Dropdown -->
                            <div class="relative ms-2">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center whitespace-nowrap rounded-md border border-transparent dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm font-medium leading-4 text-gray-500 dark:text-gray-300 transition duration-150 ease-in-out hover:text-gray-700 dark:hover:text-gray-100 focus:outline-none"
                                            >
                                                {{ $page.props.auth.user.name }}

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
                                        <DropdownLink :href="route('notifications.index')">
                                            通知中心
                                        </DropdownLink>
                                        <DropdownLink
                                            v-if="$page.props.auth.user.role === 'admin'"
                                            :href="route('audit-logs.index')"
                                        >
                                            系統審計日誌
                                        </DropdownLink>
                                        <DropdownLink
                                            v-if="$page.props.auth.user.role === 'admin'"
                                            :href="route('webhooks.index')"
                                        >
                                            外部整合 (Webhooks)
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('delegations.index')"
                                        >
                                            職務代理人設定
                                        </DropdownLink>
                                        <DropdownLink
                                            v-if="['hr', 'admin', 'manager'].includes($page.props.auth.user.role)"
                                            :href="route('attendance.reports.index')"
                                        >
                                            考勤月報統計
                                        </DropdownLink>
                                        <DropdownLink
                                            v-if="['hr', 'admin', 'manager'].includes($page.props.auth.user.role)"
                                            :href="route('reports.index')"
                                        >
                                            表單統計報表
                                        </DropdownLink>
                                        <DropdownLink :href="route('holidays.index')">
                                            假日日曆
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
                                            個人帳號設定
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            登出系統
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
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

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden max-h-[calc(100dvh-4rem)] overflow-y-auto overscroll-contain"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            :href="route('dashboard')"
                            :active="route().current('dashboard')"
                        >
                            總覽儀表板
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('announcements.index')"
                            :active="route().current('announcements.*')"
                        >
                            企業公告
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('forms.index')"
                            :active="route().current('forms.*')"
                        >
                            表單簽核
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('approvals.index')"
                            :active="route().current('approvals.*')"
                        >
                            主管審批中心
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('directory.index')"
                            :active="route().current('directory.*')"
                        >
                            組織通訊錄
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('attendance.index')"
                            :active="route().current('attendance.*')"
                        >
                            考勤打卡
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('leave-balances.index')"
                            :active="route().current('leave-balances.*')"
                        >
                            休假額度
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('meeting-rooms.index')"
                            :active="route().current('meeting-rooms.*')"
                        >
                            會議室借用
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('documents.index')"
                            :active="route().current('documents.*')"
                        >
                            企業文件庫
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('calendar.index')"
                            :active="route().current('calendar.*')"
                        >
                            全景行事曆
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('polls.index')"
                            :active="route().current('polls.*')"
                        >
                            同仁投票
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('projects.index')"
                            :active="route().current('projects.*')"
                        >
                            專案看板
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('assets.index')"
                            :active="route().current('assets.*')"
                        >
                            資產設備
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('weekly-reports.index')"
                            :active="route().current('weekly-reports.*')"
                        >
                            工作週報
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('payslips.index')"
                            :active="route().current('payslips.*')"
                        >
                            薪資明細
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('meal-orders.index')"
                            :active="route().current('meal-orders.*')"
                        >
                            訂餐揪團
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('tickets.index')"
                            :active="route().current('tickets.*')"
                        >
                            服務叫修
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('visitor-reservations.index')"
                            :active="route().current('visitor-reservations.*')"
                        >
                            訪客接待
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('vehicles.index')"
                            :active="route().current('vehicles.*')"
                        >
                            公務派車
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('trainings.index')"
                            :active="route().current('trainings.*')"
                        >
                            企業內訓
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('recognition.index')"
                            :active="route().current('recognition.*') || route().current('welfare.*')"
                        >
                            感謝牆與福利
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="$page.props.auth.user.role === 'admin'"
                            :href="route('audit-logs.index')"
                            :active="route().current('audit-logs.*')"
                        >
                            系統日誌
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="$page.props.auth.user.role === 'admin'"
                            :href="route('system-health.index')"
                            :active="route().current('system-health.*')"
                        >
                            系統健康
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="$page.props.auth.user.role === 'admin'"
                            :href="route('webhooks.index')"
                            :active="route().current('webhooks.*')"
                        >
                            整合設定
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="['admin', 'hr'].includes($page.props.auth.user.role)"
                            :href="route('org-management.index')"
                            :active="route().current('org-management.*')"
                        >
                            組織與員工管理
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="['admin', 'hr'].includes($page.props.auth.user.role)"
                            :href="route('attendance.settings')"
                            :active="route().current('attendance.settings')"
                        >
                            考勤圍欄設定
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="$page.props.auth.user.role === 'admin'"
                            :href="route('branding.edit')"
                            :active="route().current('branding.edit')"
                        >
                            品牌設定
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div
                        class="border-t border-gray-200 dark:border-gray-700 pb-1 pt-4"
                    >
                        <div class="px-4 flex items-center justify-between">
                            <div>
                                <div
                                    class="text-base font-medium text-gray-800 dark:text-gray-200"
                                >
                                    {{ $page.props.auth.user.name }}
                                </div>
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    {{ $page.props.auth.user.email }}
                                </div>
                            </div>
                            <!-- 手機版主題切換開關 -->
                            <ThemeToggle />
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('notifications.index')">
                                個人通知中心
                                <span
                                    v-if="$page.props.auth.unread_notifications_count > 0"
                                    class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800"
                                >
                                    {{ $page.props.auth.unread_notifications_count }}
                                </span>
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                v-if="$page.props.auth.user.role === 'admin'"
                                :href="route('audit-logs.index')"
                            >
                                系統審計日誌
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                v-if="$page.props.auth.user.role === 'admin'"
                                :href="route('webhooks.index')"
                            >
                                外部整合 (Webhooks)
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('delegations.index')">
                                職務代理人設定
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                v-if="['hr', 'admin', 'manager'].includes($page.props.auth.user.role)"
                                :href="route('attendance.reports.index')"
                            >
                                考勤月報統計
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                v-if="['hr', 'admin', 'manager'].includes($page.props.auth.user.role)"
                                :href="route('reports.index')"
                            >
                                表單統計報表
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('holidays.index')">
                                假日日曆
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('profile.edit')">
                                個人帳號設定
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                登出系統
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header class="page-header" v-if="$slots.header">
                <div class="mx-auto max-w-7xl px-4 pt-8 pb-2 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>

        <!-- 全站錯誤提示條 -->
        <div
            v-if="hasErrorToast"
            role="alert"
            class="fixed bottom-4 right-4 left-4 sm:left-auto sm:w-96 z-[60] rounded-xl border border-red-200 bg-white p-4 shadow-lg dark:border-red-900/60 dark:bg-gray-800"
        >
            <div class="flex items-start gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-red-700 dark:text-red-400">操作未完成,請修正以下問題</p>
                    <ul class="mt-1 list-disc space-y-0.5 pl-4 text-xs text-gray-700 dark:text-gray-300">
                        <li v-for="m in errorToast" :key="m">{{ m }}</li>
                    </ul>
                </div>
                <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" aria-label="關閉" @click="errorToast = []">✕</button>
            </div>
        </div>

        <!-- 全站快捷搜尋指揮中心 (Global Command Palette) -->
        <CommandPalette ref="commandPaletteRef" />
        <DialogHost />
    </div>
</template>
