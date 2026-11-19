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

        <form class="flex flex-wrap gap-2" @submit.prevent="applyFilters">
            <input v-model="form.search" type="text" placeholder="Tìm theo tên trung tâm..." class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                Tìm
            </button>
        </form>

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
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat';
import { usePermission } from '../../../composables/usePermission';

const props = defineProps({
    workCenters: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { money } = useFormat();
const { can } = usePermission();

const form = reactive({ search: props.filters.search ?? '' });

const columns = [
    { key: 'code', label: 'Mã', sortable: true },
    { key: 'name', label: 'Tên trung tâm', sortable: true },
    { key: 'cost_per_hour', label: 'Chi phí/giờ' },
    { key: 'is_active', label: 'Trạng thái' },
];

function applyFilters() {
    router.get(route('manufacturing.work-centers.index'), { search: form.search || undefined }, { preserveState: true });
}
</script>
