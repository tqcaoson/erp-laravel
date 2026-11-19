<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Phiếu lương của tôi</h1>
            <p v-if="employee" class="mt-1 text-sm text-slate-500">{{ employee.full_name }} ({{ employee.code }})</p>
        </div>

        <div v-if="!employee" class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
            Tài khoản chưa gắn với hồ sơ nhân viên nên chưa xem được phiếu lương. Vui lòng liên hệ phòng nhân sự để được gắn hồ sơ.
        </div>

        <DataTable v-else :columns="columns" :items="payslips" empty-text="Bạn chưa có phiếu lương nào.">
            <template #cell-payrollRun="{ row }">
                Tháng {{ row.payroll_run?.period ?? '—' }}
                <span class="text-xs text-slate-400">({{ row.payroll_run?.code }})</span>
            </template>
            <template #cell-base_salary="{ value }">
                {{ money(value) }}
            </template>
            <template #cell-allowances="{ value }">
                {{ money(value) }}
            </template>
            <template #cell-net_salary="{ value }">
                <span class="font-semibold text-slate-900">{{ money(value) }}</span>
            </template>
            <template #cell-status="{ value }">
                <StatusBadge :status="value" />
            </template>
            <template #actions="{ row }">
                <Link :href="route('my.payslips.show', row.id)" class="font-medium text-blue-600 hover:underline">Xem</Link>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat.js';

defineProps({
    employee: { type: Object, default: null },
    payslips: { type: Object, default: null },
});

const { money } = useFormat();

const columns = [
    { key: 'payrollRun', label: 'Kỳ lương' },
    { key: 'base_salary', label: 'Lương cơ bản' },
    { key: 'allowances', label: 'Phụ cấp' },
    { key: 'net_salary', label: 'Thực lĩnh' },
    { key: 'status', label: 'Trạng thái' },
];
</script>
