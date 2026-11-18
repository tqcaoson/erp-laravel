<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-slate-800">Nhà cung cấp</h1>
            <Link v-if="can('procurement.supplier.manage')" :href="route('procurement.suppliers.create')" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                + Thêm nhà cung cấp
            </Link>
        </div>
        <form @submit.prevent="search" class="flex flex-wrap gap-3">
            <input v-model="form.search" type="text" placeholder="Tìm theo mã, tên hoặc mã số thuế…" class="w-80 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
            <select v-model="form.filter.is_active" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                <option value="">— Tất cả trạng thái —</option>
                <option value="1">Đang hoạt động</option>
                <option value="0">Ngừng hoạt động</option>
            </select>
            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Tìm kiếm</button>
        </form>
        <DataTable :columns="columns" :items="suppliers" empty-text="Chưa có nhà cung cấp nào.">
            <template #cell-is_active="{ value }"><StatusBadge :status="value ? 'active' : 'inactive'" /></template>
            <template #actions="{ row }">
                <Link v-if="can('procurement.supplier.manage')" :href="route('procurement.suppliers.edit', row.id)" class="mr-3 text-sm font-medium text-blue-600 hover:underline">Sửa</Link>
                <button v-if="can('procurement.supplier.manage')" @click="remove(row)" class="text-sm font-medium text-red-600 hover:underline">Xóa</button>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { usePermission } from '../../../composables/usePermission.js';

const props = defineProps({
    suppliers: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { can } = usePermission();

const columns = [
    { key: 'code', label: 'Mã NCC', sortable: true },
    { key: 'name', label: 'Tên nhà cung cấp', sortable: true },
    { key: 'tax_code', label: 'Mã số thuế' },
    { key: 'phone', label: 'Điện thoại' },
    { key: 'email', label: 'Email' },
    { key: 'payment_term_days', label: 'Hạn thanh toán (ngày)' },
    { key: 'is_active', label: 'Trạng thái' },
];

const form = reactive({
    search: props.filters.search ?? '',
    filter: { is_active: props.filters.filter?.is_active ?? '' },
});

function search() {
    router.get(route('procurement.suppliers.index'), form, { preserveState: true });
}

function remove(row) {
    if (confirm(`Xóa nhà cung cấp ${row.name}?`)) {
        router.delete(route('procurement.suppliers.destroy', row.id));
    }
}
</script>
