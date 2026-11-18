<template>
    <div class="space-y-4 p-4 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800">Phiếu thu / chi</h1>
                <p class="text-sm text-slate-500">Thu tiền bán hàng, trả tiền mua hàng và các khoản thu/chi khác.</p>
            </div>
            <Link
                v-if="can('accounting.payment.create')"
                :href="route('accounting.payments.create')"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
                + Tạo phiếu thu/chi
            </Link>
        </div>

        <form @submit.prevent="applyFilters" class="flex flex-wrap items-center gap-3">
            <select v-model="filterForm.type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Tất cả loại</option>
                <option value="receipt">Phiếu thu</option>
                <option value="payment">Phiếu chi</option>
            </select>
            <select v-model="filterForm.status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Tất cả trạng thái</option>
                <option value="draft">Nháp</option>
                <option value="posted">Đã ghi sổ</option>
                <option value="cancelled">Đã hủy</option>
            </select>
            <button type="submit" class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Lọc</button>
        </form>

        <DataTable :columns="columns" :items="payments" empty-text="Chưa có phiếu thu/chi nào.">
            <template #cell-type="{ row }">{{ row.type === 'receipt' ? 'Phiếu thu' : 'Phiếu chi' }}</template>
            <template #cell-payment_date="{ row }">{{ format.date(row.payment_date) }}</template>
            <template #cell-amount="{ row }">{{ format.money(row.amount) }}</template>
            <template #cell-invoice="{ row }">{{ row.invoice?.code ?? '—' }}</template>
            <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
            <template #actions="{ row }">
                <button
                    v-if="row.status === 'draft' && can('accounting.payment.create')"
                    @click="postPayment(row)"
                    class="text-sm font-medium text-green-600 hover:underline"
                >
                    Ghi sổ
                </button>
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
    payments: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const format = useFormat();
const { can } = usePermission();

const columns = [
    { key: 'code', label: 'Mã phiếu' },
    { key: 'type', label: 'Loại' },
    { key: 'payment_date', label: 'Ngày' },
    { key: 'amount', label: 'Số tiền' },
    { key: 'invoice', label: 'Hóa đơn' },
    { key: 'description', label: 'Diễn giải' },
    { key: 'status', label: 'Trạng thái' },
];

const filterForm = reactive({
    type: props.filters.type ?? '',
    status: props.filters.status ?? '',
});

function applyFilters() {
    router.get(route('accounting.payments.index'), {
        'filter[type]': filterForm.type || undefined,
        'filter[status]': filterForm.status || undefined,
    }, { preserveState: true });
}

function postPayment(row) {
    router.post(route('accounting.payments.post', row.id));
}
</script>
