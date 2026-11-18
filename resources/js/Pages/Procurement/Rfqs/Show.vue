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
                <button v-if="rfq.status === 'draft'" @click="changeStatus('sent')" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Gửi cho nhà cung cấp</button>
                <button v-if="rfq.status === 'sent'" @click="changeStatus('received')" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Đã nhận báo giá</button>
                <button v-if="rfq.status === 'received'" @click="changeStatus('accepted')" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">Chấp nhận</button>
                <button v-if="['sent', 'received'].includes(rfq.status)" @click="changeStatus('rejected')" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Từ chối</button>
                <button v-if="rfq.status === 'draft'" @click="remove" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Xóa</button>
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
import { router } from '@inertiajs/vue3';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat.js';

const props = defineProps({ rfq: { type: Object, required: true } });
const { money, number, date } = useFormat();

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
