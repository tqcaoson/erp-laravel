<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Bảng lương</h1>
            <p class="mt-1 text-sm text-slate-500">Kỳ lương theo tháng: tạo kỳ → tính lương → xem trước → khóa → duyệt (sinh bút toán lương) → đánh dấu đã trả.</p>
        </div>

        <form
            v-if="can('hr.payroll.run')"
            @submit.prevent="submit"
            class="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200"
        >
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Kỳ lương (tháng) *</label>
                <input v-model="form.period" type="month" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                <p v-if="form.errors.period" class="mt-1 text-sm text-red-600">{{ form.errors.period }}</p>
            </div>
            <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                Tạo kỳ lương
            </button>
            <p class="text-xs text-slate-400">Mỗi tháng chỉ có 1 kỳ lương. Mã kỳ (PRN-2026-0001…) sinh tự động.</p>
        </form>

        <div class="flex flex-wrap gap-3 rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <select v-model="filterForm.filter.status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" @change="applyFilters">
                <option value="">Tất cả trạng thái</option>
                <option value="draft">Nháp</option>
                <option value="calculated">Đã tính</option>
                <option value="locked">Đã khóa</option>
                <option value="approved">Đã duyệt</option>
                <option value="paid">Đã trả</option>
            </select>
        </div>

        <DataTable :columns="columns" :items="payrollRuns" empty-text="Chưa có kỳ lương nào.">
            <template #cell-period="{ value }">
                Tháng {{ value }}
            </template>
            <template #cell-status="{ value }">
                <StatusBadge :status="value" />
            </template>
            <template #cell-total_gross="{ value }">
                {{ money(value) }}
            </template>
            <template #cell-total_net="{ value }">
                <span class="font-medium">{{ money(value) }}</span>
            </template>
            <template #cell-locked_at="{ value }">
                {{ value ? datetime(value) : '—' }}
            </template>
            <template #actions="{ row }">
                <Link :href="route('hr.payroll-runs.show', row.id)" class="font-medium text-blue-600 hover:underline">Chi tiết</Link>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat.js';
import { usePermission } from '../../../composables/usePermission.js';

const props = defineProps({
    payrollRuns: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { money, datetime } = useFormat();
const { can } = usePermission();

const columns = [
    { key: 'code', label: 'Mã kỳ' },
    { key: 'period', label: 'Kỳ lương' },
    { key: 'status', label: 'Trạng thái' },
    { key: 'payslips_count', label: 'Số phiếu lương' },
    { key: 'total_gross', label: 'Tổng thu nhập' },
    { key: 'total_net', label: 'Tổng thực lĩnh' },
    { key: 'locked_at', label: 'Khóa lúc' },
];

const form = useForm({ period: '' });

const filterForm = reactive({
    filter: { status: props.filters.filter?.status ?? '' },
});

function submit() {
    form.post(route('hr.payroll-runs.store'));
}

function applyFilters() {
    router.get(route('hr.payroll-runs.index'), filterForm, { preserveState: true, preserveScroll: true });
}
</script>
