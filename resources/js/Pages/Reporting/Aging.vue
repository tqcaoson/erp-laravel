<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Công nợ tuổi nợ</h1>
                <p class="mt-1 text-sm text-slate-500">Hóa đơn phải thu chia nhóm theo số ngày quá hạn so với hạn thanh toán.</p>
            </div>
            <a :href="route('reports.aging', { export: 'csv' })" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">
                Xuất CSV
            </a>
        </div>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div v-for="b in buckets" :key="b.key" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-medium text-slate-500">{{ b.label }}</p>
                <p class="mt-1 text-xl font-bold" :class="b.key === '90+' && b.amount > 0 ? 'text-red-600' : 'text-slate-900'">{{ money(b.amount) }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ number(b.count) }} hóa đơn</p>
            </div>
        </div>

        <div class="rounded-lg bg-white p-4 text-sm text-slate-600 shadow-sm ring-1 ring-slate-200">
            Tổng công nợ phải thu: <span class="font-bold text-slate-900">{{ money(totalAmount) }}</span>
            ({{ number(totalCount) }} hóa đơn chưa thanh toán)
        </div>

        <div v-for="b in buckets" :key="b.key" class="space-y-2">
            <h2 class="text-base font-semibold text-slate-900">Nhóm {{ b.label }} — {{ money(b.amount) }}</h2>
            <DataTable :columns="columns" :items="b.invoices" empty-text="Không có hóa đơn trong nhóm này.">
                <template #cell-invoice_date="{ value }">
                    {{ date(value) }}
                </template>
                <template #cell-due_date="{ value }">
                    {{ date(value) }}
                </template>
                <template #cell-overdue_days="{ value }">
                    <span :class="value > 0 ? 'font-medium text-red-600' : 'text-slate-500'">{{ number(value) }} ngày</span>
                </template>
                <template #cell-total_amount="{ value }">
                    <span class="font-medium">{{ money(value) }}</span>
                </template>
            </DataTable>
        </div>
    </div>
</template>

<script setup>
import DataTable from '../../Components/DataTable.vue';
import { useFormat } from '../../composables/useFormat.js';

defineProps({
    buckets: { type: Array, default: () => [] },
    totalAmount: { type: [Number, String], default: 0 },
    totalCount: { type: [Number, String], default: 0 },
});

const { money, number, date } = useFormat();

const columns = [
    { key: 'code', label: 'Số hóa đơn' },
    { key: 'customer_name', label: 'Khách hàng' },
    { key: 'invoice_date', label: 'Ngày hóa đơn' },
    { key: 'due_date', label: 'Hạn thanh toán' },
    { key: 'overdue_days', label: 'Quá hạn' },
    { key: 'total_amount', label: 'Số tiền' },
];
</script>
