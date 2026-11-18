<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Xuất nhập tồn</h1>
                <p class="mt-1 text-sm text-slate-500">Tồn đầu kỳ, nhập, xuất, tồn cuối kỳ theo sản phẩm.</p>
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

        <p class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-500 ring-1 ring-slate-200">
            Nguồn: bảng tổng hợp xuất nhập tồn theo ngày (fallback về sổ kho khi summary trống).
            Tồn đầu kỳ trong bản này lấy theo tổng đơn giản — xem ghi chú trong ReportService.
        </p>

        <DataTable :columns="columns" :items="rows" empty-text="Không có biến động tồn kho trong khoảng thời gian đã chọn.">
            <template #cell-opening_qty="{ value }">
                {{ number(value, 2) }}
            </template>
            <template #cell-in_qty="{ value }">
                {{ number(value, 2) }}
            </template>
            <template #cell-out_qty="{ value }">
                {{ number(value, 2) }}
            </template>
            <template #cell-closing_qty="{ value }">
                <span class="font-medium">{{ number(value, 2) }}</span>
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
    warehouseId: { type: [Number, String], default: null },
});

const { number } = useFormat();

const columns = [
    { key: 'sku', label: 'SKU' },
    { key: 'name', label: 'Tên sản phẩm' },
    { key: 'opening_qty', label: 'Tồn đầu kỳ' },
    { key: 'in_qty', label: 'Nhập' },
    { key: 'out_qty', label: 'Xuất' },
    { key: 'closing_qty', label: 'Tồn cuối kỳ' },
];

const filterForm = reactive({ from: props.from, to: props.to });

const exportUrl = computed(
    () => route('reports.stock-movement', { from: filterForm.from, to: filterForm.to, export: 'csv' }),
);

function applyFilters() {
    router.get(route('reports.stock-movement'), filterForm, { preserveState: true, preserveScroll: true });
}
</script>
