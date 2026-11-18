<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-slate-800">Kho hàng</h1>
            <Link v-if="can('inventory.warehouse.manage')" :href="route('inventory.warehouses.create')" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                + Thêm kho
            </Link>
        </div>
        <form @submit.prevent="search" class="flex flex-wrap gap-3">
            <input v-model="form.search" type="text" placeholder="Tìm theo mã hoặc tên kho…" class="w-72 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Tìm kiếm</button>
        </form>
        <DataTable :columns="columns" :items="warehouses" empty-text="Chưa có kho nào.">
            <template #cell-is_active="{ value }"><StatusBadge :status="value ? 'active' : 'inactive'" /></template>
            <template #actions="{ row }">
                <Link v-if="can('inventory.warehouse.manage')" :href="route('inventory.warehouses.edit', row.id)" class="mr-3 text-sm font-medium text-blue-600 hover:underline">Sửa</Link>
                <button v-if="can('inventory.warehouse.manage')" @click="remove(row)" class="text-sm font-medium text-red-600 hover:underline">Xóa</button>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { usePermission } from '../../../composables/usePermission.js';

const props = defineProps({
    warehouses: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { can } = usePermission();

const columns = [
    { key: 'code', label: 'Mã kho', sortable: true },
    { key: 'name', label: 'Tên kho', sortable: true },
    { key: 'address', label: 'Địa chỉ' },
    { key: 'locations_count', label: 'Số vị trí' },
    { key: 'is_active', label: 'Trạng thái' },
];

const form = reactive({ search: props.filters.search ?? '' });

function search() {
    router.get(route('inventory.warehouses.index'), form, { preserveState: true });
}

function remove(row) {
    if (confirm(`Xóa kho ${row.name}?`)) {
        router.delete(route('inventory.warehouses.destroy', row.id));
    }
}
</script>
