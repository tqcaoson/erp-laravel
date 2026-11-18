<template>
    <div class="mx-auto max-w-2xl space-y-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Sửa nhân viên {{ employee.code }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ employee.full_name }}</p>
        </div>

        <form @submit.prevent="submit" class="space-y-4 rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Họ tên *</label>
                <input v-model="form.full_name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                <p v-if="form.errors.full_name" class="mt-1 text-sm text-red-600">{{ form.errors.full_name }}</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                    <input v-model="form.email" type="email" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Điện thoại</label>
                    <input v-model="form.phone" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Phòng ban *</label>
                    <select v-model="form.department_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Chức danh</label>
                    <select v-model="form.position_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option :value="null">— Không chọn —</option>
                        <option v-for="p in positions" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Ngày vào làm</label>
                    <input v-model="form.hire_date" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Lương cơ bản (VND)</label>
                    <input v-model="form.base_salary" type="number" min="0" step="1000" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Trạng thái</label>
                    <select v-model="form.status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="active">Đang làm việc</option>
                        <option value="on_leave">Đang nghỉ phép</option>
                        <option value="resigned">Đã nghỉ việc</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <Link :href="route('hr.employees.show', employee.id)" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Hủy</Link>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                    {{ form.processing ? 'Đang lưu…' : 'Lưu thay đổi' }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    employee: { type: Object, required: true },
    departments: { type: Array, default: () => [] },
    positions: { type: Array, default: () => [] },
});

const form = useForm({
    full_name: props.employee.full_name,
    email: props.employee.email ?? '',
    phone: props.employee.phone ?? '',
    department_id: props.employee.department_id,
    position_id: props.employee.position_id ?? null,
    hire_date: props.employee.hire_date ? String(props.employee.hire_date).slice(0, 10) : '',
    base_salary: props.employee.base_salary ?? 0,
    status: props.employee.status,
});

function submit() {
    form.put(route('hr.employees.update', props.employee.id));
}
</script>
