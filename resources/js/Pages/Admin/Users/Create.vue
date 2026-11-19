<template>
    <div class="mx-auto max-w-2xl space-y-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Thêm người dùng</h1>
            <p class="mt-1 text-sm text-slate-500">Tạo tài khoản đăng nhập mới. Mật khẩu do quản trị đặt, tối thiểu 8 ký tự.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-4 rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tên *</label>
                <input v-model="form.name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" placeholder="VD: Nguyễn Văn A" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Email *</label>
                <input v-model="form.email" type="email" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" placeholder="VD: nguyenvana@congty.vn" />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Mật khẩu * (tối thiểu 8 ký tự)</label>
                <input v-model="form.password" type="password" required minlength="8" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Vai trò</label>
                <div class="space-y-2 rounded-lg border border-slate-200 p-3">
                    <label v-for="role in roles" :key="role.id" class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" :value="role.id" v-model="form.roles" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                        {{ role.label }}
                    </label>
                    <p v-if="!roles.length" class="text-sm text-slate-400">Chưa có vai trò nào.</p>
                </div>
                <p v-if="form.errors.roles" class="mt-1 text-sm text-red-600">{{ form.errors.roles }}</p>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" v-model="form.is_active" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                Tài khoản đang hoạt động (bỏ chọn để tạo tài khoản bị vô hiệu)
            </label>

            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" v-model="form.create_employee" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                Tạo luôn hồ sơ nhân viên
            </label>

            <div v-if="form.create_employee" class="space-y-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                <p class="text-sm font-medium text-slate-700">Hồ sơ nhân viên</p>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Phòng ban *</label>
                    <select v-model="form.employee.department_id" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">— Chọn phòng ban —</option>
                        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                    <p v-if="form.errors['employee.department_id']" class="mt-1 text-sm text-red-600">{{ form.errors['employee.department_id'] }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Chức danh</label>
                    <select v-model="form.employee.position_id" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">— Chưa chọn —</option>
                        <option v-for="p in positions" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                    <p v-if="form.errors['employee.position_id']" class="mt-1 text-sm text-red-600">{{ form.errors['employee.position_id'] }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Số điện thoại</label>
                    <input v-model="form.employee.phone" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" placeholder="VD: 0901234567" />
                    <p v-if="form.errors['employee.phone']" class="mt-1 text-sm text-red-600">{{ form.errors['employee.phone'] }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Ngày vào làm</label>
                    <input v-model="form.employee.hire_date" type="date" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors['employee.hire_date']" class="mt-1 text-sm text-red-600">{{ form.errors['employee.hire_date'] }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Lương cơ bản</label>
                    <NumberInput v-model="form.employee.base_salary" :min="0" placeholder="VD: 20000" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p class="mt-1 text-sm text-slate-500">Để 0 nếu chưa biết — lương 0 khiến bảng lương bằng 0 cho tới khi nhân sự cập nhật.</p>
                    <p v-if="form.errors['employee.base_salary']" class="mt-1 text-sm text-red-600">{{ form.errors['employee.base_salary'] }}</p>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <Link :href="route('admin.users.index')" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Hủy</Link>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                    {{ form.processing ? 'Đang lưu…' : 'Tạo tài khoản' }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import NumberInput from '../../../Components/NumberInput.vue';

const props = defineProps({
    roles: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    positions: { type: Array, default: () => [] },
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    roles: [],
    is_active: true,
    create_employee: false,
    employee: {
        department_id: '',
        position_id: '',
        phone: '',
        hire_date: '',
        base_salary: 0,
    },
});

// Role "Nhân viên" cần hồ sơ nhân viên để tự chấm công/nghỉ phép/xem
// phiếu lương, nên tự tick tạo kèm khi admin chọn role này (vẫn bỏ
// tick được nếu chỉ muốn tạo tài khoản).
const nhanVienRoleId = computed(() => props.roles.find((r) => r.name === 'nhan_vien')?.id);

watch(
    () => form.roles,
    (roles) => {
        if (nhanVienRoleId.value && roles.includes(nhanVienRoleId.value)) {
            form.create_employee = true;
        }
    },
);

// Bỏ tick thì xóa lỗi validate của nhóm hồ sơ để form không kẹt lỗi cũ.
watch(
    () => form.create_employee,
    (checked) => {
        if (!checked) {
            form.clearErrors('employee.department_id', 'employee.position_id', 'employee.phone', 'employee.hire_date', 'employee.base_salary');
        }
    },
);

function submit() {
    form.post(route('admin.users.store'));
}
</script>
