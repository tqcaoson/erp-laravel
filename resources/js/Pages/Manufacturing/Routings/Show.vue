<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-800">Quy trình {{ routing.code }}</h1>
                <p class="text-sm text-slate-500">
                    {{ routing.name }} · Sản phẩm áp dụng: {{ routing.product_name ?? 'Dùng chung' }}
                </p>
            </div>
            <Link :href="route('manufacturing.routings.index')" class="text-sm text-blue-600 hover:underline">
                ← Danh sách quy trình
            </Link>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Công đoạn theo thứ tự</h2>
            <DataTable
                :columns="[
                    { key: 'sequence', label: 'Thứ tự' },
                    { key: 'operation_name', label: 'Công đoạn' },
                    { key: 'work_center', label: 'Trung tâm sản xuất' },
                    { key: 'standard_minutes', label: 'Thời gian chuẩn (phút)' },
                ]"
                :items="routing.operations ?? []"
                empty-text="Quy trình này chưa có công đoạn nào."
            >
                <template #cell-work_center="{ row }">{{ row.work_center?.name ?? '—' }}</template>
                <template #cell-standard_minutes="{ row }">{{ number(row.standard_minutes, 2) }}</template>
            </DataTable>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import DataTable from '../../../Components/DataTable.vue';
import { useFormat } from '../../../composables/useFormat';

defineProps({ routing: { type: Object, required: true } });

const { number } = useFormat();
</script>
