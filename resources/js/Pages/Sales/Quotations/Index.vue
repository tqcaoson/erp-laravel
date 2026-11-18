<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold text-slate-800">Báo giá</h1>
            <Link
                v-if="can('sales.quotation.create')"
                :href="route('sales.quotations.create')"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
                + Tạo báo giá
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

        <DataTable :columns="columns" :items="quotations" empty-text="Chưa có báo giá nào.">
            <template #cell-code="{ row }">
                <Link :href="route('sales.quotations.show', row.id)" class="font-medium text-blue-600 hover:underline">
                    {{ row.code }}
                </Link>
            </template>
            <template #cell-customer="{ row }">{{ row.customer?.name ?? '—' }}</template>
            <template #cell-valid_until="{ row }">{{ date(row.valid_until) }}</template>
            <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
            <template #cell-total_amount="{ row }">{{ money(row.total_amount) }}</template>
            <template #actions="{ row }">
                <Link :href="route('sales.quotations.show', row.id)" class="text-sm text-blue-600 hover:underline">Chi tiết</Link>
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
    quotations: { type: Object, required: true },
    statuses: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { money, date } = useFormat();
const { can } = usePermission();

const form = reactive({ status: props.filters.filter?.status ?? '' });

const columns = [
    { key: 'code', label: 'Mã báo giá', sortable: true },
    { key: 'customer', label: 'Khách hàng' },
    { key: 'valid_until', label: 'Hiệu lực đến' },
    { key: 'status', label: 'Trạng thái' },
    { key: 'total_amount', label: 'Tổng tiền', sortable: true },
];

function applyFilters() {
    router.get(route('sales.quotations.index'), { 'filter[status]': form.status || undefined }, { preserveState: true });
}
</script>
