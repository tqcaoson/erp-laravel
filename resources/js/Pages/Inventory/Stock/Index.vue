<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-slate-800">Tồn kho</h1>
            <div class="flex gap-2">
                <Link :href="route('inventory.stock.moves')" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Sổ kho</Link>
                <Link v-if="can('inventory.stock.move')" :href="route('inventory.stock.move-form')" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    + Phiếu nhập / xuất / chuyển kho
                </Link>
            </div>
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
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input v-model="form.low_stock" type="checkbox" true-value="1" false-value="" class="h-4 w-4 rounded border-slate-300 text-blue-600" />
                Chỉ hiện hàng dưới mức tối thiểu
            </label>
            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Lọc</button>
        </form>

        <DataTable :columns="columns" :items="quants" empty-text="Chưa có tồn kho.">
            <template #cell-product="{ row }">{{ row.product?.sku }} — {{ row.product?.name }}</template>
            <template #cell-warehouse="{ row }">{{ row.warehouse?.name ?? '—' }}</template>
            <template #cell-location="{ row }">{{ row.location?.name ?? '—' }}</template>
            <template #cell-qty="{ value }">{{ number(value, 2) }}</template>
            <template #cell-available_qty="{ value }">{{ number(value, 2) }}</template>
            <template #cell-status="{ row }">
                <span v-if="row.is_below_minimum" class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 ring-1 ring-inset ring-red-200">
                    Dưới mức tối thiểu
                </span>
                <span v-else class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 ring-1 ring-inset ring-green-200">
                    Bình thường
                </span>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import DataTable from '../../../Components/DataTable.vue';
import { useFormat } from '../../../composables/useFormat.js';
import { usePermission } from '../../../composables/usePermission.js';

const props = defineProps({
    quants: { type: Object, required: true },
    warehouses: { type: Array, required: true },
    products: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { number } = useFormat();
const { can } = usePermission();

const columns = [
    { key: 'product', label: 'Sản phẩm' },
    { key: 'warehouse', label: 'Kho' },
    { key: 'location', label: 'Vị trí' },
    { key: 'qty', label: 'Tồn kho', sortable: true },
    { key: 'reserved_qty', label: 'Đã giữ' },
    { key: 'available_qty', label: 'Khả dụng' },
    { key: 'status', label: 'Cảnh báo' },
];

const form = reactive({
    warehouse_id: props.filters.warehouse_id ?? '',
    product_id: props.filters.product_id ?? '',
    low_stock: props.filters.low_stock ? '1' : '',
});

function search() {
    router.get(route('inventory.stock.index'), form, { preserveState: true });
}
</script>
