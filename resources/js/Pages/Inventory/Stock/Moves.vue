<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-slate-800">Sổ kho</h1>
            <Link :href="route('inventory.stock.index')" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Về tồn kho</Link>
        </div>

        <form @submit.prevent="search" class="flex flex-wrap gap-3">
            <select v-model="form.warehouse_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                <option value="">— Tất cả kho —</option>
                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
            <select v-model="form.product_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                <option value="">— Tất cả sản phẩm —</option>
                <option v-for="p in products" :key="p.id" :value="p.id">{{ p.sku }} — {{ p.name }}</option>
            </select>
            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Lọc</button>
        </form>

        <DataTable :columns="columns" :items="moves" empty-text="Chưa có phiếu nhập/xuất nào.">
            <template #cell-moved_at="{ value }">{{ datetime(value) }}</template>
            <template #cell-product="{ row }">{{ row.product?.sku }} — {{ row.product?.name }}</template>
            <template #cell-warehouse="{ row }">{{ row.warehouse?.name ?? '—' }}</template>
            <template #cell-qty="{ value }">
                <span :class="Number(value) >= 0 ? 'font-medium text-green-700' : 'font-medium text-red-700'">
                    {{ Number(value) >= 0 ? '+' : '' }}{{ number(value, 2) }}
                </span>
            </template>
            <template #cell-unit_cost="{ value }">{{ money(value) }}</template>
            <template #cell-source_type="{ value }">{{ sourceLabel(value) }}</template>
        </DataTable>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import DataTable from '../../../Components/DataTable.vue';
import { useFormat } from '../../../composables/useFormat.js';

const props = defineProps({
    moves: { type: Object, required: true },
    warehouses: { type: Array, required: true },
    products: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { money, number, datetime } = useFormat();

const columns = [
    { key: 'moved_at', label: 'Thời gian', sortable: true },
    { key: 'product', label: 'Sản phẩm' },
    { key: 'warehouse', label: 'Kho' },
    { key: 'qty', label: 'Số lượng', sortable: true },
    { key: 'unit_cost', label: 'Đơn giá vốn' },
    { key: 'source_type', label: 'Nguồn' },
];

const form = reactive({
    warehouse_id: props.filters.warehouse_id ?? '',
    product_id: props.filters.product_id ?? '',
});

function search() {
    router.get(route('inventory.stock.moves'), form, { preserveState: true });
}

function sourceLabel(sourceType) {
    if (sourceType === 'manual') return 'Nhập/xuất tay';
    if (sourceType?.includes('GoodsReceipt')) return 'Phiếu nhận hàng';
    if (sourceType?.includes('DeliveryOrder')) return 'Phiếu giao hàng';
    if (sourceType?.includes('StockCount')) return 'Kiểm kê';
    return sourceType ?? '—';
}
</script>
