<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-slate-800">Đơn mua hàng</h1>
            <Link v-if="can('procurement.po.create')" :href="route('procurement.purchase-orders.create')" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                + Tạo đơn mua
            </Link>
        </div>
        <form @submit.prevent="search" class="flex flex-wrap gap-3">
            <input v-model="form.search" type="text" placeholder="Tìm theo mã đơn..." class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
            <select v-model="form.supplier_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                <option value="">— Tất cả nhà cung cấp —</option>
                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
            <select v-model="form.filter.status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                <option value="">— Tất cả trạng thái —</option>
                <option value="draft">Nháp</option>
                <option value="pending_approval">Chờ duyệt</option>
                <option value="approved">Đã duyệt</option>
                <option value="partially_received">Nhận một phần</option>
                <option value="received">Đã nhận hàng</option>
                <option value="closed">Đã đóng</option>
                <option value="cancelled">Đã hủy</option>
            </select>
            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Lọc</button>
        </form>
        <DataTable :columns="columns" :items="orders" empty-text="Chưa có đơn mua hàng nào.">
            <template #cell-supplier="{ row }">{{ row.supplier?.name ?? '—' }}</template>
            <template #cell-progress="{ row }">{{ number(totalReceived(row)) }}/{{ number(totalOrdered(row)) }}</template>
            <template #cell-order_date="{ value }">{{ date(value) }}</template>
            <template #cell-total_amount="{ value }">{{ money(value) }}</template>
            <template #cell-status="{ value }"><StatusBadge :status="value" /></template>
            <template #actions="{ row }">
                <Link :href="route('procurement.purchase-orders.show', row.id)" class="text-sm font-medium text-blue-600 hover:underline">Chi tiết</Link>
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
    orders: { type: Object, required: true },
    suppliers: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { money, number, date } = useFormat();
const { can } = usePermission();

const columns = [
    { key: 'code', label: 'Mã đơn', sortable: true },
    { key: 'supplier', label: 'Nhà cung cấp' },
    { key: 'progress', label: 'Tiến độ nhận' },
    { key: 'order_date', label: 'Ngày đặt', sortable: true },
    { key: 'total_amount', label: 'Tổng tiền', sortable: true },
    { key: 'status', label: 'Trạng thái' },
];

/** Tổng số lượng đặt / đã nhận của đơn (cộng dồn các dòng). */
function totalOrdered(order) {
    return (order.lines ?? []).reduce((sum, l) => sum + Number(l.qty || 0), 0);
}

function totalReceived(order) {
    return (order.lines ?? []).reduce((sum, l) => sum + Number(l.received_qty || 0), 0);
}

const form = reactive({
    search: props.filters.search ?? '',
    supplier_id: props.filters.supplier_id ?? '',
    filter: { status: props.filters.filter?.status ?? '' },
});

function search() {
    router.get(route('procurement.purchase-orders.index'), form, { preserveState: true });
}
</script>
