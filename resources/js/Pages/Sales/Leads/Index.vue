<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold text-slate-800">Đầu mối</h1>
            <Link
                v-if="can('sales.lead.manage')"
                :href="route('sales.leads.create')"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
                + Thêm đầu mối
            </Link>
        </div>

        <form class="flex flex-wrap gap-2" @submit.prevent="applyFilters">
            <input
                v-model="form.search"
                type="text"
                placeholder="Tìm theo tên, công ty, điện thoại…"
                class="w-72 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
            />
            <select v-model="form.status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                <option value="">Tất cả trạng thái</option>
                <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
            </select>
            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                Lọc
            </button>
        </form>

        <DataTable :columns="columns" :items="leads" empty-text="Chưa có đầu mối nào.">
            <template #cell-status="{ row }">
                <select
                    :value="row.status"
                    class="rounded border border-slate-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none"
                    @change="changeStatus(row, $event.target.value)"
                >
                    <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
                </select>
            </template>
            <template #actions="{ row }">
                <button class="text-sm text-red-600 hover:underline" @click="removeLead(row)">Xóa</button>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import DataTable from '../../../Components/DataTable.vue';
import { usePermission } from '../../../composables/usePermission';

const props = defineProps({
    leads: { type: Object, required: true },
    statuses: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { can } = usePermission();

const form = reactive({
    search: props.filters.search ?? '',
    status: props.filters.filter?.status ?? '',
});

const columns = [
    { key: 'name', label: 'Tên', sortable: true },
    { key: 'company_name', label: 'Công ty' },
    { key: 'phone', label: 'Điện thoại' },
    { key: 'email', label: 'Email' },
    { key: 'source', label: 'Nguồn' },
    { key: 'status', label: 'Trạng thái' },
];

function applyFilters() {
    router.get(
        route('sales.leads.index'),
        { search: form.search || undefined, 'filter[status]': form.status || undefined },
        { preserveState: true },
    );
}

function changeStatus(lead, status) {
    router.put(route('sales.leads.update', lead.id), { ...lead, status }, { preserveScroll: true });
}

function removeLead(lead) {
    if (confirm(`Xóa đầu mối "${lead.name}"?`)) {
        router.delete(route('sales.leads.destroy', lead.id), { preserveScroll: true });
    }
}
</script>
