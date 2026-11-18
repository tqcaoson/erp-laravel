<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Chấm công</h1>
            <p class="mt-1 text-sm text-slate-500">Bản ghi chấm công theo ngày. Nhập tay tại form dưới (mỗi nhân viên 1 bản ghi/ngày).</p>
        </div>

        <form
            v-if="can('hr.attendance.manage')"
            @submit.prevent="submit"
            class="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200"
        >
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Nhân viên *</label>
                <select v-model="form.employee_id" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <option value="" disabled>— Chọn —</option>
                    <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name }}</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Ngày làm việc *</label>
                <input v-model="form.work_date" type="date" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Ca</label>
                <select v-model="form.shift_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <option :value="null">— Không chọn —</option>
                    <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Giờ vào</label>
                <input v-model="form.check_in" type="datetime-local" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Giờ ra</label>
                <input v-model="form.check_out" type="datetime-local" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Trạng thái *</label>
                <select v-model="form.status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <option value="present">Đúng giờ</option>
                    <option value="late">Đi muộn</option>
                    <option value="absent">Vắng mặt</option>
                    <option value="leave">Nghỉ phép</option>
                </select>
            </div>
            <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                Lưu chấm công
            </button>
        </form>

        <div class="flex flex-wrap gap-3 rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <input v-model="filterForm.filter.work_date" type="date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" @change="applyFilters" />
            <select v-model="filterForm.filter.employee_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" @change="applyFilters">
                <option value="">Tất cả nhân viên</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name }}</option>
            </select>
            <select v-model="filterForm.filter.status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" @change="applyFilters">
                <option value="">Tất cả trạng thái</option>
                <option value="present">Đúng giờ</option>
                <option value="late">Đi muộn</option>
                <option value="absent">Vắng mặt</option>
                <option value="leave">Nghỉ phép</option>
            </select>
        </div>

        <DataTable :columns="columns" :items="logs" empty-text="Chưa có bản ghi chấm công nào.">
            <template #cell-employee="{ row }">
                {{ row.employee?.full_name ?? '—' }} <span class="text-xs text-slate-400">({{ row.employee?.code }})</span>
            </template>
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
    </div>
</template>

<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat.js';
import { usePermission } from '../../../composables/usePermission.js';

const props = defineProps({
    logs: { type: Object, required: true },
    employees: { type: Array, default: () => [] },
    shifts: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const { date, datetime } = useFormat();
const { can } = usePermission();

const columns = [
    { key: 'employee', label: 'Nhân viên' },
    { key: 'work_date', label: 'Ngày làm việc' },
    { key: 'shift', label: 'Ca' },
    { key: 'check_in', label: 'Giờ vào' },
    { key: 'check_out', label: 'Giờ ra' },
    { key: 'status', label: 'Trạng thái' },
];

const form = useForm({
    employee_id: '',
    shift_id: null,
    work_date: '',
    check_in: '',
    check_out: '',
    status: 'present',
});

const filterForm = reactive({
    filter: {
        work_date: props.filters.filter?.work_date ?? '',
        employee_id: props.filters.filter?.employee_id ?? '',
        status: props.filters.filter?.status ?? '',
    },
});

function submit() {
    form.post(route('hr.attendance.store'), { onSuccess: () => form.reset('check_in', 'check_out') });
}

function applyFilters() {
    router.get(route('hr.attendance.index'), filterForm, { preserveState: true, preserveScroll: true });
}

function attendanceBadge(status) {
    return { present: 'completed', late: 'pending', absent: 'rejected', leave: 'approved' }[status] ?? status;
}
</script>
