<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-800">Đơn bán {{ order.code }}</h1>
                <p class="text-sm text-slate-500">
                    {{ order.customer?.name }} · {{ date(order.order_date) }} · Kho: {{ order.warehouse_name }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <StatusBadge :status="order.status" />
                <Link
                    v-if="order.status === 'draft' && can('sales.order.create')"
                    :href="route('sales.orders.edit', order.id)"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    Sửa
                </Link>
                <button
                    v-if="order.status === 'draft' && can('sales.order.confirm')"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    @click="postAction('confirm')"
                >
                    Xác nhận
                </button>
                <button
                    v-if="order.status === 'pending_approval' && can('sales.order.approve')"
                    class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                    @click="postAction('approve')"
                >
                    Duyệt đơn
                </button>
                <button
                    v-if="['confirmed', 'partially_delivered'].includes(order.status) && hasRemaining && can('sales.delivery.validate')"
                    class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700"
                    @click="postAction('deliver-remaining')"
                >
                    Giao phần còn lại
                </button>
                <button
                    v-if="['partially_delivered', 'delivered', 'invoiced'].includes(order.status) && hasUninvoiced && can('accounting.invoice.create')"
                    class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700"
                    @click="postAction('create-invoice')"
                >
                    Lập hóa đơn
                </button>
                <button
                    v-if="['draft', 'pending_approval', 'confirmed'].includes(order.status) && can('sales.order.confirm')"
                    class="rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50"
                    @click="cancelOrder"
                >
                    Hủy đơn
                </button>
            </div>
        </div>

        <div v-if="warnings.length" class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
            <p class="font-medium">Cảnh báo tồn kho:</p>
            <ul class="mt-1 list-disc pl-5">
                <li v-for="(w, i) in warnings" :key="i">{{ w }}</li>
            </ul>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Công nợ khách hàng</p>
                <p class="mt-1 text-lg font-bold text-amber-700">{{ money(order.customer?.current_debt) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Hạn mức còn lại</p>
                <p v-if="order.customer?.remaining_credit === null" class="mt-1 text-lg font-bold text-slate-400">Không giới hạn</p>
                <p v-else class="mt-1 text-lg font-bold" :class="order.customer.remaining_credit < 0 ? 'text-red-600' : 'text-green-700'">
                    {{ money(order.customer.remaining_credit) }}
                </p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Tổng tiền đơn</p>
                <p class="mt-1 text-lg font-bold text-slate-800">{{ money(order.total_amount) }}</p>
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Dòng sản phẩm</h2>
            <DataTable
                :columns="[
                    { key: 'product_name', label: 'Sản phẩm' },
                    { key: 'qty', label: 'Số lượng' },
                    { key: 'delivered_qty', label: 'Đã giao' },
                    { key: 'unit_price', label: 'Đơn giá' },
                    { key: 'line_total', label: 'Thành tiền' },
                ]"
                :items="order.lines ?? []"
                empty-text="Đơn chưa có dòng nào."
            >
                <template #cell-product_name="{ row }">
                    {{ row.product_name }}
                    <span v-if="row.product_sku" class="text-xs text-slate-400">({{ row.product_sku }})</span>
                </template>
                <template #cell-qty="{ row }">{{ number(row.qty, 2) }}</template>
                <template #cell-delivered_qty="{ row }">{{ number(row.delivered_qty, 2) }}</template>
                <template #cell-unit_price="{ row }">{{ money(row.unit_price) }}</template>
                <template #cell-line_total="{ row }">{{ money(row.line_total) }}</template>
            </DataTable>
            <dl class="ml-auto mt-3 w-64 space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Tạm tính</dt><dd>{{ money(order.subtotal) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Thuế</dt><dd>{{ money(order.tax_amount) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Chiết khấu</dt><dd>− {{ money(order.discount_amount) }}</dd></div>
                <div class="flex justify-between border-t border-slate-200 pt-1 font-bold"><dt>Tổng cộng</dt><dd>{{ money(order.total_amount) }}</dd></div>
            </dl>
        </div>

        <div v-if="order.delivery_orders?.length" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Phiếu giao hàng</h2>
            <DataTable
                :columns="[
                    { key: 'code', label: 'Mã phiếu' },
                    { key: 'delivery_date', label: 'Ngày giao' },
                    { key: 'status', label: 'Trạng thái' },
                ]"
                :items="order.delivery_orders"
            >
                <template #cell-code="{ row }">
                    <Link :href="route('sales.deliveries.show', row.id)" class="font-medium text-blue-600 hover:underline">{{ row.code }}</Link>
                </template>
                <template #cell-delivery_date="{ row }">{{ date(row.delivery_date) }}</template>
                <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
            </DataTable>
        </div>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat';
import { usePermission } from '../../../composables/usePermission';

const props = defineProps({
    order: { type: Object, required: true },
    warnings: { type: Array, default: () => [] },
});

const { money, date, number } = useFormat();
const { can } = usePermission();

// Còn thiếu ở ít nhất 1 dòng → hiện nút "Giao phần còn lại".
const hasRemaining = computed(() =>
    (props.order.lines ?? []).some((line) => Number(line.qty) - Number(line.delivered_qty) > 0),
);

// Còn phần đã giao chưa lập hóa đơn → hiện nút "Lập hóa đơn".
const hasUninvoiced = computed(() =>
    (props.order.lines ?? []).some((line) => Number(line.delivered_qty) - Number(line.invoiced_qty ?? 0) > 0),
);

function postAction(action) {
    router.post(route(`sales.orders.${action}`, props.order.id), {}, { preserveScroll: true });
}

function cancelOrder() {
    if (confirm('Bạn có chắc muốn hủy đơn bán này?')) {
        postAction('cancel');
    }
}
</script>
