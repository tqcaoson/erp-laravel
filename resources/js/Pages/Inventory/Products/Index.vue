<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-slate-800">Sản phẩm</h1>
            <Link v-if="can('inventory.product.manage')" :href="route('inventory.products.create')" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                + Thêm sản phẩm
            </Link>
        </div>

        <form @submit.prevent="search" class="flex flex-wrap gap-3">
            <input v-model="form.search" type="text" placeholder="Tìm theo mã hoặc tên sản phẩm…" class="w-72 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
            <select v-model="form.filter.is_active" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                <option value="">— Tất cả trạng thái —</option>
                <option value="1">Đang hoạt động</option>
                <option value="0">Ngừng hoạt động</option>
            </select>
            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Tìm kiếm</button>
        </form>

        <DataTable :columns="columns" :items="products" empty-text="Chưa có sản phẩm nào.">
            <template #cell-sale_price="{ value }">{{ money(value) }}</template>
            <template #cell-average_cost="{ value }">{{ money(value) }}</template>
            <template #cell-is_active="{ value }">
                <StatusBadge :status="value ? 'active' : 'inactive'" />
            </template>
            <template #actions="{ row }">
                <Link v-if="can('inventory.product.manage')" :href="route('inventory.products.edit', row.id)" class="mr-3 text-sm font-medium text-blue-600 hover:underline">Sửa</Link>
                <button v-if="can('inventory.product.manage')" @click="remove(row)" class="text-sm font-medium text-red-600 hover:underline">Xóa</button>
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
    products: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { money } = useFormat();
const { can } = usePermission();

const columns = [
    { key: 'sku', label: 'Mã (SKU)', sortable: true },
    { key: 'name', label: 'Tên sản phẩm', sortable: true },
    { key: 'sale_price', label: 'Giá bán', sortable: true },
    { key: 'average_cost', label: 'Giá vốn bình quân' },
    { key: 'reorder_min', label: 'Tồn tối thiểu' },
    { key: 'is_active', label: 'Trạng thái' },
];

const form = reactive({
    search: props.filters.search ?? '',
    filter: { is_active: props.filters.filter?.is_active ?? '' },
});

function search() {
    router.get(route('inventory.products.index'), form, { preserveState: true });
}

function remove(row) {
    if (confirm(`Xóa sản phẩm ${row.name}?`)) {
        router.delete(route('inventory.products.destroy', row.id));
    }
}
</script>
