<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Chấm công của tôi</h1>
            <p v-if="employee" class="mt-1 text-sm text-slate-500">{{ employee.full_name }} ({{ employee.code }}) — chỉ chấm trong ngày hôm nay, giờ đã chấm không sửa lại được.</p>
        </div>

        <div v-if="!employee" class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
            Tài khoản chưa gắn với hồ sơ nhân viên nên chưa xem hay chấm công được. Vui lòng liên hệ phòng nhân sự để được gắn hồ sơ.
        </div>

        <template v-else>
            <div class="flex flex-wrap items-center gap-3 rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <div class="flex-1">
                    <p class="text-sm text-slate-500">Hôm nay</p>
                    <p class="text-sm font-medium text-slate-900">
                        Giờ vào: <span class="font-semibold">{{ todayLog?.check_in ? datetime(todayLog.check_in) : '—' }}</span>
                        ·
                        Giờ ra: <span class="font-semibold">{{ todayLog?.check_out ? datetime(todayLog.check_out) : '—' }}</span>
                    </p>
                </div>
                <button
                    v-if="!todayLog?.check_in"
                    @click="checkIn"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                >
                    Chấm vào
                </button>
                <button
                    v-else-if="!todayLog?.check_out"
                    @click="checkOut"
                    class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700"
                >
                    Chấm ra
                </button>
                <span v-else class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-600">Đã chấm đủ hôm nay</span>
            </div>

            <div class="flex flex-wrap gap-3 rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <input v-model="filterForm.filter.work_date" type="date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" @change="applyFilters" />
            </div>

            <DataTable :columns="columns" :items="logs" empty-text="Chưa có bản ghi chấm công nào.">
                <template #cell-work_date="{ value }">
                    {{ date(value) }}
                </template>
                <template #cell-check_in="{ value }">
                    {{ datetime(value) }}
                </template>
                <template #cell-check_out="{ value }">
                    {{ datetime(value) }}
                </template>
                <template #cell-shift="{ row }">
                    {{ row.shift?.name ?? '—' }}
                </template>
                <template #cell-status="{ value }">
                    <StatusBadge :status="attendanceBadge(value)" />
                </template>
            </DataTable>
        </template>
    </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat.js';

const props = defineProps({
    employee: { type: Object, default: null },
    logs: { type: Object, default: null },
    todayLog: { type: Object, default: null },
    filters: { type: Object, default: () => ({}) },
});

const { date, datetime } = useFormat();

const columns = [
    { key: 'work_date', label: 'Ngày làm việc' },
    { key: 'shift', label: 'Ca' },
    { key: 'check_in', label: 'Giờ vào' },
    { key: 'check_out', label: 'Giờ ra' },
    { key: 'status', label: 'Trạng thái' },
];

const filterForm = reactive({
    filter: {
        work_date: props.filters.filter?.work_date ?? '',
    },
});

function applyFilters() {
    router.get(route('my.attendance.index'), filterForm, { preserveState: true, preserveScroll: true });
}

function checkIn() {
    router.post(route('my.attendance.store'), { action: 'check_in' }, { preserveScroll: true });
}

function checkOut() {
    router.post(route('my.attendance.store'), { action: 'check_out' }, { preserveScroll: true });
}

function attendanceBadge(status) {
    return { present: 'completed', late: 'pending', absent: 'rejected', leave: 'approved' }[status] ?? status;
}
</script>
