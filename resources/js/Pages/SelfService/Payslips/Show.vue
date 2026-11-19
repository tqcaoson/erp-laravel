<template>
    <div class="mx-auto max-w-2xl space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Phiếu lương tháng {{ payslip.payroll_run?.period }}</h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ payslip.employee?.full_name }} ({{ payslip.employee?.code }}) · {{ payslip.payroll_run?.code }}
                </p>
            </div>
            <StatusBadge :status="payslip.status" />
        </div>

        <div class="space-y-3 rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div class="flex justify-between text-sm">
                <span class="text-slate-600">Lương cơ bản</span>
                <span class="font-medium text-slate-900">{{ money(payslip.base_salary) }}</span>
            </div>
            <div v-for="line in payslip.lines" :key="line.id" class="flex justify-between text-sm">
                <span class="text-slate-600">{{ line.label }} <span class="text-xs text-slate-400">({{ line.component_code }})</span></span>
                <span :class="line.type === 'earning' ? 'font-medium text-green-700' : 'font-medium text-red-600'">
                    {{ line.type === 'earning' ? '+' : '−' }}{{ money(line.amount) }}
                </span>
            </div>
            <div class="flex justify-between border-t border-slate-200 pt-3 text-sm">
                <span class="text-slate-600">Phụ cấp</span>
                <span class="font-medium text-slate-900">{{ money(payslip.allowances) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-600">Khấu trừ</span>
                <span class="font-medium text-red-600">{{ money(payslip.deductions) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-600">Bảo hiểm NLĐ</span>
                <span class="font-medium text-red-600">{{ money(payslip.insurance_employee) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-600">Thuế TNCN</span>
                <span class="font-medium text-red-600">{{ money(payslip.tax) }}</span>
            </div>
            <div class="flex justify-between border-t border-slate-200 pt-3 text-base">
                <span class="font-semibold text-slate-900">Thực lĩnh</span>
                <span class="font-bold text-green-700">{{ money(payslip.net_salary) }}</span>
            </div>
        </div>

        <Link :href="route('my.payslips.index')" class="inline-block rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">
            Về danh sách
        </Link>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat.js';

defineProps({
    payslip: { type: Object, required: true },
});

const { money } = useFormat();
</script>
