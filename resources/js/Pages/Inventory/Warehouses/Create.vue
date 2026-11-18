<template>
    <div class="mx-auto max-w-2xl space-y-4">
        <h1 class="text-xl font-semibold text-slate-800">Thêm kho</h1>
        <form @submit.prevent="submit" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Mã kho *</label>
                <input v-model="form.code" type="text" placeholder="VD: KHO-HN" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                <p v-if="form.errors.code" class="mt-1 text-sm text-red-600">{{ form.errors.code }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tên kho *</label>
                <input v-model="form.name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Địa chỉ</label>
                <input v-model="form.address" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700"><input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" /> Đang hoạt động</label>
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Lưu kho</button>
                <Link :href="route('inventory.warehouses.index')" class="text-sm text-slate-600 hover:text-slate-800">Quay lại</Link>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';

const form = useForm({ code: '', name: '', address: '', is_active: true });

function submit() {
    form.post(route('inventory.warehouses.store'));
}
</script>
