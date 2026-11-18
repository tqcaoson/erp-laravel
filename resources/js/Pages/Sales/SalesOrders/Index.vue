<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold text-slate-800">Đơn bán hàng</h1>
            <Link
                v-if="can('sales.order.create')"
                :href="route('sales.orders.create')"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
                + Tạo đơn bán
            </Link>
        </div>

        <form class="flex flex-wrap gap-2" @submit.prevent="applyFilters">
            <select v-model="form.status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                <option value="">Tất cả trạng thái</option>
                <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
            </select>
            <select v-model="form.customer_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                <option value="">Tất cả khách hàng</option>
                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                Lọc
            </button>
        </form>

        <DataTable :columns="columns" :items="orders" empty-text="Chưa có đơn bán nào.">
            <template #cell-code="{ row }">
                <Link :href="route('sales.orders.show', row.id)" class="font-medium text-blue-600 hover:underline">
                    {{ row.code }}
                </Link>
            </template>
            <template #cell-customer="{ row }">{{ row.customer?.name ?? '—' }}</template>
            <template #cell-order_date="{ row }">{{ date(row.order_date) }}</template>
            <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
            <template #cell-total_amount="{ row }">{{ money(row.total_amount) }}</template>
            <template #actions="{ row }">
                <Link :href="route('sales.orders.show', row.id)" class="text-sm text-blue-600 hover:underline">Chi tiết</Link>
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
    customers: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const { money, date } = useFormat();
const { can } = usePermission();

const form = reactive({
    status: props.filters.filter?.status ?? '',
    customer_id: props.filters.filter?.customer_id ?? '',
});

const columns = [
    { key: 'code', label: 'Mã đơn', sortable: true },
    { key: 'customer', label: 'Khách hàng' },
    { key: 'warehouse_name', label: 'Kho giao' },
    { key: 'order_date', label: 'Ngày đặt', sortable: true },
    { key: 'status', label: 'Trạng thái' },
    { key: 'total_amount', label: 'Tổng tiền', sortable: true },
];

function applyFilters() {
    router.get(
        route('sales.orders.index'),
        { 'filter[status]': form.status || undefined, 'filter[customer_id]': form.customer_id || undefined },
        { preserveState: true },
    );
}
</script>
