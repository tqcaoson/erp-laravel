<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800">Yêu cầu báo giá {{ rfq.code }}</h1>
                <p class="text-sm text-slate-500">
                    Nhà cung cấp: {{ rfq.supplier?.name }} — Hạn báo giá: {{ date(rfq.deadline) }} —
                    <StatusBadge :status="rfq.status" />
                </p>
                <p v-if="rfq.note" class="mt-1 text-sm text-slate-600">Ghi chú: {{ rfq.note }}</p>
            </div>
            <div class="flex gap-2">
                <button v-if="rfq.status === 'draft' && can('procurement.rfq.manage')" @click="changeStatus('sent')" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Gửi cho nhà cung cấp</button>
                <button v-if="rfq.status === 'sent' && can('procurement.rfq.manage')" @click="changeStatus('received')" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Đã nhận báo giá</button>
                <button v-if="rfq.status === 'received' && can('procurement.rfq.manage')" @click="changeStatus('accepted')" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">Chấp nhận</button>
                <button v-if="['sent', 'received'].includes(rfq.status) && can('procurement.rfq.manage')" @click="changeStatus('rejected')" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Từ chối</button>
                <button v-if="rfq.status === 'draft' && can('procurement.rfq.manage')" @click="remove" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Xóa</button>
                <Link v-if="rfq.status === 'accepted' && can('procurement.po.create')" :href="route('procurement.purchase-orders.create', { rfq_id: rfq.id })" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">Tạo đơn mua</Link>
            </div>
        </div>
        <DataTable :columns="columns" :items="rfq.lines" empty-text="Báo giá này chưa có dòng nào.">
            <template #cell-product="{ row }">{{ row.product?.sku }} — {{ row.product?.name }}</template>
            <template #cell-qty="{ value }">{{ number(value, 2) }}</template>
            <template #cell-quoted_price="{ value }">{{ value === null ? '—' : money(value) }}</template>
        </DataTable>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat.js';
import { usePermission } from '../../../composables/usePermission.js';

const props = defineProps({ rfq: { type: Object, required: true } });
const { money, number, date } = useFormat();
const { can } = usePermission();

const columns = [
    { key: 'product', label: 'Sản phẩm' },
    { key: 'qty', label: 'Số lượng' },
    { key: 'quoted_price', label: 'Giá chào' },
];

function changeStatus(status) {
    router.post(route('procurement.rfqs.status', props.rfq.id), { status });
}

function remove() {
    if (confirm('Xóa yêu cầu báo giá này?')) {
        router.delete(route('procurement.rfqs.destroy', props.rfq.id));
    }
}
</script>
