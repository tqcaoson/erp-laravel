<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Nghỉ phép</h1>
                <p class="mt-1 text-sm text-slate-500">Đăng ký và duyệt đơn nghỉ phép. Chỉ phép năm mới trừ vào số dư phép (mặc định 12 ngày/năm).</p>
            </div>
            <Link
                v-if="can('hr.leave.request')"
                :href="route('hr.leave-requests.create')"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
            >
                + Đăng ký nghỉ
            </Link>
        </div>

        <div class="flex flex-wrap gap-3 rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <select v-model="filterForm.filter.status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" @change="applyFilters">
                <option value="">Tất cả trạng thái</option>
                <option value="pending">Chờ duyệt</option>
                <option value="approved">Đã duyệt</option>
                <option value="rejected">Từ chối</option>
                <option value="cancelled">Đã hủy</option>
            </select>
            <select v-model="filterForm.filter.employee_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" @change="applyFilters">
                <option value="">Tất cả nhân viên</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name }}</option>
            </select>
        </div>

        <DataTable :columns="columns" :items="leaveRequests" empty-text="Chưa có đơn nghỉ phép nào.">
            <template #cell-employee="{ row }">
                {{ row.employee?.full_name ?? '—' }} <span class="text-xs text-slate-400">({{ row.employee?.code }})</span>
            </template>
            <template #cell-leave_type="{ value }">
                {{ leaveTypeLabel(value) }}
            </template>
            <template #cell-start_date="{ row }">
                {{ date(row.start_date) }} – {{ date(row.end_date) }}
            </template>
            <template #cell-status="{ value }">
                <StatusBadge :status="value" />
            </template>
            <template #actions="{ row }">
                <div v-if="row.status === 'pending' && can('hr.leave.approve')" class="flex justify-end gap-2">
                    <button @click="approve(row)" class="font-medium text-green-600 hover:underline">Duyệt</button>
                    <button @click="reject(row)" class="font-medium text-red-600 hover:underline">Từ chối</button>
                </div>
                <span v-else class="text-xs text-slate-400">{{ row.approver?.name ?? '' }}</span>
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
    leaveRequests: { type: Object, required: true },
    employees: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const { date } = useFormat();
const { can } = usePermission();

const columns = [
    { key: 'code', label: 'Mã đơn' },
    { key: 'employee', label: 'Nhân viên' },
    { key: 'leave_type', label: 'Loại nghỉ' },
    { key: 'start_date', label: 'Thời gian nghỉ' },
    { key: 'days', label: 'Số ngày' },
    { key: 'reason', label: 'Lý do' },
    { key: 'status', label: 'Trạng thái' },
];

const filterForm = reactive({
    filter: {
        status: props.filters.filter?.status ?? '',
        employee_id: props.filters.filter?.employee_id ?? '',
    },
});

function applyFilters() {
    router.get(route('hr.leave-requests.index'), filterForm, { preserveState: true, preserveScroll: true });
}

function leaveTypeLabel(type) {
    return { annual: 'Phép năm', sick: 'Nghỉ ốm', unpaid: 'Không lương' }[type] ?? type;
}

function approve(row) {
    if (confirm(`Duyệt đơn nghỉ ${row.code} của ${row.employee?.full_name}? Phép năm sẽ bị trừ vào số dư.`)) {
        router.post(route('hr.leave-requests.approve', row.id));
    }
}

function reject(row) {
    if (confirm(`Từ chối đơn nghỉ ${row.code}?`)) {
        router.post(route('hr.leave-requests.reject', row.id));
    }
}
</script>
