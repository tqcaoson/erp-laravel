<template>
    <div class="mx-auto max-w-2xl space-y-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Đăng ký nghỉ phép</h1>
            <p v-if="employee" class="mt-1 text-sm text-slate-500">
                {{ employee.full_name }} ({{ employee.code }}) — còn {{ remainingLeaveDays }} ngày phép năm.
                Phép năm vượt số ngày còn lại sẽ bị từ chối. Nghỉ ốm và nghỉ không lương không trừ phép năm.
            </p>
        </div>

        <div v-if="!employee" class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
            Tài khoản chưa gắn với hồ sơ nhân viên nên không đăng ký nghỉ phép được. Vui lòng liên hệ phòng nhân sự để được gắn hồ sơ.
        </div>

        <form v-else @submit.prevent="submit" class="space-y-4 rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Loại nghỉ *</label>
                <select v-model="form.leave_type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <option value="annual">Phép năm (trừ số dư phép)</option>
                    <option value="sick">Nghỉ ốm</option>
                    <option value="unpaid">Nghỉ không lương</option>
                </select>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Từ ngày *</label>
                    <input v-model="form.start_date" type="date" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Đến ngày *</label>
                    <input v-model="form.end_date" type="date" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.end_date" class="mt-1 text-sm text-red-600">{{ form.errors.end_date }}</p>
                </div>
            </div>

            <p v-if="dayCount > 0" class="text-sm text-slate-600">Số ngày nghỉ (tính cả ngày đầu và cuối): <span class="font-semibold text-slate-900">{{ dayCount }} ngày</span></p>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Lý do</label>
                <textarea v-model="form.reason" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" placeholder="VD: Nghỉ phép năm về quê" />
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <Link :href="route('my.leave-requests.index')" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Hủy</Link>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                    {{ form.processing ? 'Đang gửi…' : 'Gửi đơn nghỉ' }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    employee: { type: Object, default: null },
    remainingLeaveDays: { type: Number, default: null },
});

const form = useForm({
    leave_type: 'annual',
    start_date: '',
    end_date: '',
    reason: '',
});

const dayCount = computed(() => {
    if (!form.start_date || !form.end_date) return 0;
    const diff = (new Date(form.end_date) - new Date(form.start_date)) / 86400000 + 1;
    return diff > 0 ? diff : 0;
});

function submit() {
    form.post(route('my.leave-requests.store'));
}
</script>
