<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800">Yêu cầu mua {{ purchaseRequest.code }}</h1>
                <p class="text-sm text-slate-500">
                    Người yêu cầu: {{ purchaseRequest.requester?.name ?? 'Hệ thống tự động' }} —
                    <StatusBadge :status="purchaseRequest.status" />
                </p>
                <p v-if="purchaseRequest.note" class="mt-1 text-sm text-slate-600">Ghi chú: {{ purchaseRequest.note }}</p>
            </div>
            <div class="flex gap-2">
                <button v-if="['draft', 'pending_approval'].includes(purchaseRequest.status) && can('procurement.pr.approve')" @click="approve" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">Duyệt</button>
                <button v-if="['draft', 'pending_approval'].includes(purchaseRequest.status) && can('procurement.pr.approve')" @click="reject" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Từ chối</button>
                <button v-if="['draft', 'pending_approval', 'approved'].includes(purchaseRequest.status)" @click="cancel" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Hủy</button>
                <Link v-if="purchaseRequest.status === 'approved'" :href="route('procurement.purchase-orders.create', { purchase_request_id: purchaseRequest.id })" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Tạo đơn mua từ yêu cầu này
                </Link>
            </div>
        </div>
        <DataTable :columns="columns" :items="purchaseRequest.lines" empty-text="Yêu cầu này chưa có dòng nào.">
            <template #cell-product="{ row }">{{ row.product?.sku }} — {{ row.product?.name }}</template>
            <template #cell-warehouse="{ row }">{{ row.warehouse?.name ?? '—' }}</template>
            <template #cell-qty="{ value }">{{ number(value, 2) }}</template>
            <template #cell-unit_price="{ value }">{{ money(value) }}</template>
            <template #cell-line_total="{ row }">{{ money(Number(row.qty) * Number(row.unit_price ?? 0)) }}</template>
        </DataTable>
        <p class="text-right text-sm font-semibold text-slate-800">Tổng ước tính: {{ money(purchaseRequest.total_amount) }}</p>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat.js';
import { usePermission } from '../../../composables/usePermission.js';

const props = defineProps({ purchaseRequest: { type: Object, required: true } });
const { money, number } = useFormat();
const { can } = usePermission();

const columns = [
    { key: 'product', label: 'Sản phẩm' },
    { key: 'warehouse', label: 'Kho cần hàng' },
    { key: 'qty', label: 'Số lượng' },
    { key: 'unit_price', label: 'Đơn giá ước tính' },
    { key: 'line_total', label: 'Thành tiền' },
];

function approve() {
    router.post(route('procurement.purchase-requests.approve', props.purchaseRequest.id));
}

function reject() {
    const note = prompt('Lý do từ chối (tùy chọn):');
    router.post(route('procurement.purchase-requests.reject', props.purchaseRequest.id), { note });
}

function cancel() {
    if (confirm('Hủy yêu cầu mua hàng này?')) {
        router.post(route('procurement.purchase-requests.cancel', props.purchaseRequest.id));
    }
}
</script>
