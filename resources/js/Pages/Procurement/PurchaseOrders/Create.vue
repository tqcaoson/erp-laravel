<template>
    <div class="mx-auto max-w-3xl space-y-4">
        <h1 class="text-xl font-semibold text-slate-800">Tạo đơn mua hàng</h1>
        <p v-if="fromRfq" class="rounded-lg bg-blue-50 px-4 py-2 text-sm text-blue-800 ring-1 ring-blue-200">
            Đang tạo đơn mua từ yêu cầu báo giá {{ fromRfq.code }} đã chấp nhận — nhà cung cấp và đơn giá đã được điền sẵn từ báo giá.
        </p>
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
                    <label class="mb-1 block text-sm font-medium text-slate-700">Từ yêu cầu mua (tùy chọn)</label>
                    <select v-model="form.purchase_request_id" @change="fillFromRequest" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option :value="null">— Nhập trực tiếp —</option>
                        <option v-for="r in purchaseRequests" :key="r.id" :value="r.id">{{ r.code }} ({{ money(r.total_amount) }})</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Ngày đặt hàng *</label>
                    <input v-model="form.order_date" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.order_date" class="mt-1 text-sm text-red-600">{{ form.errors.order_date }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Ngày giao dự kiến</label>
                    <input v-model="form.expected_date" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Thuế (tổng)</label>
                    <NumberInput v-model="form.tax_amount" :min="0" placeholder="VD: 20000" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.tax_amount" class="mt-1 text-sm text-red-600">{{ form.errors.tax_amount }}</p>
                    <p class="mt-1 text-xs text-slate-400">Nhập số, có thể dùng dấu . hoặc , phân cách nghìn.</p>
                </div>
            </div>
            <div class="overflow-hidden rounded-lg border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-500">Sản phẩm</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-slate-500">Số lượng</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-slate-500">Đơn giá</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-slate-500">Thành tiền</th>
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
                                <NumberInput v-model="line.qty" :decimals="4" :min="0.0001" placeholder="VD: 10" class="w-24 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm focus:border-blue-500 focus:outline-none" />
                                <p v-if="form.errors[`lines.${i}.qty`]" class="mt-1 text-left text-xs text-red-600">{{ form.errors[`lines.${i}.qty`] }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <NumberInput v-model="line.unit_price" :min="0" placeholder="VD: 20000" class="w-32 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm focus:border-blue-500 focus:outline-none" />
                                <p v-if="form.errors[`lines.${i}.unit_price`]" class="mt-1 text-left text-xs text-red-600">{{ form.errors[`lines.${i}.unit_price`] }}</p>
                            </td>
                            <td class="px-3 py-2 text-right text-sm">{{ money((Number(line.qty) || 0) * (Number(line.unit_price) || 0)) }}</td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" @click="form.lines.splice(i, 1)" class="text-sm font-medium text-red-600 hover:underline">Xóa</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="form.errors.lines" class="text-sm text-red-600">{{ form.errors.lines }}</p>
            <p class="text-xs text-slate-400">Nhập số, có thể dùng dấu . hoặc , phân cách nghìn (VD: 20.000 = 20000). Số lượng tối thiểu 0.0001.</p>
            <div class="flex items-center justify-between">
                <button type="button" @click="addLine" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">+ Thêm dòng</button>
                <p class="text-sm font-semibold text-slate-800">Tổng cộng: {{ money(totalAmount) }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Ghi chú</label>
                <textarea v-model="form.note" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"></textarea>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Tạo đơn mua</button>
                <Link :href="route('procurement.purchase-orders.index')" class="text-sm text-slate-600 hover:text-slate-800">Quay lại</Link>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import NumberInput from '../../../Components/NumberInput.vue';
import { useFormat } from '../../../composables/useFormat.js';

const props = defineProps({
    suppliers: { type: Array, required: true },
    products: { type: Array, required: true },
    purchaseRequests: { type: Array, required: true },
    fromRequest: { type: Object, default: null },
    fromRfq: { type: Object, default: null },
});

const { money } = useFormat();

// Điền sẵn dòng từ yêu cầu mua (PR) hoặc từ báo giá đã chấp nhận (RFQ) —
// cả hai prop có cùng dạng lines (product_id/qty/unit_price), RFQ dùng
// quoted_price làm unit_price. PR được ưu tiên nếu cả hai cùng có.
const prefillSource = props.fromRequest ?? props.fromRfq;

const initialLines = prefillSource
    ? prefillSource.lines.map((l) => ({ product_id: l.product_id, qty: Number(l.qty), unit_price: Number(l.unit_price) || 0 }))
    : [{ product_id: '', qty: null, unit_price: 0 }];

const form = useForm({
    supplier_id: props.fromRfq?.supplier_id ?? '',
    purchase_request_id: props.fromRequest?.id ?? null,
    order_date: new Date().toISOString().slice(0, 10),
    expected_date: '',
    tax_amount: 0,
    note: '',
    lines: initialLines,
});

const totalAmount = computed(
    () => form.lines.reduce((sum, l) => sum + (Number(l.qty) || 0) * (Number(l.unit_price) || 0), 0) + (Number(form.tax_amount) || 0),
);

function addLine() {
    form.lines.push({ product_id: '', qty: null, unit_price: 0 });
}

function fillPrice(line) {
    const product = props.products.find((p) => p.id === Number(line.product_id));
    if (product) line.unit_price = Number(product.average_cost) || 0;
}

function fillFromRequest() {
    if (!form.purchase_request_id) return;
    // Nạp lại trang với PR đã chọn để controller điền sẵn dòng.
    window.location.href = route('procurement.purchase-orders.create', { purchase_request_id: form.purchase_request_id });
}

function submit() {
    form.post(route('procurement.purchase-orders.store'));
}
</script>
