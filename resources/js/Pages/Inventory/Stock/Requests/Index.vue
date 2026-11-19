<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Duyệt phiếu kho</h1>
            <p class="mt-1 text-sm text-slate-500">
                Phiếu nhập/xuất/chuyển kho tạo tay chỉ đổi tồn kho sau khi được duyệt ở đây.
                Từ chối thì tồn kho giữ nguyên.
            </p>
        </div>

        <div class="space-y-4">
            <h2 class="text-base font-semibold text-slate-800">Phiếu chờ duyệt</h2>
            <DataTable :columns="columns" :items="pendingRequests" empty-text="Không có phiếu nào chờ duyệt.">
                <template #cell-type="{ row }">{{ typeLabels[row.type] ?? row.type }}</template>
                <template #cell-product="{ row }">{{ row.product?.name ?? '—' }} <span class="text-xs text-slate-400">({{ row.product?.sku }})</span></template>
                <template #cell-warehouse="{ row }">
                    {{ row.warehouse?.name ?? '—' }}
                    <span v-if="row.type === 'transfer'">→ {{ row.to_warehouse?.name ?? '—' }}</span>
                </template>
                <template #cell-qty="{ row }">{{ number(row.qty, 2) }}</template>
                <template #cell-requester="{ row }">{{ row.requester?.name ?? '—' }}</template>
                <template #cell-created_at="{ row }">{{ date(row.created_at) }}</template>
                <template #actions="{ row }">
                    <div class="flex gap-2">
                        <button class="rounded-lg bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-700" @click="approve(row)">
                            Duyệt
                        </button>
                        <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50" @click="reject(row)">
                            Từ chối
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>

        <div class="space-y-4">
            <h2 class="text-base font-semibold text-slate-800">Lịch sử đã xử lý</h2>
            <DataTable :columns="historyColumns" :items="historyRequests" empty-text="Chưa có phiếu nào được duyệt hoặc từ chối.">
                <template #cell-type="{ row }">{{ typeLabels[row.type] ?? row.type }}</template>
                <template #cell-product="{ row }">{{ row.product?.name ?? '—' }} <span class="text-xs text-slate-400">({{ row.product?.sku }})</span></template>
                <template #cell-warehouse="{ row }">
                    {{ row.warehouse?.name ?? '—' }}
                    <span v-if="row.type === 'transfer'">→ {{ row.to_warehouse?.name ?? '—' }}</span>
                </template>
                <template #cell-qty="{ row }">{{ number(row.qty, 2) }}</template>
                <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
                <template #cell-approver="{ row }">{{ row.approver?.name ?? '—' }}</template>
                <template #cell-updated_at="{ row }">{{ date(row.updated_at) }}</template>
            </DataTable>
        </div>
    </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import DataTable from '../../../../Components/DataTable.vue';
import StatusBadge from '../../../../Components/StatusBadge.vue';
import { useFormat } from '../../../../composables/useFormat.js';

defineProps({
    pendingRequests: { type: Object, required: true },
    historyRequests: { type: Object, required: true },
    statusLabels: { type: Object, default: () => ({}) },
    typeLabels: { type: Object, default: () => ({}) },
});

const { number, date } = useFormat();

const columns = [
    { key: 'id', label: 'Phiếu #' },
    { key: 'type', label: 'Loại phiếu' },
    { key: 'product', label: 'Sản phẩm' },
    { key: 'warehouse', label: 'Kho' },
    { key: 'qty', label: 'Số lượng' },
    { key: 'requester', label: 'Người tạo' },
    { key: 'created_at', label: 'Ngày tạo' },
];

const historyColumns = [
    { key: 'id', label: 'Phiếu #' },
    { key: 'type', label: 'Loại phiếu' },
    { key: 'product', label: 'Sản phẩm' },
    { key: 'warehouse', label: 'Kho' },
    { key: 'qty', label: 'Số lượng' },
    { key: 'status', label: 'Trạng thái' },
    { key: 'approver', label: 'Người xử lý' },
    { key: 'updated_at', label: 'Ngày xử lý' },
];

function approve(row) {
    if (confirm('Duyệt phiếu này? Tồn kho sẽ thay đổi theo phiếu.')) {
        router.post(route('inventory.stock-requests.approve', row.id), {}, { preserveScroll: true });
    }
}

function reject(row) {
    if (confirm('Từ chối phiếu này? Tồn kho sẽ không thay đổi.')) {
        router.post(route('inventory.stock-requests.reject', row.id), {}, { preserveScroll: true });
    }
}
</script>
