<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Công nợ phải trả theo nhà cung cấp</h1>
                <p class="mt-1 text-sm text-slate-500">Số dư từ sổ cái tài khoản 331 (Có − Nợ), chia nhóm tuổi nợ theo ngày phát sinh bút toán.</p>
            </div>
            <a :href="route('reports.payables-by-supplier', { export: 'csv' })" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">
                Xuất CSV
            </a>
        </div>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div v-for="b in bucketCards" :key="b.key" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-medium text-slate-500">{{ b.label }}</p>
                <p class="mt-1 text-xl font-bold" :class="b.key === '90+' && b.amount > 0 ? 'text-red-600' : 'text-slate-900'">{{ money(b.amount) }}</p>
            </div>
        </div>

        <div class="rounded-lg bg-white p-4 text-sm text-slate-600 shadow-sm ring-1 ring-slate-200">
            Tổng công nợ phải trả: <span class="font-bold text-slate-900">{{ money(totalBalance) }}</span>
            ({{ number(rows.length) }} nhà cung cấp còn số dư)
        </div>

        <DataTable :columns="columns" :items="rows" empty-text="Không có công nợ phải trả nào.">
            <template #cell-balance="{ value }">
                <span class="font-medium">{{ money(value) }}</span>
            </template>
            <template #cell-bucket_0_30="{ row }">{{ money(row.buckets['0-30']) }}</template>
            <template #cell-bucket_31_60="{ row }">{{ money(row.buckets['31-60']) }}</template>
            <template #cell-bucket_61_90="{ row }">{{ money(row.buckets['61-90']) }}</template>
            <template #cell-bucket_90="{ row }">
                <span :class="row.buckets['90+'] > 0 ? 'font-medium text-red-600' : ''">{{ money(row.buckets['90+']) }}</span>
            </template>
        </DataTable>

        <div class="pt-4">
            <h2 class="text-lg font-bold text-slate-900">Hàng đã nhận, chưa có hóa đơn (TK 335)</h2>
            <p class="mt-1 text-sm text-slate-500">Phiếu nhận đã xác nhận nhưng kế toán chưa lập hóa đơn mua. Khi hóa đơn mua từ phiếu nhận được ghi sổ, số tiền này chuyển sang công nợ phải trả 331 ở bảng trên.</p>
        </div>

        <div class="rounded-lg bg-white p-4 text-sm text-slate-600 shadow-sm ring-1 ring-slate-200">
            Tổng hàng về chưa có hóa đơn: <span class="font-bold text-slate-900">{{ money(pendingTotal) }}</span>
            ({{ number(pendingRows.length) }} nhà cung cấp)
        </div>

        <DataTable :columns="columns" :items="pendingRows" empty-text="Không có hàng nhận chưa có hóa đơn.">
            <template #cell-balance="{ value }">
                <span class="font-medium">{{ money(value) }}</span>
            </template>
            <template #cell-bucket_0_30="{ row }">{{ money(row.buckets['0-30']) }}</template>
            <template #cell-bucket_31_60="{ row }">{{ money(row.buckets['31-60']) }}</template>
            <template #cell-bucket_61_90="{ row }">{{ money(row.buckets['61-90']) }}</template>
            <template #cell-bucket_90="{ row }">
                <span :class="row.buckets['90+'] > 0 ? 'font-medium text-red-600' : ''">{{ money(row.buckets['90+']) }}</span>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import DataTable from '../../Components/DataTable.vue';
import { useFormat } from '../../composables/useFormat.js';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    totalBalance: { type: [Number, String], default: 0 },
    bucketTotals: { type: Object, default: () => ({}) },
    pendingRows: { type: Array, default: () => [] },
    pendingTotal: { type: [Number, String], default: 0 },
});

const { money, number } = useFormat();

const bucketCards = computed(() => [
    { key: '0-30', label: '0–30 ngày', amount: Number(props.bucketTotals['0-30'] ?? 0) },
    { key: '31-60', label: '31–60 ngày', amount: Number(props.bucketTotals['31-60'] ?? 0) },
    { key: '61-90', label: '61–90 ngày', amount: Number(props.bucketTotals['61-90'] ?? 0) },
    { key: '90+', label: 'Trên 90 ngày', amount: Number(props.bucketTotals['90+'] ?? 0) },
]);

const columns = [
    { key: 'supplier_code', label: 'Mã nhà cung cấp' },
    { key: 'supplier_name', label: 'Nhà cung cấp' },
    { key: 'balance', label: 'Tổng phải trả' },
    { key: 'bucket_0_30', label: '0–30 ngày' },
    { key: 'bucket_31_60', label: '31–60 ngày' },
    { key: 'bucket_61_90', label: '61–90 ngày' },
    { key: 'bucket_90', label: 'Trên 90 ngày' },
];
</script>
