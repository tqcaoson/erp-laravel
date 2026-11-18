<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold text-slate-800">Trung tâm sản xuất</h1>
            <Link
                v-if="can('manufacturing.workcenter.manage')"
                :href="route('manufacturing.work-centers.create')"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
                + Thêm trung tâm
            </Link>
        </div>

        <DataTable :columns="columns" :items="workCenters" empty-text="Chưa có trung tâm sản xuất nào.">
            <template #cell-cost_per_hour="{ row }">{{ money(row.cost_per_hour) }}/giờ</template>
            <template #cell-is_active="{ row }">
                <StatusBadge :status="row.is_active ? 'active' : 'inactive'" />
            </template>
            <template #actions="{ row }">
                <Link
                    v-if="can('manufacturing.workcenter.manage')"
                    :href="route('manufacturing.work-centers.edit', row.id)"
                    class="text-sm text-blue-600 hover:underline"
                >
                    Sửa
                </Link>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat';
import { usePermission } from '../../../composables/usePermission';

defineProps({
    workCenters: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { money } = useFormat();
const { can } = usePermission();

const columns = [
    { key: 'code', label: 'Mã', sortable: true },
    { key: 'name', label: 'Tên trung tâm', sortable: true },
    { key: 'cost_per_hour', label: 'Chi phí/giờ' },
    { key: 'is_active', label: 'Trạng thái' },
];
</script>
