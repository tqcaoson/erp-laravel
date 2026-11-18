<template>
    <div class="space-y-4 p-4 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800">Bút toán</h1>
                <p class="text-sm text-slate-500">Sổ nhật ký chung — bút toán nhập tay và bút toán tự sinh từ các module khác.</p>
            </div>
            <Link
                v-if="can('accounting.entry.create')"
                :href="route('accounting.journal-entries.create')"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
                + Tạo bút toán
            </Link>
        </div>

        <form @submit.prevent="applyFilters" class="flex flex-wrap items-center gap-3">
            <select v-model="filterForm.status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Tất cả trạng thái</option>
                <option value="draft">Nháp</option>
                <option value="posted">Đã ghi sổ</option>
                <option value="reversed">Đã đảo</option>
            </select>
            <input v-model="filterForm.search" type="text" placeholder="Tìm theo mã bút toán..." class="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            <button type="submit" class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Lọc</button>
        </form>

        <DataTable :columns="columns" :items="entries" empty-text="Chưa có bút toán nào.">
            <template #cell-code="{ row }">
                <Link :href="route('accounting.journal-entries.show', row.id)" class="font-medium text-blue-600 hover:underline">
                    {{ row.code }}
                </Link>
            </template>
            <template #cell-entry_date="{ row }">{{ format.date(row.entry_date) }}</template>
            <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
            <template #cell-period="{ row }">{{ row.fiscal_period?.code ?? '—' }}</template>
            <template #actions="{ row }">
                <Link :href="route('accounting.journal-entries.show', row.id)" class="text-sm font-medium text-blue-600 hover:underline">Xem</Link>
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
    entries: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const format = useFormat();
const { can } = usePermission();

const columns = [
    { key: 'code', label: 'Mã bút toán' },
    { key: 'entry_date', label: 'Ngày chứng từ' },
    { key: 'description', label: 'Diễn giải' },
    { key: 'period', label: 'Kỳ kế toán' },
    { key: 'status', label: 'Trạng thái' },
];

const filterForm = reactive({
    status: props.filters.status ?? '',
    search: props.filters.search ?? '',
});

function applyFilters() {
    router.get(route('accounting.journal-entries.index'), {
        'filter[status]': filterForm.status || undefined,
        search: filterForm.search || undefined,
    }, { preserveState: true });
}
</script>
