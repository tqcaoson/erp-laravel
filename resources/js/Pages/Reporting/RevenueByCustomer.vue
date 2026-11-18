<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Doanh thu theo khách hàng</h1>
                <p class="mt-1 text-sm text-slate-500">Tổng doanh thu và số đơn theo từng khách hàng trong khoảng thời gian.</p>
            </div>
            <a :href="exportUrl" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">
                Xuất CSV
            </a>
        </div>

        <div class="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Từ ngày</label>
                <input v-model="filterForm.from" type="date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Đến ngày</label>
                <input v-model="filterForm.to" type="date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
            </div>
            <button @click="applyFilters" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Xem báo cáo</button>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-medium text-slate-500">Tổng doanh thu</p>
                <p class="mt-1 text-xl font-bold text-slate-900">{{ money(totalRevenue) }}</p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-medium text-slate-500">Tổng số đơn</p>
                <p class="mt-1 text-xl font-bold text-slate-900">{{ number(totalOrders) }}</p>
            </div>
        </div>

        <DataTable :columns="columns" :items="rows" empty-text="Không có doanh thu trong khoảng thời gian đã chọn.">
            <template #cell-revenue="{ value }">
                <span class="font-medium">{{ money(value) }}</span>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import DataTable from '../../Components/DataTable.vue';
import { useFormat } from '../../composables/useFormat.js';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    from: { type: String, default: '' },
    to: { type: String, default: '' },
    totalRevenue: { type: [Number, String], default: 0 },
    totalOrders: { type: [Number, String], default: 0 },
});

const { money, number } = useFormat();

const columns = [
    { key: 'customer_code', label: 'Mã khách' },
    { key: 'customer_name', label: 'Tên khách hàng' },
    { key: 'orders', label: 'Số đơn' },
    { key: 'revenue', label: 'Doanh thu' },
];

const filterForm = reactive({ from: props.from, to: props.to });

const exportUrl = computed(
    () => route('reports.revenue-by-customer', { from: filterForm.from, to: filterForm.to, export: 'csv' }),
);

function applyFilters() {
    router.get(route('reports.revenue-by-customer'), filterForm, { preserveState: true, preserveScroll: true });
}
</script>
