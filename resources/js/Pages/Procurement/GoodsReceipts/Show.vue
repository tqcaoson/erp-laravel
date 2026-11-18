<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800">Phiếu nhận hàng {{ receipt.code }}</h1>
                <p class="text-sm text-slate-500">
                    Nhà cung cấp: {{ receipt.supplier?.name }} — Kho: {{ receipt.warehouse?.name }} —
                    Ngày nhận: {{ date(receipt.receipt_date) }} — <StatusBadge :status="receipt.status" />
                </p>
                <p v-if="receipt.purchase_order" class="mt-1 text-sm text-slate-600">
                    Theo đơn mua
                    <Link :href="route('procurement.purchase-orders.show', receipt.purchase_order.id)" class="text-blue-600 hover:underline">{{ receipt.purchase_order.code }}</Link>
                </p>
            </div>
            <div class="flex gap-2">
                <button v-if="receipt.status === 'draft'" @click="validateReceipt" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                    Xác nhận nhận hàng
                </button>
                <button v-if="receipt.status === 'draft'" @click="cancel" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Hủy phiếu
                </button>
            </div>
        </div>
        <DataTable :columns="columns" :items="receipt.lines" empty-text="Phiếu này chưa có dòng nào.">
            <template #cell-product="{ row }">{{ row.product?.sku }} — {{ row.product?.name }}</template>
            <template #cell-qty="{ value }">{{ number(value, 2) }}</template>
            <template #cell-unit_cost="{ value }">{{ money(value) }}</template>
            <template #cell-line_total="{ row }">{{ money(Number(row.qty) * Number(row.unit_cost)) }}</template>
        </DataTable>
        <p class="text-xs text-slate-500">Xác nhận phiếu sẽ đối chiếu số lượng với đơn mua, cập nhật đơn mua và tăng tồn kho (kèm tính lại giá vốn bình quân).</p>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat.js';

const props = defineProps({ receipt: { type: Object, required: true } });
const { money, number, date } = useFormat();

const columns = [
    { key: 'product', label: 'Sản phẩm' },
    { key: 'qty', label: 'Số lượng nhận' },
    { key: 'unit_cost', label: 'Giá nhập' },
    { key: 'line_total', label: 'Thành tiền' },
];

function validateReceipt() {
    if (confirm('Xác nhận phiếu nhận hàng này? Tồn kho sẽ tăng và đơn mua sẽ được cập nhật.')) {
        router.post(route('procurement.goods-receipts.validate', props.receipt.id));
    }
}

function cancel() {
    if (confirm('Hủy phiếu nhận hàng này?')) {
        router.post(route('procurement.goods-receipts.cancel', props.receipt.id));
    }
}
</script>
