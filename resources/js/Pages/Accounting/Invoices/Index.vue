<template>
    <div class="space-y-4 p-4 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800">Hóa đơn</h1>
                <p class="text-sm text-slate-500">Hóa đơn bán hàng và mua hàng — ghi sổ để sinh bút toán công nợ.</p>
            </div>
            <Link
                v-if="can('accounting.invoice.create')"
                :href="route('accounting.invoices.create')"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
                + Lập hóa đơn
            </Link>
        </div>

        <form @submit.prevent="applyFilters" class="flex flex-wrap items-center gap-3">
            <select v-model="filterForm.type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Tất cả loại</option>
                <option value="sale">Bán hàng</option>
                <option value="purchase">Mua hàng</option>
            </select>
            <select v-model="filterForm.status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Tất cả trạng thái</option>
                <option value="draft">Nháp</option>
                <option value="posted">Đã ghi sổ</option>
                <option value="paid">Đã thanh toán</option>
                <option value="cancelled">Đã hủy</option>
            </select>
            <input v-model="filterForm.search" type="text" placeholder="Tìm theo mã hóa đơn..." class="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            <button type="submit" class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Lọc</button>
        </form>

        <DataTable :columns="columns" :items="invoices" empty-text="Chưa có hóa đơn nào.">
            <template #cell-code="{ row }">
                <Link :href="route('accounting.invoices.show', row.id)" class="font-medium text-blue-600 hover:underline">
                    {{ row.code }}
                </Link>
            </template>
            <template #cell-type="{ row }">{{ row.type === 'sale' ? 'Bán hàng' : 'Mua hàng' }}</template>
            <template #cell-invoice_date="{ row }">{{ format.date(row.invoice_date) }}</template>
            <template #cell-total_amount="{ row }">{{ format.money(row.total_amount) }}</template>
            <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
            <template #actions="{ row }">
                <Link :href="route('accounting.invoices.show', row.id)" class="text-sm font-medium text-blue-600 hover:underline">Xem</Link>
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
    invoices: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const format = useFormat();
const { can } = usePermission();

const columns = [
    { key: 'code', label: 'Mã hóa đơn' },
    { key: 'type', label: 'Loại' },
    { key: 'invoice_date', label: 'Ngày hóa đơn' },
    { key: 'total_amount', label: 'Tổng tiền' },
    { key: 'status', label: 'Trạng thái' },
];

const filterForm = reactive({
    type: props.filters.type ?? '',
    status: props.filters.status ?? '',
    search: props.filters.search ?? '',
});

function applyFilters() {
    router.get(route('accounting.invoices.index'), {
        'filter[type]': filterForm.type || undefined,
        'filter[status]': filterForm.status || undefined,
        search: filterForm.search || undefined,
    }, { preserveState: true });
}
</script>
