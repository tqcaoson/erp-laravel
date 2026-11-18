<template>
    <div class="mx-auto max-w-2xl space-y-4">
        <h1 class="text-xl font-semibold text-slate-800">Thêm sản phẩm</h1>
        <form @submit.prevent="submit" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Mã sản phẩm (SKU) *</label>
                    <input v-model="form.sku" type="text" placeholder="VD: SP-001" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.sku" class="mt-1 text-sm text-red-600">{{ form.errors.sku }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Tên sản phẩm *</label>
                    <input v-model="form.name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Đơn vị tính *</label>
                    <select v-model="form.uom_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">— Chọn đơn vị —</option>
                        <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }} ({{ u.code }})</option>
                    </select>
                    <p v-if="form.errors.uom_id" class="mt-1 text-sm text-red-600">{{ form.errors.uom_id }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Phương pháp tính giá vốn</label>
                    <select v-model="form.cost_method" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="avg">Bình quân gia quyền</option>
                        <option value="fifo">FIFO (nhập trước xuất trước)</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Giá bán</label>
                    <input v-model.number="form.sale_price" type="number" min="0" step="0.01" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Giá vốn ban đầu</label>
                    <input v-model.number="form.average_cost" type="number" min="0" step="0.01" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Tồn tối thiểu</label>
                    <input v-model.number="form.reorder_min" type="number" min="0" step="0.0001" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Mô tả</label>
                <textarea v-model="form.description" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"></textarea>
            </div>
            <div class="flex gap-6">
                <label class="flex items-center gap-2 text-sm text-slate-700"><input v-model="form.track_lot" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" /> Theo dõi lô</label>
                <label class="flex items-center gap-2 text-sm text-slate-700"><input v-model="form.track_serial" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" /> Theo dõi serial</label>
                <label class="flex items-center gap-2 text-sm text-slate-700"><input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" /> Đang hoạt động</label>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Lưu sản phẩm</button>
                <Link :href="route('inventory.products.index')" class="text-sm text-slate-600 hover:text-slate-800">Quay lại</Link>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';

defineProps({ units: { type: Array, required: true } });

const form = useForm({
    sku: '', name: '', uom_id: '', cost_method: 'avg',
    track_lot: false, track_serial: false,
    reorder_min: 0, sale_price: 0, average_cost: 0,
    description: '', is_active: true,
});

function submit() {
    form.post(route('inventory.products.store'));
}
</script>
