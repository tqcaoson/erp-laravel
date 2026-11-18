<template>
    <div class="mx-auto max-w-2xl space-y-4">
        <h1 class="text-xl font-semibold text-slate-800">Tạo phiếu kiểm kê</h1>
        <form @submit.prevent="submit" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Kho kiểm kê *</label>
                <select v-model="form.warehouse_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <option value="">— Chọn kho —</option>
                    <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
                <p v-if="form.errors.warehouse_id" class="mt-1 text-sm text-red-600">{{ form.errors.warehouse_id }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Sản phẩm cần kiểm (để trống = kiểm toàn bộ sản phẩm có tồn tại kho)</label>
                <div class="max-h-64 overflow-y-auto rounded-lg border border-slate-300 p-3">
                    <label v-for="p in products" :key="p.id" class="flex items-center gap-2 py-1 text-sm text-slate-700">
                        <input type="checkbox" :value="p.id" v-model="form.product_ids" class="h-4 w-4 rounded border-slate-300 text-blue-600" />
                        {{ p.sku }} — {{ p.name }}
                    </label>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Ghi chú</label>
                <textarea v-model="form.note" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"></textarea>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Tạo phiếu kiểm kê</button>
                <Link :href="route('inventory.stock-counts.index')" class="text-sm text-slate-600 hover:text-slate-800">Quay lại</Link>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';

defineProps({
    warehouses: { type: Array, required: true },
    products: { type: Array, required: true },
});

const form = useForm({ warehouse_id: '', product_ids: [], note: '' });

function submit() {
    form.post(route('inventory.stock-counts.store'));
}
</script>
