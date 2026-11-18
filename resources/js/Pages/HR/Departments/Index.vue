<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Phòng ban</h1>
            <p class="mt-1 text-sm text-slate-500">Cơ cấu tổ chức và số lượng nhân viên mỗi phòng.</p>
        </div>

        <form
            v-if="can('hr.employee.manage')"
            @submit.prevent="submit"
            class="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200"
        >
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Mã phòng *</label>
                <input v-model="form.code" type="text" required placeholder="VD: HCNS" class="w-36 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
            </div>
            <div class="min-w-48 flex-1">
                <label class="mb-1 block text-sm font-medium text-slate-700">Tên phòng *</label>
                <input v-model="form.name" type="text" required placeholder="VD: Hành chính nhân sự" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Quản lý</label>
                <select v-model="form.manager_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <option :value="null">— Chưa chọn —</option>
                    <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name }}</option>
                </select>
            </div>
            <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                Thêm phòng ban
            </button>
        </form>

        <DataTable :columns="columns" :items="departments" empty-text="Chưa có phòng ban nào.">
            <template #cell-manager="{ row }">
                {{ row.manager?.full_name ?? '—' }}
            </template>
            <template #cell-is_active="{ value }">
                <StatusBadge :status="value ? 'active' : 'inactive'" />
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { usePermission } from '../../../composables/usePermission.js';

defineProps({
    departments: { type: Object, required: true },
    employees: { type: Array, default: () => [] },
});

const { can } = usePermission();

const columns = [
    { key: 'code', label: 'Mã phòng' },
    { key: 'name', label: 'Tên phòng' },
    { key: 'manager', label: 'Quản lý' },
    { key: 'employees_count', label: 'Số nhân viên' },
    { key: 'is_active', label: 'Trạng thái' },
];

const form = useForm({ code: '', name: '', manager_id: null, is_active: true });

function submit() {
    form.post(route('hr.departments.store'), { onSuccess: () => form.reset() });
}
</script>
