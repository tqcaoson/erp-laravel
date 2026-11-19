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
                <button v-if="receipt.status === 'draft' && can('procurement.gr.validate')" @click="validateReceipt" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                    Xác nhận nhận hàng
                </button>
                <button v-if="receipt.status === 'validated' && hasUninvoiced && can('accounting.invoice.create')" @click="createInvoice" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700">
                    Lập hóa đơn mua
                </button>
                <button v-if="receipt.status === 'draft'" @click="cancel" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Hủy phiếu
                </button>
            </div>
        </div>
        <DataTable :columns="columns" :items="receipt.lines" empty-text="Phiếu này chưa có dòng nào.">
            <template #cell-product="{ row }">{{ row.product?.sku }} — {{ row.product?.name }}</template>
            <template #cell-qty="{ value }">{{ number(value, 2) }}</template>
            <template #cell-ordered_qty="{ row }">{{ row.purchase_order_line ? number(row.purchase_order_line.qty, 2) : '—' }}</template>
            <template #cell-remaining_qty="{ row }">{{ remainingAfter(row) === null ? '—' : number(remainingAfter(row), 2) }}</template>
            <template #cell-unit_cost="{ value }">{{ money(value) }}</template>
            <template #cell-line_total="{ row }">{{ money(Number(row.qty) * Number(row.unit_cost)) }}</template>
        </DataTable>
        <p class="text-xs text-slate-500">Xác nhận phiếu sẽ đối chiếu số lượng với đơn mua, cập nhật đơn mua và tăng tồn kho (kèm tính lại giá vốn bình quân).</p>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat.js';
import { usePermission } from '../../../composables/usePermission.js';

const props = defineProps({ receipt: { type: Object, required: true } });
const { money, number, date } = useFormat();
const { can } = usePermission();

const columns = [
    { key: 'product', label: 'Sản phẩm' },
    { key: 'qty', label: 'Số lượng nhận' },
    { key: 'ordered_qty', label: 'Số lượng đặt' },
    { key: 'remaining_qty', label: 'Còn thiếu sau phiếu này' },
    { key: 'unit_cost', label: 'Giá nhập' },
    { key: 'line_total', label: 'Thành tiền' },
];

// Còn phần đã nhận chưa lập hóa đơn → hiện nút "Lập hóa đơn mua".
const hasUninvoiced = computed(() =>
    (props.receipt.lines ?? []).some((line) => Number(line.qty) - Number(line.invoiced_qty ?? 0) > 0),
);

/**
 * Số lượng còn thiếu của dòng đơn mua sau phiếu này. Phiếu đã xác nhận
 * thì received_qty đã gồm phiếu này; phiếu nháp thì cộng thêm số lượng
 * của dòng này vào phần đã nhận.
 */
function remainingAfter(line) {
    const poLine = line.purchase_order_line;
    if (!poLine) return null;
    const received = Number(poLine.received_qty || 0) + (props.receipt.status === 'draft' ? Number(line.qty || 0) : 0);
    return Number(poLine.qty || 0) - received;
}

function validateReceipt() {
    if (confirm('Xác nhận phiếu nhận hàng này? Tồn kho sẽ tăng và đơn mua sẽ được cập nhật.')) {
        router.post(route('procurement.goods-receipts.validate', props.receipt.id));
    }
}

function createInvoice() {
    if (confirm('Lập hóa đơn mua từ phần đã nhận chưa lập hóa đơn của phiếu này?')) {
        router.post(route('procurement.goods-receipts.create-invoice', props.receipt.id));
    }
}

function cancel() {
    if (confirm('Hủy phiếu nhận hàng này?')) {
        router.post(route('procurement.goods-receipts.cancel', props.receipt.id));
    }
}
</script>
