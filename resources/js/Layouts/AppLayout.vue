<template>
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="hidden w-64 shrink-0 flex-col bg-slate-900 text-slate-200 md:flex">
            <div class="flex h-16 items-center gap-2 border-b border-slate-800 px-5">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-600 text-lg font-bold text-white">E</span>
                <div>
                    <p class="text-base font-bold leading-tight text-white">ERP An Phát</p>
                    <p class="text-xs text-slate-400">Quản trị doanh nghiệp</p>
                </div>
            </div>
            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                <template v-for="group in navGroups" :key="group.label">
                    <p class="px-2 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wider text-slate-500 first:pt-0">
                        {{ group.label }}
                    </p>
                    <Link
                        v-for="item in group.items"
                        :key="item.label"
                        :href="item.href"
                        :class="[
                            'flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition',
                            isActive(item.href) ? 'bg-blue-600 text-white' : 'hover:bg-slate-800 hover:text-white',
                        ]"
                    >
                        <span class="w-5 text-center">{{ item.icon }}</span>
                        {{ item.label }}
                    </Link>
                </template>
            </nav>
            <div class="border-t border-slate-800 p-4 text-xs text-slate-400">
                <p>{{ user?.name }}</p>
                <p class="truncate">{{ user?.email }}</p>
            </div>
        </aside>

        <!-- Nội dung chính -->
        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Topbar -->
            <header class="sticky top-0 z-40 flex h-16 items-center gap-3 border-b border-slate-200 bg-white px-4 sm:px-6">
                <button class="rounded-md p-2 text-slate-500 hover:bg-slate-100 md:hidden" @click="mobileNav = !mobileNav">☰</button>

                <!-- Đổi công ty -->
                <div class="relative">
                    <select
                        :value="company?.current?.id"
                        class="appearance-none rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-8 text-sm font-medium text-slate-700 focus:border-blue-500 focus:outline-none"
                        @change="switchCompany($event.target.value)"
                    >
                        <option v-for="c in company?.list ?? []" :key="c.id" :value="c.id">
                            {{ c.name }}
                        </option>
                    </select>
                    <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-slate-400">▾</span>
                </div>

                <!-- Tìm kiếm nhanh Ctrl+K -->
                <div class="relative mx-auto hidden w-full max-w-md sm:block">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Tìm kiếm nhanh (Ctrl+K)…"
                        class="w-full rounded-lg border border-slate-300 py-2 pl-9 pr-12 text-sm focus:border-blue-500 focus:outline-none"
                        @keydown.enter="goSearch"
                    />
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">🔍</span>
                    <kbd class="absolute right-3 top-1/2 -translate-y-1/2 rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[11px] text-slate-500">Ctrl K</kbd>
                    <div
                        v-if="search && searchResults.length"
                        class="absolute left-0 right-0 top-full z-50 mt-1 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg"
                    >
                        <Link
                            v-for="r in searchResults"
                            :key="r.label"
                            :href="r.href"
                            class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
                            @click="search = ''"
                        >
                            {{ r.icon }} {{ r.label }}
                        </Link>
                    </div>
                </div>

                <div class="ml-auto flex items-center gap-2">
                    <span class="hidden rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 lg:inline">
                        {{ company?.current?.name }}
                    </span>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100"
                    >
                        Đăng xuất
                    </Link>
                </div>
            </header>

            <!-- Menu mobile -->
            <nav v-if="mobileNav" class="border-b border-slate-200 bg-slate-900 p-3 text-slate-200 md:hidden">
                <Link
                    v-for="item in flatNav"
                    :key="item.label"
                    :href="item.href"
                    class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800"
                    @click="mobileNav = false"
                >
                    {{ item.icon }} {{ item.label }}
                </Link>
            </nav>

            <!-- Flash message -->
            <div v-if="$page.props.flash?.success" class="mx-4 mt-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800 ring-1 ring-green-200 sm:mx-6">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="mx-4 mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200 sm:mx-6">
                {{ $page.props.flash.error }}
            </div>

            <main class="flex-1 p-4 sm:p-6">
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const company = computed(() => page.props.company);
const search = ref('');
const mobileNav = ref(false);

// Mỗi mục menu gắn với 1 quyền xem; user chỉ thấy mục nào mình có quyền.
function can(permission) {
    if (!permission) return true;
    return user.value?.permissions?.includes(permission) ?? false;
}

const allNavGroups = [
    {
        label: 'Tổng quan',
        items: [{ label: 'Dashboard', icon: '🏠', href: '/dashboard', permission: 'reporting.dashboard.view' }],
    },
    {
        label: 'Nghiệp vụ',
        items: [
            { label: 'Kế toán', icon: '💰', href: '/accounting/journal-entries', permission: 'accounting.entry.view' },
            { label: 'Mua hàng', icon: '🛒', href: '/procurement/purchase-orders', permission: 'procurement.po.view' },
            { label: 'Kho', icon: '📦', href: '/inventory/stock', permission: 'inventory.stock.view' },
            { label: 'Bán hàng', icon: '🤝', href: '/sales/orders', permission: 'sales.order.view' },
            { label: 'Sản xuất', icon: '🏭', href: '/manufacturing/orders', permission: 'manufacturing.mo.view' },
            { label: 'Nhân sự', icon: '👥', href: '/hr/employees', permission: 'hr.employee.view' },
            { label: 'Báo cáo', icon: '📊', href: '/reports', permission: 'reporting.report.view' },
        ],
    },
    {
        label: 'Danh mục',
        items: [
            { label: 'Khách hàng', icon: '🧑', href: '/sales/customers', permission: 'sales.customer.view' },
            { label: 'Nhà cung cấp', icon: '🏢', href: '/procurement/suppliers', permission: 'procurement.supplier.view' },
            { label: 'Sản phẩm', icon: '📋', href: '/inventory/products', permission: 'inventory.product.view' },
            { label: 'Hệ thống tài khoản', icon: '📒', href: '/accounting/chart-of-accounts', permission: 'accounting.account.view' },
        ],
    },
];

// Chỉ hiện nhóm/mục mà user có quyền; nhóm trống thì ẩn luôn tiêu đề nhóm.
const navGroups = computed(() =>
    allNavGroups
        .map((group) => ({ ...group, items: group.items.filter((item) => can(item.permission)) }))
        .filter((group) => group.items.length > 0),
);

const flatNav = computed(() => navGroups.value.flatMap((g) => g.items));

const searchResults = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return [];
    return flatNav.value.filter((item) => item.label.toLowerCase().includes(q)).slice(0, 6);
});

function isActive(href) {
    const path = page.url.split('?')[0];
    return path === href || path.startsWith(href + '/');
}

function switchCompany(companyId) {
    router.post(route('company.switch'), { company_id: Number(companyId) }, { preserveScroll: true });
}

function goSearch() {
    if (searchResults.value.length > 0) {
        router.visit(searchResults.value[0].href);
        search.value = '';
    }
}

function onKeydown(event) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        document.querySelector('input[placeholder*="Tìm kiếm"]')?.focus();
    }
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));
</script>
