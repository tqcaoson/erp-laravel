<template>
    <div class="mx-auto max-w-2xl space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Đơn nghỉ {{ leaveRequest.code }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ leaveRequest.employee?.full_name }} ({{ leaveRequest.employee?.code }})</p>
            </div>
            <StatusBadge :status="leaveRequest.status" />
        </div>

        <div class="space-y-3 rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Loại nghỉ</span>
                <span class="font-medium text-slate-900">{{ leaveTypeLabel(leaveRequest.leave_type) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Từ ngày</span>
                <span class="font-medium text-slate-900">{{ date(leaveRequest.start_date) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Đến ngày</span>
                <span class="font-medium text-slate-900">{{ date(leaveRequest.end_date) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Số ngày</span>
                <span class="font-medium text-slate-900">{{ leaveRequest.days }} ngày</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Lý do</span>
                <span class="max-w-md text-right font-medium text-slate-900">{{ leaveRequest.reason || '—' }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Người duyệt</span>
                <span class="font-medium text-slate-900">{{ leaveRequest.approver?.name ?? '—' }}</span>
            </div>
        </div>

        <Link :href="route('my.leave-requests.index')" class="inline-block rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">
            Về danh sách
        </Link>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat.js';

defineProps({
    leaveRequest: { type: Object, required: true },
});

const { date } = useFormat();

function leaveTypeLabel(type) {
    return { annual: 'Phép năm', sick: 'Nghỉ ốm', unpaid: 'Nghỉ không lương' }[type] ?? type;
}
</script>
