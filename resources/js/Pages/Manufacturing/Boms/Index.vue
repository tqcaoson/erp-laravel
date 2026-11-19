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

        <form class="flex flex-wrap gap-2" @submit.prevent="applyFilters">
            <select v-model="form.is_active" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                <option value="">Tất cả trạng thái</option>
                <option value="1">Đang hoạt động</option>
                <option value="0">Ngừng hoạt động</option>
            </select>
            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                Lọc
            </button>
        </form>

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
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { usePermission } from '../../../composables/usePermission';

const props = defineProps({
    boms: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { can } = usePermission();

const form = reactive({ is_active: props.filters.filter?.is_active ?? '' });

const columns = [
    { key: 'code', label: 'Mã BOM', sortable: true },
    { key: 'product_name', label: 'Thành phẩm' },
    { key: 'version', label: 'Phiên bản' },
    { key: 'lines_count', label: 'Số dòng NL' },
    { key: 'is_active', label: 'Trạng thái' },
];

function applyFilters() {
    router.get(route('manufacturing.boms.index'), { 'filter[is_active]': form.is_active === '' ? undefined : form.is_active }, { preserveState: true });
}
</script>
