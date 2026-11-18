<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Nhân viên</h1>
                <p class="mt-1 text-sm text-slate-500">Danh sách hồ sơ nhân viên theo phòng ban.</p>
            </div>
            <Link
                v-if="can('hr.employee.manage')"
                :href="route('hr.employees.create')"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
            >
                + Thêm nhân viên
            </Link>
        </div>

        <!-- Bộ lọc -->
        <div class="flex flex-wrap gap-3 rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <input
                v-model="filterForm.search"
                type="text"
                placeholder="Tìm theo tên, mã, email…"
                class="min-w-56 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                @keyup.enter="applyFilters"
            />
            <select v-model="filterForm.filter.status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                <option value="">Tất cả trạng thái</option>
                <option value="active">Đang làm việc</option>
                <option value="on_leave">Đang nghỉ phép</option>
                <option value="resigned">Đã nghỉ việc</option>
            </select>
            <select v-model="filterForm.filter.department_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                <option value="">Tất cả phòng ban</option>
                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
            <button @click="applyFilters" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Lọc</button>
        </div>

        <DataTable :columns="columns" :items="employees" empty-text="Chưa có nhân viên nào.">
            <template #cell-status="{ value }">
                <StatusBadge :status="employeeStatusBadge(value)" />
            </template>
            <template #cell-department="{ row }">
                {{ row.department?.name ?? '—' }}
            </template>
            <template #cell-position="{ row }">
                {{ row.position?.name ?? '—' }}
            </template>
            <template #cell-base_salary="{ value }">
                {{ money(value) }}
            </template>
            <template #actions="{ row }">
                <div class="flex justify-end gap-2">
                    <Link :href="route('hr.employees.show', row.id)" class="font-medium text-blue-600 hover:underline">Xem</Link>
                    <Link
                        v-if="can('hr.employee.manage')"
                        :href="route('hr.employees.edit', row.id)"
                        class="font-medium text-slate-600 hover:underline"
                    >
                        Sửa
                    </Link>
                </div>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat.js';
import { usePermission } from '../../../composables/usePermission.js';

const props = defineProps({
    employees: { type: Object, required: true },
    departments: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const { money } = useFormat();
const { can } = usePermission();

const columns = [
    { key: 'code', label: 'Mã NV', sortable: true },
    { key: 'full_name', label: 'Họ tên', sortable: true },
    { key: 'department', label: 'Phòng ban' },
    { key: 'position', label: 'Chức danh' },
    { key: 'phone', label: 'Điện thoại' },
    { key: 'base_salary', label: 'Lương cơ bản', sortable: true },
    { key: 'status', label: 'Trạng thái' },
];

const filterForm = reactive({
    search: props.filters.search ?? '',
    filter: {
        status: props.filters.filter?.status ?? '',
        department_id: props.filters.filter?.department_id ?? '',
    },
});

function applyFilters() {
    router.get(route('hr.employees.index'), filterForm, { preserveState: true, preserveScroll: true });
}

/** Map trạng thái nhân viên sang badge có sẵn (on_leave/resigned chưa có trong map). */
function employeeStatusBadge(status) {
    if (status === 'on_leave') return 'pending';
    if (status === 'resigned') return 'inactive';
    return status;
}
</script>
