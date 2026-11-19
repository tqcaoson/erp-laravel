<template>
    <div class="mx-auto max-w-4xl space-y-4">
        <h1 class="text-xl font-bold text-slate-800">Sửa đơn bán hàng {{ order.code }}</h1>

        <form class="space-y-5 rounded-lg border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Khách hàng *</label>
                    <select v-model="form.customer_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">— Chọn khách hàng —</option>
                        <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <p v-if="form.errors.customer_id" class="mt-1 text-xs text-red-600">{{ form.errors.customer_id }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Kho giao hàng *</label>
                    <select v-model="form.warehouse_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" @change="reloadStock">
                        <option value="">— Chọn kho —</option>
                        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                    </select>
                    <p v-if="form.errors.warehouse_id" class="mt-1 text-xs text-red-600">{{ form.errors.warehouse_id }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Ngày đặt</label>
                    <input v-model="form.order_date" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
            </div>

            <!-- Hạn mức công nợ của khách đã chọn -->
            <div v-if="selectedCustomer" class="rounded-lg bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200">
                <p class="font-medium text-slate-700">Công nợ khách hàng: {{ selectedCustomer.name }}</p>
                <div class="mt-1 flex flex-wrap gap-x-6 gap-y-1 text-slate-600">
                    <span>
                        Hạn mức:
                        <strong>{{ selectedCustomer.credit_limit > 0 ? money(selectedCustomer.credit_limit) : 'Không giới hạn' }}</strong>
                    </span>
                    <span>
                        Công nợ hiện tại: <strong class="text-amber-700">{{ money(selectedCustomer.current_debt) }}</strong>
                    </span>
                    <span v-if="selectedCustomer.remaining_credit !== null">
                        Còn lại:
                        <strong :class="remainingAfterOrder < 0 ? 'text-red-600' : 'text-green-700'">
                            {{ money(remainingAfterOrder) }}
                        </strong>
                        <span v-if="remainingAfterOrder < 0" class="text-red-600">(đơn này vượt hạn mức — sẽ chuyển sang chờ duyệt)</span>
                    </span>
                </div>
            </div>

            <!-- Dòng sản phẩm -->
            <div>
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-700">Dòng sản phẩm</h2>
                    <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50" @click="addLine">
                        + Thêm dòng
                    </button>
                </div>

                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-slate-500">Sản phẩm</th>
                                <th class="px-3 py-2 text-right font-semibold text-slate-500">Tồn khả dụng</th>
                                <th class="px-3 py-2 text-right font-semibold text-slate-500">Số lượng</th>
                                <th class="px-3 py-2 text-right font-semibold text-slate-500">Đơn giá</th>
                                <th class="px-3 py-2 text-right font-semibold text-slate-500">CK %</th>
                                <th class="px-3 py-2 text-right font-semibold text-slate-500">Thuế %</th>
                                <th class="px-3 py-2 text-right font-semibold text-slate-500">Thành tiền</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(line, i) in form.lines" :key="line.id ?? `new-${i}`" :class="isShort(line) ? 'bg-amber-50' : ''">
                                <td class="px-3 py-2">
                                    <select v-model="line.product_id" required class="w-56 rounded border border-slate-300 px-2 py-1.5 focus:border-blue-500 focus:outline-none" @change="onProductChange(line)">
                                        <option value="">— Chọn —</option>
                                        <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option>
                                    </select>
                                    <p v-if="isShort(line)" class="mt-1 text-xs text-amber-700">Thiếu tồn — vẫn xác nhận được, kho sẽ giao phần đủ.</p>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    {{ availableOf(line) === null ? '—' : number(availableOf(line), 2) }}
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <NumberInput v-model="line.qty" :decimals="4" :min="0.0001" required placeholder="VD: 10" class="w-20 rounded border border-slate-300 px-2 py-1.5 text-right focus:border-blue-500 focus:outline-none" />
                                    <p v-if="form.errors[`lines.${i}.qty`]" class="mt-1 text-left text-xs text-red-600">{{ form.errors[`lines.${i}.qty`] }}</p>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <NumberInput v-model="line.unit_price" :min="0" required placeholder="VD: 20000" class="w-28 rounded border border-slate-300 px-2 py-1.5 text-right focus:border-blue-500 focus:outline-none" />
                                    <p v-if="form.errors[`lines.${i}.unit_price`]" class="mt-1 text-left text-xs text-red-600">{{ form.errors[`lines.${i}.unit_price`] }}</p>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <NumberInput v-model="line.discount_rate" :decimals="2" :min="0" :max="100" placeholder="0" class="w-16 rounded border border-slate-300 px-2 py-1.5 text-right focus:border-blue-500 focus:outline-none" />
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <NumberInput v-model="line.tax_rate" :decimals="2" :min="0" :max="100" placeholder="0" class="w-16 rounded border border-slate-300 px-2 py-1.5 text-right focus:border-blue-500 focus:outline-none" />
                                </td>
                                <td class="px-3 py-2 text-right font-medium">{{ money(lineTotal(line)) }}</td>
                                <td class="px-3 py-2 text-right">
                                    <button type="button" class="text-red-600 hover:underline" :disabled="form.lines.length <= 1" @click="removeLine(i)">
                                        Xóa
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="form.errors.lines" class="mt-1 text-xs text-red-600">{{ form.errors.lines }}</p>
                <p class="mt-1 text-xs text-slate-400">Nhập số, có thể dùng dấu . hoặc , phân cách nghìn (VD: 20.000 = 20000). Số lượng tối thiểu 0.0001.</p>
            </div>

            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Chiết khấu đơn hàng (₫)</label>
                    <NumberInput v-model="form.discount_amount" :min="0" placeholder="VD: 20000" class="w-48 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <label class="mt-3 mb-1 block text-sm font-medium text-slate-700">Ghi chú</label>
                    <input v-model="form.note" type="text" class="w-72 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <dl class="space-y-1 text-right text-sm">
                    <div class="flex justify-between gap-8"><dt class="text-slate-500">Tạm tính</dt><dd>{{ money(totals.subtotal) }}</dd></div>
                    <div class="flex justify-between gap-8"><dt class="text-slate-500">Thuế</dt><dd>{{ money(totals.tax) }}</dd></div>
                    <div class="flex justify-between gap-8"><dt class="text-slate-500">Chiết khấu</dt><dd>− {{ money(form.discount_amount || 0) }}</dd></div>
                    <div class="flex justify-between gap-8 border-t border-slate-200 pt-1 text-base font-bold"><dt>Tổng cộng</dt><dd>{{ money(totals.total) }}</dd></div>
                </dl>
            </div>

            <div class="flex gap-2">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    Lưu thay đổi
                </button>
                <Link :href="route('sales.orders.show', order.id)" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    Hủy
                </Link>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useFormat } from '../../../composables/useFormat';
import NumberInput from '../../../Components/NumberInput.vue';

const props = defineProps({
    order: { type: Object, required: true },
    customers: { type: Array, required: true },
    warehouses: { type: Array, required: true },
    products: { type: Array, required: true },
    stockMap: { type: Object, default: () => ({}) },
});

const { money, number } = useFormat();

// Tồn khả dụng theo kho của đơn — khi đổi kho sẽ tải lại qua route web /sales/stock-availability.
const stock = ref({ ...props.stockMap });

const emptyLine = () => ({ id: null, product_id: '', qty: 1, unit_price: 0, discount_rate: 0, tax_rate: 0 });

const form = useForm({
    customer_id: props.order.customer_id,
    warehouse_id: props.order.warehouse_id,
    order_date: props.order.order_date ? String(props.order.order_date).slice(0, 10) : '',
    discount_amount: Number(props.order.discount_amount || 0),
    note: props.order.note ?? '',
    // Giữ id dòng cũ để backend đồng bộ theo id (có id thì cập nhật,
    // không id thì thêm mới, dòng cũ vắng mặt thì xóa).
    lines: (props.order.lines ?? []).map((line) => ({
        id: line.id,
        product_id: line.product_id,
        qty: Number(line.qty),
        unit_price: Number(line.unit_price),
        discount_rate: Number(line.discount_rate || 0),
        tax_rate: Number(line.tax_rate || 0),
    })),
});

const selectedCustomer = computed(() => props.customers.find((c) => c.id === Number(form.customer_id)) ?? null);

function lineTotal(line) {
    const net = (line.qty || 0) * (line.unit_price || 0) * (1 - (line.discount_rate || 0) / 100);
    return net * (1 + (line.tax_rate || 0) / 100);
}

const totals = computed(() => {
    let subtotal = 0;
    let tax = 0;
    for (const line of form.lines) {
        const net = (line.qty || 0) * (line.unit_price || 0) * (1 - (line.discount_rate || 0) / 100);
        subtotal += net;
        tax += net * ((line.tax_rate || 0) / 100);
    }
    return { subtotal, tax, total: subtotal - (form.discount_amount || 0) + tax };
});

const remainingAfterOrder = computed(() => {
    if (!selectedCustomer.value || selectedCustomer.value.remaining_credit === null) return null;
    return selectedCustomer.value.remaining_credit - totals.value.total;
});

function availableOf(line) {
    if (!line.product_id) return null;
    const value = stock.value[line.product_id];
    return value === undefined || value === null ? null : Number(value);
}

function isShort(line) {
    const available = availableOf(line);
    return available !== null && (line.qty || 0) > available;
}

function onProductChange(line) {
    const product = props.products.find((p) => p.id === Number(line.product_id));
    if (product && product.sale_price) {
        line.unit_price = Number(product.sale_price);
    }
}

function addLine() {
    form.lines.push(emptyLine());
}

function removeLine(index) {
    if (form.lines.length > 1) form.lines.splice(index, 1);
}

async function reloadStock() {
    const productIds = form.lines.map((l) => l.product_id).filter(Boolean);
    if (!form.warehouse_id || productIds.length === 0) return;
    try {
        const params = new URLSearchParams({ warehouse_id: form.warehouse_id });
        productIds.forEach((id) => params.append('product_ids[]', id));
        const res = await fetch(`${route('sales.stock-availability')}?${params.toString()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (res.ok) {
            const json = await res.json();
            stock.value = { ...stock.value, ...json.data };
        }
    } catch (e) {
        // Không tải được tồn thì giữ số cũ, cột tồn hiển thị "—".
    }
}

function submit() {
    form.put(route('sales.orders.update', props.order.id));
}
</script>
