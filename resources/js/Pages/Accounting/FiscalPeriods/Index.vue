<template>
    <div class="mx-auto max-w-3xl space-y-4 p-4 sm:p-6">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">Kỳ kế toán</h1>
            <p class="text-sm text-slate-500">Kỳ đã khóa thì không ghi sổ được chứng từ thuộc kỳ đó. Kỳ tháng mới được tự tạo khi ghi bút toán.</p>
        </div>

        <form @submit.prevent="createPeriod" class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Mã kỳ (YYYY-MM) *</label>
                <input v-model="form.code" type="text" placeholder="2026-01" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                <p v-if="form.errors.code" class="mt-1 text-sm text-red-600">{{ form.errors.code }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Từ ngày *</label>
                <input v-model="form.start_date" type="date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Đến ngày *</label>
                <input v-model="form.end_date" type="date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
            </div>
            <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                Tạo kỳ
            </button>
        </form>

        <DataTable :columns="columns" :items="periods" empty-text="Chưa có kỳ kế toán nào.">
            <template #cell-start_date="{ row }">{{ format.date(row.start_date) }}</template>
            <template #cell-end_date="{ row }">{{ format.date(row.end_date) }}</template>
            <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
            <template #actions="{ row }">
                <button
                    v-if="row.status === 'open'"
                    @click="lockPeriod(row)"
                    class="text-sm font-medium text-amber-600 hover:underline"
                >
                    Khóa kỳ
                </button>
                <button
                    v-else
                    @click="unlockPeriod(row)"
                    class="text-sm font-medium text-green-600 hover:underline"
                >
                    Mở lại
                </button>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { router, useForm } from '@inertiajs/vue3';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat';

defineProps({
    periods: { type: Object, required: true },
});

const format = useFormat();

const columns = [
    { key: 'code', label: 'Mã kỳ' },
    { key: 'start_date', label: 'Từ ngày' },
    { key: 'end_date', label: 'Đến ngày' },
    { key: 'status', label: 'Trạng thái' },
];

const form = useForm({ code: '', start_date: '', end_date: '' });

function createPeriod() {
    form.post(route('accounting.fiscal-periods.store'), { onSuccess: () => form.reset() });
}

function lockPeriod(row) {
    if (confirm(`Khóa kỳ ${row.code}? Sau khi khóa sẽ không ghi sổ được chứng từ trong kỳ này.`)) {
        router.post(route('accounting.fiscal-periods.lock', row.id));
    }
}

function unlockPeriod(row) {
    router.post(route('accounting.fiscal-periods.unlock', row.id));
}
</script>
