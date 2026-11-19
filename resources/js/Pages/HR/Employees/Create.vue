<template>
    <div class="mx-auto max-w-2xl space-y-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Thêm nhân viên</h1>
            <p class="mt-1 text-sm text-slate-500">Mã nhân viên (NV-0001…) được sinh tự động sau khi lưu.</p>
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
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
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
                        <option value="" disabled>— Chọn phòng ban —</option>
                        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                    <p v-if="form.errors.department_id" class="mt-1 text-sm text-red-600">{{ form.errors.department_id }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Chức danh</label>
                    <select v-model="form.position_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option :value="null">— Không chọn —</option>
                        <option v-for="p in positions" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Ngày vào làm</label>
                    <input v-model="form.hire_date" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Ngày nghỉ việc</label>
                    <input v-model="form.termination_date" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p class="mt-1 text-xs text-slate-500">Để trống nếu đang làm việc. Nhân viên nghỉ giữa tháng vẫn được tính lương phần đã làm trong tháng đó.</p>
                    <p v-if="form.errors.termination_date" class="mt-1 text-sm text-red-600">{{ form.errors.termination_date }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Lương cơ bản (VND)</label>
                    <NumberInput v-model="form.base_salary" :min="0" placeholder="VD: 20000" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.base_salary" class="mt-1 text-sm text-red-600">{{ form.errors.base_salary }}</p>
<p class="mt-1 text-xs text-slate-400">Nhập số, có thể dùng dấu . hoặc , phân cách nghìn.</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Số người phụ thuộc</label>
                    <NumberInput v-model="form.dependents_count" :min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p class="mt-1 text-xs text-slate-500">Mỗi người phụ thuộc được giảm trừ 6.200.000 đ/tháng khi tính thuế TNCN.</p>
                    <p v-if="form.errors.dependents_count" class="mt-1 text-sm text-red-600">{{ form.errors.dependents_count }}</p>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tài khoản đăng nhập</label>
                <select v-model="form.user_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <option :value="null">— Không gắn tài khoản —</option>
                    <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
                </select>
                <p class="mt-1 text-xs text-slate-500">Gắn tài khoản để nhân viên này đăng nhập hệ thống (chấm công, xin nghỉ phép…).</p>
                <p v-if="form.errors.user_id" class="mt-1 text-sm text-red-600">{{ form.errors.user_id }}</p>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <Link :href="route('hr.employees.index')" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Hủy</Link>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                    {{ form.processing ? 'Đang lưu…' : 'Lưu nhân viên' }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import NumberInput from '../../../Components/NumberInput.vue';

defineProps({
    departments: { type: Array, default: () => [] },
    positions: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
});

const form = useForm({
    full_name: '',
    email: '',
    phone: '',
    department_id: '',
    position_id: null,
    user_id: null,
    hire_date: '',
    termination_date: '',
    dependents_count: 0,
    base_salary: 0,
});

function submit() {
    form.post(route('hr.employees.store'));
}
</script>
