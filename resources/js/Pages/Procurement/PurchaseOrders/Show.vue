<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800">Đơn mua {{ order.code }}</h1>
                <p class="text-sm text-slate-500">
                    Nhà cung cấp: {{ order.supplier?.name }} — Ngày đặt: {{ date(order.order_date) }} —
                    <StatusBadge :status="order.status" />
                </p>
                <p v-if="order.note" class="mt-1 text-sm text-slate-600">Ghi chú: {{ order.note }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button v-if="order.status === 'draft'" @click="postAction('submit')" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">Trình duyệt</button>
                <button v-if="['draft', 'pending_approval'].includes(order.status) && can('procurement.po.approve')" @click="postAction('approve')" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">Duyệt</button>
                <Link v-if="['approved', 'partially_received'].includes(order.status)" :href="route('procurement.goods-receipts.create', { purchase_order_id: order.id })" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Tạo phiếu nhận hàng
                </Link>
                <button v-if="order.status === 'received' && can('procurement.po.approve')" @click="postAction('close')" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Đóng đơn</button>
                <button v-if="['draft', 'pending_approval', 'approved'].includes(order.status)" @click="cancel" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Hủy đơn</button>
            </div>
        </div>
        <DataTable :columns="columns" :items="order.lines" empty-text="Đơn mua này chưa có dòng nào.">
            <template #cell-product="{ row }">{{ row.product?.sku }} — {{ row.product?.name }}</template>
            <template #cell-qty="{ value }">{{ number(value, 2) }}</template>
            <template #cell-received_qty="{ value }">{{ number(value, 2) }}</template>
            <template #cell-unit_price="{ value }">{{ money(value) }}</template>
            <template #cell-line_total="{ value }">{{ money(value) }}</template>
        </DataTable>
        <div class="space-y-1 text-right text-sm text-slate-700">
            <p>Tổng chưa thuế: {{ money(order.subtotal) }}</p>
            <p>Thuế: {{ money(order.tax_amount) }}</p>
            <p class="font-semibold text-slate-900">Tổng cộng: {{ money(order.total_amount) }}</p>
        </div>
        <div v-if="order.goods_receipts?.length" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-2 text-sm font-semibold text-slate-800">Phiếu nhận hàng liên quan</h2>
            <ul class="space-y-1 text-sm">
                <li v-for="gr in order.goods_receipts" :key="gr.id">
                    <Link :href="route('procurement.goods-receipts.show', gr.id)" class="text-blue-600 hover:underline">{{ gr.code }}</Link>
                    — {{ date(gr.receipt_date) }} — <StatusBadge :status="gr.status" />
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat.js';
import { usePermission } from '../../../composables/usePermission.js';

const props = defineProps({ order: { type: Object, required: true } });
const { money, number, date } = useFormat();
const { can } = usePermission();

const columns = [
    { key: 'product', label: 'Sản phẩm' },
    { key: 'qty', label: 'Số lượng đặt' },
    { key: 'received_qty', label: 'Đã nhận' },
    { key: 'unit_price', label: 'Đơn giá' },
    { key: 'line_total', label: 'Thành tiền' },
];

function postAction(action) {
    router.post(route(`procurement.purchase-orders.${action}`, props.order.id));
}

function cancel() {
    if (confirm('Hủy đơn mua hàng này? Chỉ hủy được khi chưa nhận hàng.')) {
        postAction('cancel');
    }
}
</script>
