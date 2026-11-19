<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Bảng lương tháng {{ payrollRun.period }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ payrollRun.code }} · {{ payrollRun.payslips?.length ?? 0 }} nhân viên</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <StatusBadge :status="payrollRun.status" />
                <button
                    v-if="['draft', 'calculated'].includes(payrollRun.status) && can('hr.payroll.run')"
                    @click="calculate"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                >
                    Tính lương
                </button>
                <button
                    v-if="['draft', 'calculated'].includes(payrollRun.status) && can('hr.payroll.run')"
                    @click="calculateAsync"
                    class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50"
                    title="Tính bằng job chạy nền (nên dùng khi nhiều nhân viên)"
                >
                    Tính nền (job)
                </button>
                <button
                    v-if="payrollRun.status === 'calculated' && can('hr.payroll.lock')"
                    @click="lock"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700"
                >
                    Khóa bảng lương
                </button>
                <button
                    v-if="payrollRun.status === 'locked' && can('hr.payroll.approve')"
                    @click="approve"
                    class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700"
                >
                    Duyệt bảng lương
                </button>
                <button
                    v-if="payrollRun.status === 'approved' && can('hr.payroll.pay')"
                    @click="pay"
                    class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                >
                    Đánh dấu đã trả
                </button>
                <Link :href="route('hr.payroll-runs.index')" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">
                    Về danh sách
                </Link>
            </div>
        </div>

        <!-- Tổng quan kỳ lương -->
        <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-medium text-slate-500">Tổng thu nhập</p>
                <p class="mt-1 text-lg font-bold text-slate-900">{{ money(payrollRun.total_gross) }}</p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-medium text-slate-500">Tổng khấu trừ</p>
                <p class="mt-1 text-lg font-bold text-slate-900">{{ money(payrollRun.total_deductions) }}</p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-medium text-slate-500">Tổng bảo hiểm NLĐ</p>
                <p class="mt-1 text-lg font-bold text-slate-900">{{ money(payrollRun.total_insurance) }}</p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-medium text-slate-500">Tổng thuế TNCN</p>
                <p class="mt-1 text-lg font-bold text-slate-900">{{ money(payrollRun.total_tax) }}</p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-medium text-slate-500">Tổng thực lĩnh</p>
                <p class="mt-1 text-lg font-bold text-green-700">{{ money(payrollRun.total_net) }}</p>
            </div>
        </div>

        <p v-if="payrollRun.status === 'paid'" class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800 ring-1 ring-green-200">
            Kỳ lương đã trả cho nhân viên. Bút toán lương đã được gửi sang module Kế toán khi duyệt.
        </p>
        <p v-else-if="payrollRun.status === 'approved'" class="rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-800 ring-1 ring-blue-200">
            Kỳ lương đã duyệt — bút toán lương đã được gửi sang module Kế toán. Chưa đánh dấu đã trả.
        </p>
        <p v-else-if="payrollRun.status === 'locked'" class="rounded-lg bg-slate-100 px-4 py-3 text-sm text-slate-700 ring-1 ring-slate-200">
            Kỳ lương đã khóa lúc {{ datetime(payrollRun.locked_at) }} — không thể tính lại hay sửa. Bút toán lương sẽ được gửi sang module Kế toán khi kỳ được duyệt.
        </p>
        <p v-else class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
            Thuế TNCN theo biểu lũy tiến 5 bậc (Luật 109/2025/QH15): giảm trừ bản thân 15,5 triệu/tháng, người phụ thuộc 6,2 triệu/người/tháng. Bảo hiểm NLĐ BHXH 8%, BHYT 1,5%, BHTN 1% trên lương hợp đồng, trần đóng BHXH/BHYT 50,6 triệu/tháng, BHTN 106,2 triệu/tháng (vùng I). Nghỉ không lương từ 14 ngày làm việc trở lên trong tháng thì tháng đó không đóng bảo hiểm.
        </p>

        <!-- Bảng lương chi tiết -->
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nhân viên</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Ngày công</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Lương cơ bản</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Phụ cấp</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Khấu trừ</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Bảo hiểm NLĐ</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Thuế TNCN</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Thực lĩnh</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Chi tiết</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template v-for="slip in payrollRun.payslips" :key="slip.id">
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-4 py-3 text-slate-700">
                                    {{ slip.employee?.full_name }} <span class="text-xs text-slate-400">({{ slip.employee?.code }})</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-slate-700" :title="slip.unpaid_leave_days > 0 ? `Nghỉ không lương/vắng mặt: ${slip.unpaid_leave_days} ngày` : ''">
                                    {{ formatDays(slip.worked_days) }}/{{ formatDays(slip.standard_working_days) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-slate-700">{{ money(slip.base_salary) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-slate-700">{{ money(slip.allowances) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-red-600">{{ money(slip.deductions) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-red-600">{{ money(slip.insurance_employee) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-red-600">{{ money(slip.tax) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-semibold text-slate-900">{{ money(slip.net_salary) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-center">
                                    <button @click="toggleLines(slip.id)" class="font-medium text-blue-600 hover:underline">
                                        {{ expanded.has(slip.id) ? 'Ẩn' : 'Xem' }}
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="expanded.has(slip.id)" class="bg-slate-50">
                                <td colspan="9" class="px-4 py-3">
                                    <ul class="grid grid-cols-1 gap-1 sm:grid-cols-2">
                                        <li class="flex justify-between text-sm">
                                            <span class="text-slate-600">Lương cơ bản</span>
                                            <span class="font-medium text-slate-900">{{ money(slip.base_salary) }}</span>
                                        </li>
                                        <li v-for="line in slip.lines" :key="line.id" class="flex justify-between text-sm">
                                            <span class="text-slate-600">{{ line.label }} <span class="text-xs text-slate-400">({{ line.component_code }})</span></span>
                                            <span :class="line.type === 'earning' ? 'font-medium text-green-700' : 'font-medium text-red-600'">
                                                {{ line.type === 'earning' ? '+' : '−' }}{{ money(line.amount) }}
                                            </span>
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!payrollRun.payslips?.length">
                            <td colspan="9" class="px-4 py-10 text-center text-sm text-slate-400">
                                Chưa có phiếu lương nào. Bấm “Tính lương” để tính cho toàn bộ nhân viên đang hoạt động.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat.js';
import { usePermission } from '../../../composables/usePermission.js';

const props = defineProps({
    payrollRun: { type: Object, required: true },
});

const { money, datetime } = useFormat();
const { can } = usePermission();

const expanded = reactive(new Set());

function toggleLines(id) {
    if (expanded.has(id)) expanded.delete(id);
    else expanded.add(id);
}

function formatDays(value) {
    if (value === null || value === undefined) return '—';
    const n = Number(value);
    return Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
}

function calculate() {
    router.post(route('hr.payroll-runs.calculate', props.payrollRun.id));
}

function calculateAsync() {
    router.post(route('hr.payroll-runs.calculate-async', props.payrollRun.id));
}

function lock() {
    if (confirm(`Khóa bảng lương tháng ${props.payrollRun.period}? Sau khi khóa sẽ không sửa được nữa.`)) {
        router.post(route('hr.payroll-runs.lock', props.payrollRun.id));
    }
}

function approve() {
    if (confirm(`Duyệt bảng lương tháng ${props.payrollRun.period}? Bút toán lương sẽ được gửi sang Kế toán.`)) {
        router.post(route('hr.payroll-runs.approve', props.payrollRun.id));
    }
}

function pay() {
    if (confirm(`Đánh dấu đã trả lương tháng ${props.payrollRun.period} cho nhân viên?`)) {
        router.post(route('hr.payroll-runs.pay', props.payrollRun.id));
    }
}
</script>
