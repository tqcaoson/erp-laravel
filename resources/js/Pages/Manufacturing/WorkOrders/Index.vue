<template>
    <div class="space-y-4">
        <h1 class="text-xl font-bold text-slate-800">Công đoạn sản xuất</h1>

        <form class="flex flex-wrap gap-2" @submit.prevent="applyFilters">
            <select v-model="form.status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                <option value="">Tất cả trạng thái</option>
                <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
            </select>
            <select v-model="form.manufacturing_order_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                <option value="">Tất cả lệnh sản xuất</option>
                <option v-for="o in manufacturingOrders" :key="o.id" :value="o.id">{{ o.code }}</option>
            </select>
            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                Lọc
            </button>
        </form>

        <DataTable :columns="columns" :items="workOrders" empty-text="Chưa có công đoạn nào.">
            <template #cell-manufacturing_order="{ row }">
                <Link :href="route('manufacturing.orders.show', row.manufacturing_order_id)" class="font-medium text-blue-600 hover:underline">
                    {{ row.manufacturing_order?.code ?? '—' }}
                </Link>
            </template>
            <template #cell-work_center="{ row }">{{ row.work_center?.name ?? '—' }}</template>
            <template #cell-planned_minutes="{ row }">{{ number(row.planned_minutes, 0) }}</template>
            <template #cell-actual_minutes="{ row }">
                <NumberInput
                    v-if="can('manufacturing.mo.start')"
                    v-model="minutesById[row.id]"
                    :min="0"
                    placeholder="Phút"
                    class="w-20 rounded border border-slate-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none"
                />
                <span v-else>{{ row.actual_minutes === null ? '—' : number(row.actual_minutes, 0) }}</span>
            </template>
            <template #cell-status="{ row }">
                <select
                    v-if="can('manufacturing.mo.start')"
                    :value="row.status"
                    class="rounded border border-slate-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none"
                    @change="changeStatus(row, $event.target.value)"
                >
                    <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
                </select>
                <StatusBadge v-else :status="row.status" />
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import DataTable from '../../../Components/DataTable.vue';
import NumberInput from '../../../Components/NumberInput.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat';
import { usePermission } from '../../../composables/usePermission';

const props = defineProps({
    workOrders: { type: Object, required: true },
    statuses: { type: Object, required: true },
    manufacturingOrders: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const { number } = useFormat();
const { can } = usePermission();

// Phút thực tế từng công đoạn (khởi tạo từ dữ liệu đã lưu, gửi kèm khi đổi
// trạng thái; ô chưa sửa thì giữ nguyên giá trị cũ thay vì ghi đè null).
const minutesById = reactive({});
for (const row of props.workOrders?.data ?? []) {
    minutesById[row.id] = row.actual_minutes === null ? null : Number(row.actual_minutes);
}

const form = reactive({
    status: props.filters.filter?.status ?? '',
    manufacturing_order_id: props.filters.filter?.manufacturing_order_id ?? '',
});

const columns = [
    { key: 'code', label: 'Mã', sortable: true },
    { key: 'manufacturing_order', label: 'Lệnh sản xuất' },
    { key: 'operation_name', label: 'Công đoạn' },
    { key: 'work_center', label: 'Trung tâm' },
    { key: 'planned_minutes', label: 'Phút chuẩn' },
    { key: 'actual_minutes', label: 'Phút thực tế' },
    { key: 'status', label: 'Trạng thái' },
];

function applyFilters() {
    router.get(route('manufacturing.work-orders.index'), {
        'filter[status]': form.status || undefined,
        'filter[manufacturing_order_id]': form.manufacturing_order_id || undefined,
    }, { preserveState: true });
}

function changeStatus(workOrder, status) {
    router.patch(route('manufacturing.work-orders.status', workOrder.id), {
        status,
        actual_minutes: minutesById[workOrder.id] ?? workOrder.actual_minutes,
    }, { preserveScroll: true });
}
</script>
