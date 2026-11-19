<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Người dùng</h1>
                <p class="mt-1 text-sm text-slate-500">Tài khoản đăng nhập hệ thống, vai trò và trạng thái hoạt động.</p>
            </div>
            <Link :href="route('admin.users.create')" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                + Thêm người dùng
            </Link>
        </div>

        <div class="flex flex-wrap gap-3 rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <input
                v-model="filterForm.filter.search"
                type="text"
                placeholder="Tìm theo tên hoặc email…"
                class="min-w-64 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                @change="applyFilters"
            />
            <select v-model="filterForm.filter.is_active" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" @change="applyFilters">
                <option value="">Tất cả trạng thái</option>
                <option value="1">Đang hoạt động</option>
                <option value="0">Đã vô hiệu</option>
            </select>
        </div>

        <DataTable :columns="columns" :items="users" empty-text="Chưa có tài khoản nào.">
            <template #cell-name="{ row }">
                <span class="font-medium text-slate-900">{{ row.name }}</span>
            </template>
            <template #cell-roles="{ row }">
                <span v-if="row.roles?.length">{{ row.roles.map((r) => r.label).join(', ') }}</span>
                <span v-else class="text-slate-400">—</span>
            </template>
            <template #cell-is_active="{ value }">
                <StatusBadge :status="value ? 'active' : 'inactive'" />
            </template>
            <template #actions="{ row }">
                <div class="flex items-center justify-end gap-2">
                    <Link :href="route('admin.users.edit', row.id)" class="font-medium text-blue-600 hover:underline">Sửa</Link>
                    <button
                        v-if="row.id !== currentUserId"
                        @click="toggleActive(row)"
                        class="font-medium text-amber-600 hover:underline"
                    >
                        {{ row.is_active ? 'Vô hiệu' : 'Kích hoạt' }}
                    </button>
                    <button
                        v-if="row.id !== currentUserId"
                        @click="destroy(row)"
                        class="font-medium text-red-600 hover:underline"
                    >
                        Xóa
                    </button>
                </div>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';

const props = defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id);

const columns = [
    { key: 'name', label: 'Tên' },
    { key: 'email', label: 'Email' },
    { key: 'roles', label: 'Vai trò' },
    { key: 'is_active', label: 'Trạng thái' },
];

const filterForm = reactive({
    filter: {
        search: props.filters.filter?.search ?? '',
        is_active: props.filters.filter?.is_active ?? '',
    },
});

function applyFilters() {
    router.get(route('admin.users.index'), filterForm, { preserveState: true, preserveScroll: true });
}

function toggleActive(user) {
    const action = user.is_active ? 'vô hiệu' : 'kích hoạt lại';
    if (confirm(`${action === 'vô hiệu' ? 'Vô hiệu' : 'Kích hoạt lại'} tài khoản ${user.email}?${user.is_active ? ' Tài khoản bị vô hiệu sẽ không đăng nhập được nữa.' : ''}`)) {
        router.post(route('admin.users.toggle-active', user.id), {}, { preserveScroll: true });
    }
}

function destroy(user) {
    if (confirm(`Xóa tài khoản ${user.email}? Hành động này không thể hoàn tác. Nếu chỉ muốn tạm dừng, hãy chọn “Vô hiệu” thay vì xóa.`)) {
        router.delete(route('admin.users.destroy', user.id), { preserveScroll: true });
    }
}
</script>
