<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-slate-800">Yêu cầu mua hàng</h1>
            <Link v-if="can('procurement.pr.create')" :href="route('procurement.purchase-requests.create')" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                + Tạo yêu cầu mua
            </Link>
        </div>
        <form @submit.prevent="search" class="flex flex-wrap gap-3">
            <select v-model="form.filter.status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                <option value="">— Tất cả trạng thái —</option>
                <option value="draft">Nháp</option>
                <option value="pending_approval">Chờ duyệt</option>
                <option value="approved">Đã duyệt</option>
                <option value="rejected">Từ chối</option>
                <option value="converted">Đã chuyển thành PO</option>
                <option value="cancelled">Đã hủy</option>
            </select>
            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Lọc</button>
        </form>
        <DataTable :columns="columns" :items="requests" empty-text="Chưa có yêu cầu mua hàng nào.">
            <template #cell-requester="{ row }">{{ row.requester?.name ?? 'Hệ thống tự động' }}</template>
            <template #cell-total_amount="{ value }">{{ money(value) }}</template>
            <template #cell-status="{ value }"><StatusBadge :status="value" /></template>
            <template #cell-created_at="{ value }">{{ datetime(value) }}</template>
            <template #actions="{ row }">
                <Link :href="route('procurement.purchase-requests.show', row.id)" class="text-sm font-medium text-blue-600 hover:underline">Chi tiết</Link>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat.js';
import { usePermission } from '../../../composables/usePermission.js';

const props = defineProps({
    requests: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { money, datetime } = useFormat();
const { can } = usePermission();

const columns = [
    { key: 'code', label: 'Mã yêu cầu', sortable: true },
    { key: 'requester', label: 'Người yêu cầu' },
    { key: 'lines_count', label: 'Số dòng' },
    { key: 'total_amount', label: 'Tổng tiền ước tính', sortable: true },
    { key: 'status', label: 'Trạng thái' },
    { key: 'created_at', label: 'Ngày tạo', sortable: true },
];

const form = reactive({ filter: { status: props.filters.filter?.status ?? '' } });

function search() {
    router.get(route('procurement.purchase-requests.index'), form, { preserveState: true });
}
</script>
