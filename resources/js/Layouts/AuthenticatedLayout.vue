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
        <div class="min-h-screen bg-gray-100">
            <nav
                class="border-b border-gray-100 bg-white"
            >
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-full px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between items-center">
                        <div class="flex items-center min-w-0">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center mr-2 lg:mr-4">
                                <Link :href="route('dashboard')">
                                    <ApplicationLogo
                                        class="block h-9 w-auto fill-current text-gray-800"
                                    />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div
                                class="hidden space-x-1 sm:space-x-2 md:space-x-3 lg:space-x-4 xl:space-x-6 sm:-my-px sm:flex items-center flex-nowrap"
                            >
                                <NavLink
                                    :href="route('dashboard')"
                                    :active="route().current('dashboard')"
                                    class="whitespace-nowrap"
                                >
                                    總覽儀表板
                                </NavLink>
                                <NavLink
                                    :href="route('announcements.index')"
                                    :active="route().current('announcements.*')"
                                    class="whitespace-nowrap"
                                >
                                    企業公告
                                </NavLink>
                                <NavLink
                                    :href="route('forms.index')"
                                    :active="route().current('forms.*')"
                                    class="whitespace-nowrap"
                                >
                                    表單簽核
                                </NavLink>
                                <NavLink
                                    :href="route('approvals.index')"
                                    :active="route().current('approvals.*')"
                                    class="whitespace-nowrap"
                                >
                                    審批中心
                                </NavLink>
                                <NavLink
                                    :href="route('directory.index')"
                                    :active="route().current('directory.*')"
                                    class="whitespace-nowrap"
                                >
                                    通訊錄
                                </NavLink>
                                <NavLink
                                    :href="route('attendance.index')"
                                    :active="route().current('attendance.*')"
                                    class="whitespace-nowrap"
                                >
                                    考勤打卡
                                </NavLink>
                                <NavLink
                                    :href="route('leave-balances.index')"
                                    :active="route().current('leave-balances.*')"
                                    class="whitespace-nowrap"
                                >
                                    休假額度
                                </NavLink>
                                <NavLink
                                    :href="route('meeting-rooms.index')"
                                    :active="route().current('meeting-rooms.*')"
                                    class="whitespace-nowrap"
                                >
                                    會議室
                                </NavLink>
                                <NavLink
                                    :href="route('documents.index')"
                                    :active="route().current('documents.*')"
                                    class="whitespace-nowrap"
                                >
                                    知識文件
                                </NavLink>
                                <NavLink
                                    :href="route('calendar.index')"
                                    :active="route().current('calendar.*')"
                                    class="whitespace-nowrap"
                                >
                                    行事曆
                                </NavLink>

                                <!-- 系統管理 Dropdown (限管理者與人資) -->
                                <div
                                    v-if="['admin', 'hr'].includes($page.props.auth.user.role)"
                                    class="relative inline-flex items-center"
                                >
                                    <Dropdown align="left" width="48">
                                        <template #trigger>
                                            <button
                                                type="button"
                                                :class="[
                                                    'inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none whitespace-nowrap',
                                                    route().current('audit-logs.*') || route().current('webhooks.*') || route().current('attendance.settings') || route().current('org-management.*')
                                                        ? 'border-indigo-400 text-gray-900 font-bold'
                                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                                ]"
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
                                        </template>
                                    </Dropdown>
                                </div>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center gap-2">
                            <!-- Notification Bell Dropdown -->
                            <div class="relative">
                                <Dropdown align="right" width="60">
                                    <template #trigger>
                                        <button
                                            type="button"
                                            class="relative p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-full transition focus:outline-none"
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
                                        <div class="px-4 py-2 border-b border-gray-100 flex items-center justify-between">
                                            <span class="text-xs font-bold text-gray-700 uppercase tracking-wider">站內通知</span>
                                            <Link
                                                :href="route('notifications.index')"
                                                class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                            >
                                                查看全部
                                            </Link>
                                        </div>

                                        <div v-if="$page.props.auth.recent_notifications && $page.props.auth.recent_notifications.length > 0" class="divide-y divide-gray-100 max-h-80 overflow-y-auto">
                                            <div
                                                v-for="item in $page.props.auth.recent_notifications"
                                                :key="item.id"
                                                class="p-3 hover:bg-gray-50 transition text-left"
                                            >
                                                <div class="flex items-center justify-between mb-1">
                                                    <span class="text-xs font-semibold text-gray-900 truncate max-w-44">{{ item.title }}</span>
                                                    <span class="text-[10px] text-gray-400">{{ item.created_at }}</span>
                                                </div>
                                                <p class="text-xs text-gray-600 line-clamp-2">{{ item.message }}</p>
                                                <div class="mt-2 flex items-center gap-2">
                                                    <Link
                                                        v-if="item.action_url"
                                                        :href="item.action_url"
                                                        class="text-[11px] text-indigo-600 font-semibold hover:underline"
                                                    >
                                                        前往檢視
                                                    </Link>
                                                    <Link
                                                        :href="route('notifications.read', item.id)"
                                                        method="post"
                                                        as="button"
                                                        class="text-[11px] text-gray-400 hover:text-gray-600 ml-auto"
                                                    >
                                                        標記已讀
                                                    </Link>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="p-6 text-center text-xs text-gray-400">
                                            目前沒有任何未讀通知
                                        </div>

                                        <div class="p-2 border-t border-gray-100 bg-gray-50 text-center">
                                            <Link
                                                :href="route('notifications.index')"
                                                class="text-xs text-gray-600 hover:text-indigo-600 font-medium block"
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
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
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
                    class="sm:hidden"
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
                            v-if="$page.props.auth.user.role === 'admin'"
                            :href="route('audit-logs.index')"
                            :active="route().current('audit-logs.*')"
                        >
                            系統日誌
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
                    </div>

                    <!-- Responsive Settings Options -->
                    <div
                        class="border-t border-gray-200 pb-1 pt-4"
                    >
                        <div class="px-4">
                            <div
                                class="text-base font-medium text-gray-800"
                            >
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500">
                                {{ $page.props.auth.user.email }}
                            </div>
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
                            <ResponsiveNavLink :href="route('profile.edit')">
                                個人帳號設定
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

            <!-- Page Heading -->
            <header
                class="bg-white shadow"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
