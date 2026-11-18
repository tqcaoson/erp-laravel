<template>
    <div class="mx-auto max-w-3xl space-y-4">
        <h1 class="text-xl font-semibold text-slate-800">Tạo phiếu nhận hàng</h1>
        <form @submit.prevent="submit" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Đơn mua hàng (tùy chọn)</label>
                    <select v-model="form.purchase_order_id" @change="fillFromOrder" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option :value="null">— Nhận hàng không theo đơn —</option>
                        <option v-for="o in purchaseOrders" :key="o.id" :value="o.id">{{ o.code }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Ngày nhận hàng *</label>
                    <input v-model="form.receipt_date" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.receipt_date" class="mt-1 text-sm text-red-600">{{ form.errors.receipt_date }}</p>
                </div>
            </div>
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
                    <label class="mb-1 block text-sm font-medium text-slate-700">Kho nhận hàng *</label>
                    <select v-model="form.warehouse_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">— Chọn kho —</option>
                        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                    </select>
                    <p v-if="form.errors.warehouse_id" class="mt-1 text-sm text-red-600">{{ form.errors.warehouse_id }}</p>
                </div>
            </div>
            <div class="overflow-hidden rounded-lg border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-500">Sản phẩm</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-slate-500">Số lượng nhận</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-slate-500">Giá nhập</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(line, i) in form.lines" :key="i">
                            <td class="px-3 py-2">
                                <select v-model="line.product_id" @change="fillCost(line)" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                                    <option value="">— Chọn sản phẩm —</option>
                                    <option v-for="p in products" :key="p.id" :value="p.id">{{ p.sku }} — {{ p.name }}</option>
                                </select>
                                <p v-if="line.remaining !== undefined" class="mt-1 text-xs text-slate-500">Đơn mua còn thiếu: {{ number(line.remaining, 2) }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <input v-model.number="line.qty" type="number" min="0" step="0.0001" class="w-24 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm focus:border-blue-500 focus:outline-none" />
                            </td>
                            <td class="px-3 py-2">
                                <input v-model.number="line.unit_cost" type="number" min="0" step="0.01" class="w-32 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm focus:border-blue-500 focus:outline-none" />
                            </td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" @click="form.lines.splice(i, 1)" class="text-sm font-medium text-red-600 hover:underline">Xóa</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="form.errors.lines" class="text-sm text-red-600">{{ form.errors.lines }}</p>
            <button type="button" @click="addLine" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">+ Thêm dòng</button>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Ghi chú</label>
                <textarea v-model="form.note" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"></textarea>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Tạo phiếu nhận hàng</button>
                <Link :href="route('procurement.goods-receipts.index')" class="text-sm text-slate-600 hover:text-slate-800">Quay lại</Link>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { useFormat } from '../../../composables/useFormat.js';

const props = defineProps({
    suppliers: { type: Array, required: true },
    warehouses: { type: Array, required: true },
    products: { type: Array, required: true },
    purchaseOrders: { type: Array, required: true },
    fromOrder: { type: Object, default: null },
});

const { number } = useFormat();

const initialLines = props.fromOrder
    ? props.fromOrder.lines
        .filter((l) => Number(l.qty) - Number(l.received_qty) > 0)
        .map((l) => ({
            product_id: l.product_id,
            qty: Number(l.qty) - Number(l.received_qty),
            unit_cost: Number(l.unit_price),
            purchase_order_line_id: l.id,
            remaining: Number(l.qty) - Number(l.received_qty),
        }))
    : [{ product_id: '', qty: null, unit_cost: 0, purchase_order_line_id: null }];

const form = useForm({
    purchase_order_id: props.fromOrder?.id ?? null,
    supplier_id: props.fromOrder?.supplier_id ?? '',
    warehouse_id: '',
    receipt_date: new Date().toISOString().slice(0, 10),
    note: '',
    lines: initialLines,
});

function addLine() {
    form.lines.push({ product_id: '', qty: null, unit_cost: 0, purchase_order_line_id: null });
}

function fillCost(line) {
    const product = props.products.find((p) => p.id === Number(line.product_id));
    if (product) line.unit_cost = Number(product.average_cost) || 0;
}

function fillFromOrder() {
    if (!form.purchase_order_id) return;
    window.location.href = route('procurement.goods-receipts.create', { purchase_order_id: form.purchase_order_id });
}

function submit() {
    form.post(route('procurement.goods-receipts.store'));
}
</script>
