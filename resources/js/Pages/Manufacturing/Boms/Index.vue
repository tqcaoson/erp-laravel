<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold text-slate-800">Định mức nguyên vật liệu (BOM)</h1>
            <Link
                v-if="can('manufacturing.bom.manage')"
                :href="route('manufacturing.boms.create')"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
                + Tạo BOM
            </Link>
        </div>

        <DataTable :columns="columns" :items="boms" empty-text="Chưa có định mức nào.">
            <template #cell-code="{ row }">
                <Link :href="route('manufacturing.boms.show', row.id)" class="font-medium text-blue-600 hover:underline">
                    {{ row.code }}
                </Link>
            </template>
            <template #cell-is_active="{ row }">
                <StatusBadge :status="row.is_active ? 'active' : 'inactive'" />
            </template>
            <template #actions="{ row }">
                <Link :href="route('manufacturing.boms.show', row.id)" class="text-sm text-blue-600 hover:underline">Chi tiết</Link>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { usePermission } from '../../../composables/usePermission';

defineProps({
    boms: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { can } = usePermission();

const columns = [
    { key: 'code', label: 'Mã BOM', sortable: true },
    { key: 'product_name', label: 'Thành phẩm' },
    { key: 'version', label: 'Phiên bản' },
    { key: 'lines_count', label: 'Số dòng NL' },
    { key: 'is_active', label: 'Trạng thái' },
];
</script>
