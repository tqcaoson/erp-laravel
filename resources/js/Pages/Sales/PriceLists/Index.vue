<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold text-slate-800">Bảng giá</h1>
            <Link
                v-if="can('sales.price.manage')"
                :href="route('sales.price-lists.create')"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
                + Tạo bảng giá
            </Link>
        </div>

        <DataTable :columns="columns" :items="priceLists" empty-text="Chưa có bảng giá nào.">
            <template #cell-is_active="{ row }">
                <StatusBadge :status="row.is_active ? 'active' : 'inactive'" />
            </template>
            <template #actions="{ row }">
                <button class="text-sm text-red-600 hover:underline" @click="removeList(row)">Xóa</button>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { usePermission } from '../../../composables/usePermission';

defineProps({
    priceLists: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { can } = usePermission();

const columns = [
    { key: 'code', label: 'Mã', sortable: true },
    { key: 'name', label: 'Tên bảng giá', sortable: true },
    { key: 'items_count', label: 'Số mặt hàng' },
    { key: 'is_active', label: 'Trạng thái' },
];

function removeList(list) {
    if (confirm(`Xóa bảng giá "${list.name}"?`)) {
        router.delete(route('sales.price-lists.destroy', list.id), { preserveScroll: true });
    }
}
</script>
