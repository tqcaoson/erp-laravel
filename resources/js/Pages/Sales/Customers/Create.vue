<template>
    <div class="mx-auto max-w-2xl space-y-4">
        <h1 class="text-xl font-bold text-slate-800">{{ customer ? 'Sửa khách hàng' : 'Thêm khách hàng' }}</h1>

        <form class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Mã khách hàng</label>
                    <input v-model="form.code" type="text" placeholder="Để trống để tự sinh" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Tên khách hàng *</label>
                    <input v-model="form.name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Mã số thuế</label>
                    <input v-model="form.tax_code" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Điện thoại</label>
                    <input v-model="form.phone" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                    <input v-model="form.email" type="email" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Hạn mức công nợ (₫, 0 = không giới hạn)</label>
                    <NumberInput v-model="form.credit_limit" :min="0" placeholder="VD: 20000" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p class="mt-1 text-xs text-slate-400">Nhập số, có thể dùng dấu . hoặc , phân cách nghìn.</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Hạn thanh toán (ngày)</label>
                    <NumberInput v-model="form.payment_term_days" :min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div class="flex items-end gap-2 pb-2">
                    <input id="is_active" v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                    <label for="is_active" class="text-sm text-slate-700">Đang hoạt động</label>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Địa chỉ</label>
                <textarea v-model="form.address" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"></textarea>
            </div>

            <div class="flex gap-2">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    {{ customer ? 'Cập nhật' : 'Tạo khách hàng' }}
                </button>
                <Link :href="route('sales.customers.index')" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    Hủy
                </Link>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import NumberInput from '../../../Components/NumberInput.vue';

const props = defineProps({
    customer: { type: Object, default: null },
});

const form = useForm({
    code: props.customer?.code ?? '',
    name: props.customer?.name ?? '',
    tax_code: props.customer?.tax_code ?? '',
    phone: props.customer?.phone ?? '',
    email: props.customer?.email ?? '',
    address: props.customer?.address ?? '',
    credit_limit: props.customer?.credit_limit ?? 0,
    payment_term_days: props.customer?.payment_term_days ?? 0,
    is_active: props.customer?.is_active ?? true,
});

function submit() {
    if (props.customer) {
        form.put(route('sales.customers.update', props.customer.id));
    } else {
        form.post(route('sales.customers.store'));
    }
}
</script>
