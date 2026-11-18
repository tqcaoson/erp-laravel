<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold text-slate-800">Khách hàng</h1>
            <Link
                v-if="can('sales.customer.manage')"
                :href="route('sales.customers.create')"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
                + Thêm khách hàng
            </Link>
        </div>

        <form class="flex flex-wrap gap-2" @submit.prevent="applyFilters">
            <input
                v-model="form.search"
                type="text"
                placeholder="Tìm theo tên, mã, điện thoại…"
                class="w-72 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
            />
            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                Tìm kiếm
            </button>
        </form>

        <DataTable :columns="columns" :items="customers" empty-text="Chưa có khách hàng nào.">
            <template #cell-name="{ row }">
                <Link :href="route('sales.customers.show', row.id)" class="font-medium text-blue-600 hover:underline">
                    {{ row.name }}
                </Link>
            </template>
            <template #cell-credit_limit="{ row }">
                {{ row.credit_limit > 0 ? money(row.credit_limit) : 'Không giới hạn' }}
            </template>
            <template #cell-current_debt="{ row }">
                <span :class="row.current_debt > 0 ? 'font-medium text-amber-700' : ''">{{ money(row.current_debt) }}</span>
            </template>
            <template #cell-remaining_credit="{ row }">
                <span v-if="row.remaining_credit === null" class="text-slate-400">—</span>
                <span v-else :class="row.remaining_credit < 0 ? 'font-medium text-red-600' : 'text-green-700'">
                    {{ money(row.remaining_credit) }}
                </span>
            </template>
            <template #cell-is_active="{ row }">
                <StatusBadge :status="row.is_active ? 'active' : 'inactive'" />
            </template>
            <template #actions="{ row }">
                <Link
                    v-if="can('sales.customer.manage')"
                    :href="route('sales.customers.edit', row.id)"
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
    customers: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { money } = useFormat();
const { can } = usePermission();

const form = reactive({ search: props.filters.search ?? '' });

const columns = [
    { key: 'code', label: 'Mã', sortable: true },
    { key: 'name', label: 'Tên khách hàng', sortable: true },
    { key: 'phone', label: 'Điện thoại' },
    { key: 'credit_limit', label: 'Hạn mức công nợ' },
    { key: 'current_debt', label: 'Công nợ hiện tại' },
    { key: 'remaining_credit', label: 'Hạn mức còn lại' },
    { key: 'is_active', label: 'Trạng thái' },
];

function applyFilters() {
    router.get(route('sales.customers.index'), { search: form.search || undefined }, { preserveState: true });
}
</script>
