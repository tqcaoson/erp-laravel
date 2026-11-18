<template>
    <div class="mx-auto max-w-3xl space-y-4">
        <h1 class="text-xl font-semibold text-slate-800">Tạo yêu cầu mua hàng</h1>
        <p class="text-sm text-slate-500">Yêu cầu có tổng trên 50.000.000 ₫ sẽ chuyển sang chờ duyệt; ngược lại được duyệt tự động.</p>
        <form @submit.prevent="submit" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Ghi chú</label>
                <textarea v-model="form.note" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"></textarea>
            </div>
            <div class="overflow-hidden rounded-lg border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-500">Sản phẩm</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-slate-500">Số lượng</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-slate-500">Đơn giá ước tính</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-500">Kho cần hàng</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(line, i) in form.lines" :key="i">
                            <td class="px-3 py-2">
                                <select v-model="line.product_id" @change="fillPrice(line)" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                                    <option value="">— Chọn sản phẩm —</option>
                                    <option v-for="p in products" :key="p.id" :value="p.id">{{ p.sku }} — {{ p.name }}</option>
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <input v-model.number="line.qty" type="number" min="0" step="0.0001" class="w-24 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm focus:border-blue-500 focus:outline-none" />
                            </td>
                            <td class="px-3 py-2">
                                <input v-model.number="line.unit_price" type="number" min="0" step="0.01" class="w-32 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm focus:border-blue-500 focus:outline-none" />
                            </td>
                            <td class="px-3 py-2">
                                <select v-model="line.warehouse_id" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                                    <option :value="null">— Không chỉ định —</option>
                                    <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                                </select>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" @click="form.lines.splice(i, 1)" class="text-sm font-medium text-red-600 hover:underline">Xóa</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="form.errors.lines" class="text-sm text-red-600">{{ form.errors.lines }}</p>
            <div class="flex items-center justify-between">
                <button type="button" @click="addLine" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">+ Thêm dòng</button>
                <p class="text-sm font-semibold text-slate-800">Tổng ước tính: {{ money(totalAmount) }}</p>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Tạo yêu cầu mua</button>
                <Link :href="route('procurement.purchase-requests.index')" class="text-sm text-slate-600 hover:text-slate-800">Quay lại</Link>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useFormat } from '../../../composables/useFormat.js';

const props = defineProps({
    products: { type: Array, required: true },
    warehouses: { type: Array, required: true },
});

const { money } = useFormat();

const form = useForm({
    note: '',
    lines: [{ product_id: '', qty: null, unit_price: 0, warehouse_id: null, note: null }],
});

const totalAmount = computed(() => form.lines.reduce((sum, l) => sum + (Number(l.qty) || 0) * (Number(l.unit_price) || 0), 0));

function addLine() {
    form.lines.push({ product_id: '', qty: null, unit_price: 0, warehouse_id: null, note: null });
}

function fillPrice(line) {
    const product = props.products.find((p) => p.id === Number(line.product_id));
    if (product) line.unit_price = Number(product.average_cost) || 0;
}

function submit() {
    form.post(route('procurement.purchase-requests.store'));
}
</script>
