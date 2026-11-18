<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-800">BOM {{ bom.code }}</h1>
                <p class="text-sm text-slate-500">Thành phẩm: {{ bom.product_name }} · Phiên bản {{ bom.version }}</p>
            </div>
            <StatusBadge :status="bom.is_active ? 'active' : 'inactive'" />
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Nguyên liệu cho 1 đơn vị thành phẩm</h2>
            <DataTable
                :columns="[
                    { key: 'component_name', label: 'Nguyên liệu' },
                    { key: 'qty', label: 'Số lượng' },
                    { key: 'unit', label: 'Đơn vị' },
                    { key: 'scrap_rate', label: 'Hao hụt %' },
                ]"
                :items="bom.lines ?? []"
            >
                <template #cell-qty="{ row }">{{ number(row.qty, 2) }}</template>
                <template #cell-unit="{ row }">{{ row.unit?.name ?? '—' }}</template>
                <template #cell-scrap_rate="{ row }">{{ number(row.scrap_rate, 2) }}</template>
            </DataTable>
        </div>
    </div>
</template>

<script setup>
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat';

defineProps({ bom: { type: Object, required: true } });

const { number } = useFormat();
</script>
