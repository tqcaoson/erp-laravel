<template>
    <div class="mx-auto max-w-3xl space-y-4">
        <h1 class="text-xl font-semibold text-slate-800">Tạo yêu cầu báo giá</h1>
        <form @submit.prevent="submit" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nhà cung cấp *</label>
                    <select v-model="form.supplier_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">— Chọn nhà cung cấp —</option>
                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.code }} — {{ s.name }}</option>
                    </select>
                    <p v-if="form.errors.supplier_id" class="mt-1 text-sm text-red-600">{{ form.errors.supplier_id }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Hạn báo giá</label>
                    <input v-model="form.deadline" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
            </div>
            <div class="overflow-hidden rounded-lg border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-500">Sản phẩm</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-slate-500">Số lượng</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-slate-500">Giá chào (nếu có)</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(line, i) in form.lines" :key="i">
                            <td class="px-3 py-2">
                                <select v-model="line.product_id" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                                    <option value="">— Chọn sản phẩm —</option>
                                    <option v-for="p in products" :key="p.id" :value="p.id">{{ p.sku }} — {{ p.name }}</option>
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <NumberInput v-model="line.qty" :decimals="4" :min="0.0001" placeholder="VD: 10" class="w-24 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm focus:border-blue-500 focus:outline-none" />
                                <p v-if="form.errors[`lines.${i}.qty`]" class="mt-1 text-left text-xs text-red-600">{{ form.errors[`lines.${i}.qty`] }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <NumberInput v-model="line.quoted_price" :min="0" placeholder="VD: 20000" class="w-32 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm focus:border-blue-500 focus:outline-none" />
                                <p v-if="form.errors[`lines.${i}.quoted_price`]" class="mt-1 text-left text-xs text-red-600">{{ form.errors[`lines.${i}.quoted_price`] }}</p>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" @click="form.lines.splice(i, 1)" class="text-sm font-medium text-red-600 hover:underline">Xóa</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="form.errors.lines" class="text-sm text-red-600">{{ form.errors.lines }}</p>
            <p class="text-xs text-slate-400">Nhập số, có thể dùng dấu . hoặc , phân cách nghìn (VD: 20.000 = 20000). Số lượng tối thiểu 0.0001.</p>
            <button type="button" @click="form.lines.push({ product_id: '', qty: null, quoted_price: null })" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">+ Thêm dòng</button>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Ghi chú</label>
                <textarea v-model="form.note" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"></textarea>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Tạo yêu cầu báo giá</button>
                <Link :href="route('procurement.rfqs.index')" class="text-sm text-slate-600 hover:text-slate-800">Quay lại</Link>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import NumberInput from '../../../Components/NumberInput.vue';

defineProps({
    suppliers: { type: Array, required: true },
    products: { type: Array, required: true },
});

const form = useForm({
    supplier_id: '', deadline: '', note: '',
    lines: [{ product_id: '', qty: null, quoted_price: null }],
});

function submit() {
    form.post(route('procurement.rfqs.store'));
}
</script>
