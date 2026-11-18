<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">{{ employee.full_name }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ employee.code }} · {{ employee.department?.name ?? '—' }} · {{ employee.position?.name ?? '—' }}</p>
            </div>
            <div class="flex gap-2">
                <Link
                    v-if="can('hr.employee.manage')"
                    :href="route('hr.employees.edit', employee.id)"
                    class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50"
                >
                    Sửa
                </Link>
                <Link :href="route('hr.employees.index')" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">
                    Về danh sách
                </Link>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-base font-semibold text-slate-900">Thông tin chung</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Email</dt><dd class="font-medium text-slate-900">{{ employee.email ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Điện thoại</dt><dd class="font-medium text-slate-900">{{ employee.phone ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Ngày vào làm</dt><dd class="font-medium text-slate-900">{{ date(employee.hire_date) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Lương cơ bản</dt><dd class="font-medium text-slate-900">{{ money(employee.base_salary) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Phép năm còn lại</dt><dd class="font-medium text-slate-900">{{ number(remainingLeaveDays) }} ngày</dd></div>
                </dl>
            </div>

            <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-base font-semibold text-slate-900">Hợp đồng</h2>
                <ul v-if="employee.contracts?.length" class="mt-3 space-y-2 text-sm">
                    <li v-for="c in employee.contracts" :key="c.id" class="flex items-center justify-between">
                        <span class="text-slate-700">{{ c.code }} <span class="text-xs text-slate-400">({{ date(c.start_date) }} – {{ date(c.end_date) }})</span></span>
                        <StatusBadge :status="c.status" />
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-slate-400">Chưa có hợp đồng nào.</p>
            </div>

            <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-base font-semibold text-slate-900">Đơn nghỉ phép gần đây</h2>
                <ul v-if="employee.leave_requests?.length" class="mt-3 space-y-2 text-sm">
                    <li v-for="lr in employee.leave_requests" :key="lr.id" class="flex items-center justify-between">
                        <span class="text-slate-700">{{ lr.code }} <span class="text-xs text-slate-400">({{ date(lr.start_date) }} – {{ date(lr.end_date) }}, {{ number(lr.days) }} ngày)</span></span>
                        <StatusBadge :status="lr.status" />
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-slate-400">Chưa có đơn nghỉ phép nào.</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat.js';
import { usePermission } from '../../../composables/usePermission.js';

defineProps({
    employee: { type: Object, required: true },
    remainingLeaveDays: { type: [Number, String], default: 0 },
});

const { money, number, date } = useFormat();
const { can } = usePermission();
</script>
