<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold text-slate-800">Quy trình sản xuất (Routing)</h1>
            <Link
                v-if="can('manufacturing.routing.manage')"
                :href="route('manufacturing.routings.create')"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
                + Tạo quy trình
            </Link>
        </div>
        <p class="-mt-2 text-sm text-slate-500">
            Quy trình gồm các công đoạn theo thứ tự. Khi lệnh sản xuất bắt đầu, hệ thống tự tạo
            công đoạn (Work Order) từ quy trình đã gắn.
        </p>

        <DataTable :columns="columns" :items="routings" empty-text="Chưa có quy trình nào.">
            <template #cell-code="{ row }">
                <Link :href="route('manufacturing.routings.show', row.id)" class="font-medium text-blue-600 hover:underline">
                    {{ row.code }}
                </Link>
            </template>
            <template #cell-product_name="{ row }">{{ row.product_name ?? '— Dùng chung —' }}</template>
            <template #actions="{ row }">
                <Link :href="route('manufacturing.routings.show', row.id)" class="text-sm text-blue-600 hover:underline">Chi tiết</Link>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import DataTable from '../../../Components/DataTable.vue';
import { usePermission } from '../../../composables/usePermission';

defineProps({
    routings: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { can } = usePermission();

const columns = [
    { key: 'code', label: 'Mã quy trình', sortable: true },
    { key: 'name', label: 'Tên quy trình', sortable: true },
    { key: 'product_name', label: 'Sản phẩm áp dụng' },
    { key: 'operations_count', label: 'Số công đoạn' },
];
</script>
