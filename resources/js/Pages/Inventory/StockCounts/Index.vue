<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-slate-800">Kiểm kê kho</h1>
            <Link :href="route('inventory.stock-counts.create')" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                + Tạo phiếu kiểm kê
            </Link>
        </div>
        <form @submit.prevent="search" class="flex flex-wrap gap-3">
            <select v-model="form.warehouse_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                <option value="">— Tất cả kho —</option>
                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
            <select v-model="form.filter.status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                <option value="">— Tất cả trạng thái —</option>
                <option value="draft">Nháp</option>
                <option value="in_progress">Đang kiểm</option>
                <option value="completed">Hoàn tất</option>
                <option value="cancelled">Đã hủy</option>
            </select>
            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Lọc</button>
        </form>
        <DataTable :columns="columns" :items="counts" empty-text="Chưa có phiếu kiểm kê nào.">
            <template #cell-warehouse="{ row }">{{ row.warehouse?.name ?? '—' }}</template>
            <template #cell-status="{ value }"><StatusBadge :status="value" /></template>
            <template #cell-created_at="{ value }">{{ datetime(value) }}</template>
            <template #actions="{ row }">
                <Link :href="route('inventory.stock-counts.show', row.id)" class="text-sm font-medium text-blue-600 hover:underline">Chi tiết</Link>
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

const props = defineProps({
    counts: { type: Object, required: true },
    warehouses: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { datetime } = useFormat();

const columns = [
    { key: 'code', label: 'Mã phiếu', sortable: true },
    { key: 'warehouse', label: 'Kho' },
    { key: 'lines_count', label: 'Số dòng' },
    { key: 'status', label: 'Trạng thái' },
    { key: 'created_at', label: 'Ngày tạo', sortable: true },
];

const form = reactive({
    warehouse_id: props.filters.warehouse_id ?? '',
    filter: { status: props.filters.filter?.status ?? '' },
});

function search() {
    router.get(route('inventory.stock-counts.index'), form, { preserveState: true });
}
</script>
