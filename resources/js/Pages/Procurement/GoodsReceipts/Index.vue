<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-slate-800">Phiếu nhận hàng</h1>
            <Link v-if="can('procurement.gr.create')" :href="route('procurement.goods-receipts.create')" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                + Tạo phiếu nhận hàng
            </Link>
        </div>
        <form @submit.prevent="search" class="flex flex-wrap gap-3">
            <select v-model="form.filter.status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                <option value="">— Tất cả trạng thái —</option>
                <option value="draft">Nháp</option>
                <option value="validated">Đã xác nhận</option>
                <option value="cancelled">Đã hủy</option>
            </select>
            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Lọc</button>
        </form>
        <DataTable :columns="columns" :items="receipts" empty-text="Chưa có phiếu nhận hàng nào.">
            <template #cell-supplier="{ row }">{{ row.supplier?.name ?? '—' }}</template>
            <template #cell-warehouse="{ row }">{{ row.warehouse?.name ?? '—' }}</template>
            <template #cell-purchase_order="{ row }">{{ row.purchase_order?.code ?? '—' }}</template>
            <template #cell-receipt_date="{ value }">{{ date(value) }}</template>
            <template #cell-status="{ value }"><StatusBadge :status="value" /></template>
            <template #actions="{ row }">
                <Link :href="route('procurement.goods-receipts.show', row.id)" class="text-sm font-medium text-blue-600 hover:underline">Chi tiết</Link>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat.js';
import { usePermission } from '../../../composables/usePermission.js';

const props = defineProps({
    receipts: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { date } = useFormat();
const { can } = usePermission();

const columns = [
    { key: 'code', label: 'Mã phiếu', sortable: true },
    { key: 'supplier', label: 'Nhà cung cấp' },
    { key: 'warehouse', label: 'Kho nhận' },
    { key: 'purchase_order', label: 'Đơn mua' },
    { key: 'receipt_date', label: 'Ngày nhận', sortable: true },
    { key: 'status', label: 'Trạng thái' },
];

const form = reactive({ filter: { status: props.filters.filter?.status ?? '' } });

function search() {
    router.get(route('procurement.goods-receipts.index'), form, { preserveState: true });
}
</script>
