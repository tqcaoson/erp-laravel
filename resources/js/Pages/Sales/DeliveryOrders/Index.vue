<template>
    <div class="space-y-4">
        <h1 class="text-xl font-bold text-slate-800">Phiếu giao hàng</h1>

        <form class="flex flex-wrap gap-2" @submit.prevent="applyFilters">
            <select v-model="form.status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                <option value="">Tất cả trạng thái</option>
                <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
            </select>
            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                Lọc
            </button>
        </form>

        <DataTable :columns="columns" :items="deliveryOrders" empty-text="Chưa có phiếu giao hàng nào.">
            <template #cell-code="{ row }">
                <Link :href="route('sales.deliveries.show', row.id)" class="font-medium text-blue-600 hover:underline">
                    {{ row.code }}
                </Link>
            </template>
            <template #cell-sales_order="{ row }">{{ row.sales_order?.code ?? '—' }}</template>
            <template #cell-delivery_date="{ row }">{{ date(row.delivery_date) }}</template>
            <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
            <template #actions="{ row }">
                <Link :href="route('sales.deliveries.show', row.id)" class="text-sm text-blue-600 hover:underline">Chi tiết</Link>
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

const props = defineProps({
    deliveryOrders: { type: Object, required: true },
    statuses: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { date } = useFormat();

const form = reactive({ status: props.filters.filter?.status ?? '' });

const columns = [
    { key: 'code', label: 'Mã phiếu', sortable: true },
    { key: 'sales_order', label: 'Đơn bán' },
    { key: 'warehouse_name', label: 'Kho xuất' },
    { key: 'delivery_date', label: 'Ngày giao' },
    { key: 'status', label: 'Trạng thái' },
];

function applyFilters() {
    router.get(route('sales.deliveries.index'), { 'filter[status]': form.status || undefined }, { preserveState: true });
}
</script>
