<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Nghỉ phép của tôi</h1>
                <p v-if="employee" class="mt-1 text-sm text-slate-500">
                    {{ employee.full_name }} ({{ employee.code }}) — còn <span class="font-semibold text-slate-900">{{ remainingLeaveDays }} ngày phép năm</span>.
                </p>
            </div>
            <Link v-if="employee" :href="route('my.leave-requests.create')" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                + Đăng ký nghỉ phép
            </Link>
        </div>

        <div v-if="!employee" class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
            Tài khoản chưa gắn với hồ sơ nhân viên nên chưa xem hay đăng ký nghỉ phép được. Vui lòng liên hệ phòng nhân sự để được gắn hồ sơ.
        </div>

        <template v-else>
            <div class="flex flex-wrap gap-3 rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <select v-model="filterForm.filter.status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" @change="applyFilters">
                    <option value="">Tất cả trạng thái</option>
                    <option value="pending">Chờ xử lý</option>
                    <option value="approved">Đã duyệt</option>
                    <option value="rejected">Từ chối</option>
                    <option value="cancelled">Đã hủy</option>
                </select>
            </div>

            <DataTable :columns="columns" :items="leaveRequests" empty-text="Bạn chưa có đơn nghỉ nào.">
                <template #cell-leave_type="{ value }">
                    {{ leaveTypeLabel(value) }}
                </template>
                <template #cell-start_date="{ value }">
                    {{ date(value) }}
                </template>
                <template #cell-end_date="{ value }">
                    {{ date(value) }}
                </template>
                <template #cell-days="{ value }">
                    {{ value }} ngày
                </template>
                <template #cell-approver="{ row }">
                    {{ row.approver?.name ?? '—' }}
                </template>
                <template #cell-status="{ value }">
                    <StatusBadge :status="value" />
                </template>
                <template #actions="{ row }">
                    <Link :href="route('my.leave-requests.show', row.id)" class="font-medium text-blue-600 hover:underline">Xem</Link>
                </template>
            </DataTable>
        </template>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat.js';

const props = defineProps({
    employee: { type: Object, default: null },
    leaveRequests: { type: Object, default: null },
    remainingLeaveDays: { type: Number, default: null },
    filters: { type: Object, default: () => ({}) },
});

const { date } = useFormat();

const columns = [
    { key: 'code', label: 'Mã đơn' },
    { key: 'leave_type', label: 'Loại nghỉ' },
    { key: 'start_date', label: 'Từ ngày' },
    { key: 'end_date', label: 'Đến ngày' },
    { key: 'days', label: 'Số ngày' },
    { key: 'approver', label: 'Người duyệt' },
    { key: 'status', label: 'Trạng thái' },
];

const filterForm = reactive({
    filter: {
        status: props.filters.filter?.status ?? '',
    },
});

function applyFilters() {
    router.get(route('my.leave-requests.index'), filterForm, { preserveState: true, preserveScroll: true });
}

function leaveTypeLabel(type) {
    return { annual: 'Phép năm', sick: 'Nghỉ ốm', unpaid: 'Nghỉ không lương' }[type] ?? type;
}
</script>
