<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold text-slate-800">Lệnh sản xuất</h1>
            <Link
                v-if="can('manufacturing.mo.create')"
                :href="route('manufacturing.orders.create')"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
                + Tạo lệnh sản xuất
            </Link>
        </div>

        <form class="flex flex-wrap gap-2" @submit.prevent="applyFilters">
            <select v-model="form.status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                <option value="">Tất cả trạng thái</option>
                <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
            </select>
            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                Lọc
            </button>
        </form>

        <DataTable :columns="columns" :items="orders" empty-text="Chưa có lệnh sản xuất nào.">
            <template #cell-code="{ row }">
                <Link :href="route('manufacturing.orders.show', row.id)" class="font-medium text-blue-600 hover:underline">
                    {{ row.code }}
                </Link>
            </template>
            <template #cell-qty="{ row }">{{ number(row.qty, 2) }}</template>
            <template #cell-produced_qty="{ row }">{{ number(row.produced_qty, 2) }}</template>
            <template #cell-planned_start_date="{ row }">{{ date(row.planned_start_date) }}</template>
            <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
            <template #actions="{ row }">
                <Link :href="route('manufacturing.orders.show', row.id)" class="text-sm text-blue-600 hover:underline">Chi tiết</Link>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat';
import { usePermission } from '../../../composables/usePermission';

const props = defineProps({
    orders: { type: Object, required: true },
    statuses: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { date, number } = useFormat();
const { can } = usePermission();

const form = reactive({ status: props.filters.filter?.status ?? '' });

const columns = [
    { key: 'code', label: 'Mã lệnh', sortable: true },
    { key: 'product_name', label: 'Thành phẩm' },
    { key: 'warehouse_name', label: 'Kho' },
    { key: 'qty', label: 'SL kế hoạch' },
    { key: 'produced_qty', label: 'Đã sản xuất' },
    { key: 'planned_start_date', label: 'Bắt đầu dự kiến' },
    { key: 'status', label: 'Trạng thái' },
];

function applyFilters() {
    router.get(route('manufacturing.orders.index'), { 'filter[status]': form.status || undefined }, { preserveState: true });
}
</script>
