<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold text-slate-800">{{ customer.name }}</h1>
            <Link
                v-if="can('sales.customer.manage')"
                :href="route('sales.customers.edit', customer.id)"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                Sửa khách hàng
            </Link>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Hạn mức công nợ</p>
                <p class="mt-1 text-lg font-bold text-slate-800">
                    {{ customer.credit_limit > 0 ? money(customer.credit_limit) : 'Không giới hạn' }}
                </p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Công nợ hiện tại</p>
                <p class="mt-1 text-lg font-bold text-amber-700">{{ money(customer.current_debt) }}</p>
                <p class="mt-1 text-xs text-slate-400">Tổng đơn đã xác nhận trở đi, chưa trừ thanh toán (xem Accounting).</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Hạn mức còn lại</p>
                <p v-if="customer.remaining_credit === null" class="mt-1 text-lg font-bold text-slate-400">—</p>
                <p v-else class="mt-1 text-lg font-bold" :class="customer.remaining_credit < 0 ? 'text-red-600' : 'text-green-700'">
                    {{ money(customer.remaining_credit) }}
                </p>
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Thông tin liên hệ</h2>
            <dl class="grid gap-2 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">Mã</dt><dd class="font-medium">{{ customer.code }}</dd></div>
                <div><dt class="text-slate-500">Mã số thuế</dt><dd>{{ customer.tax_code ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Điện thoại</dt><dd>{{ customer.phone ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Email</dt><dd>{{ customer.email ?? '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-slate-500">Địa chỉ</dt><dd>{{ customer.address ?? '—' }}</dd></div>
            </dl>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Đơn bán gần đây</h2>
            <DataTable
                :columns="[
                    { key: 'code', label: 'Mã đơn' },
                    { key: 'order_date', label: 'Ngày đặt' },
                    { key: 'status', label: 'Trạng thái' },
                    { key: 'total_amount', label: 'Tổng tiền' },
                ]"
                :items="customer.sales_orders ?? []"
                empty-text="Khách hàng chưa có đơn bán nào."
            >
                <template #cell-code="{ row }">
                    <Link :href="route('sales.orders.show', row.id)" class="font-medium text-blue-600 hover:underline">{{ row.code }}</Link>
                </template>
                <template #cell-order_date="{ row }">{{ date(row.order_date) }}</template>
                <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
                <template #cell-total_amount="{ row }">{{ money(row.total_amount) }}</template>
            </DataTable>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat';
import { usePermission } from '../../../composables/usePermission';

defineProps({ customer: { type: Object, required: true } });

const { money, date } = useFormat();
const { can } = usePermission();
</script>
