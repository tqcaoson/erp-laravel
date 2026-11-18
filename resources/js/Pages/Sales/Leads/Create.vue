<template>
    <div class="mx-auto max-w-2xl space-y-4">
        <h1 class="text-xl font-bold text-slate-800">Thêm đầu mối</h1>

        <form class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Tên liên hệ *</label>
                    <input v-model="form.name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Công ty</label>
                    <input v-model="form.company_name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Điện thoại</label>
                    <input v-model="form.phone" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                    <input v-model="form.email" type="email" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nguồn</label>
                    <input v-model="form.source" type="text" placeholder="VD: Facebook, giới thiệu, hội chợ…" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Ghi chú</label>
                <textarea v-model="form.note" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"></textarea>
            </div>

            <div class="flex gap-2">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    Tạo đầu mối
                </button>
                <Link :href="route('sales.leads.index')" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    Hủy
                </Link>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    company_name: '',
    phone: '',
    email: '',
    source: '',
    note: '',
});

function submit() {
    form.post(route('sales.leads.store'));
}
</script>
