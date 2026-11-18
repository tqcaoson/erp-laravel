<template>
    <div class="mx-auto max-w-2xl space-y-4 p-4 sm:p-6">
        <h1 class="text-xl font-semibold text-slate-800">Thêm tài khoản</h1>

        <form @submit.prevent="submit" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Mã tài khoản *</label>
                <input v-model="form.code" type="text" placeholder="Ví dụ: 1311" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                <p v-if="form.errors.code" class="mt-1 text-sm text-red-600">{{ form.errors.code }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tên tài khoản *</label>
                <input v-model="form.name" type="text" placeholder="Ví dụ: Phải thu khách hàng trong nước" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Loại tài khoản *</label>
                <select v-model="form.type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <option value="">— Chọn loại —</option>
                    <option v-for="(label, value) in types" :key="value" :value="value">{{ label }}</option>
                </select>
                <p v-if="form.errors.type" class="mt-1 text-sm text-red-600">{{ form.errors.type }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tài khoản cha (để trống nếu là tài khoản cấp 1)</label>
                <select v-model="form.parent_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <option :value="null">— Không có (cấp 1) —</option>
                    <option v-for="parent in parents" :key="parent.id" :value="parent.id">{{ parent.code }} — {{ parent.name }}</option>
                </select>
                <p v-if="form.errors.parent_id" class="mt-1 text-sm text-red-600">{{ form.errors.parent_id }}</p>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    Lưu tài khoản
                </button>
                <Link :href="route('accounting.chart-of-accounts.index')" class="text-sm text-slate-600 hover:text-slate-800">Quay lại</Link>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';

defineProps({
    parents: { type: Array, required: true },
    types: { type: Object, required: true },
});

const form = useForm({
    code: '',
    name: '',
    type: '',
    parent_id: null,
    is_active: true,
});

function submit() {
    form.post(route('accounting.chart-of-accounts.store'));
}
</script>
