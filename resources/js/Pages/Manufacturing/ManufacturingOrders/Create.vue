<template>
    <div class="mx-auto max-w-2xl space-y-4">
        <h1 class="text-xl font-bold text-slate-800">Tạo lệnh sản xuất</h1>

        <form class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Thành phẩm *</label>
                    <select v-model="form.product_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" @change="onProductChange">
                        <option value="">— Chọn thành phẩm —</option>
                        <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option>
                    </select>
                    <p v-if="form.errors.product_id" class="mt-1 text-xs text-red-600">{{ form.errors.product_id }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Số lượng *</label>
                    <NumberInput v-model="form.qty" :decimals="4" :min="0.0001" required placeholder="VD: 10" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.qty" class="mt-1 text-xs text-red-600">{{ form.errors.qty }}</p>
<p class="mt-1 text-xs text-slate-400">Nhập số, có thể dùng dấu . hoặc , phân cách nghìn.</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Kho *</label>
                    <select v-model="form.warehouse_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">— Chọn kho —</option>
                        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                    </select>
                    <p v-if="form.errors.warehouse_id" class="mt-1 text-xs text-red-600">{{ form.errors.warehouse_id }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Định mức (BOM)</label>
                    <select v-model="form.bom_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">— Tự chọn BOM đang hoạt động —</option>
                        <option v-for="b in filteredBoms" :key="b.id" :value="b.id">{{ b.code }} (phiên bản {{ b.version }})</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Quy trình (routing)</label>
                    <select v-model="form.routing_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">— Không dùng routing —</option>
                        <option v-for="r in filteredRoutings" :key="r.id" :value="r.id">{{ r.name }} ({{ r.code }})</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Đơn bán liên quan</label>
                    <select v-model="form.sales_order_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">— Không gắn —</option>
                        <option v-for="o in salesOrders" :key="o.id" :value="o.id">{{ o.code }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Bắt đầu dự kiến</label>
                    <input v-model="form.planned_start_date" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Kết thúc dự kiến</label>
                    <input v-model="form.planned_end_date" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Ghi chú</label>
                <textarea v-model="form.note" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"></textarea>
            </div>

            <div class="flex gap-2">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    Tạo lệnh sản xuất
                </button>
                <Link :href="route('manufacturing.orders.index')" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    Hủy
                </Link>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import NumberInput from '../../../Components/NumberInput.vue';
import { computed } from 'vue';

const props = defineProps({
    products: { type: Array, required: true },
    warehouses: { type: Array, required: true },
    boms: { type: Array, default: () => [] },
    routings: { type: Array, default: () => [] },
    salesOrders: { type: Array, default: () => [] },
});

const form = useForm({
    product_id: '',
    bom_id: '',
    routing_id: '',
    sales_order_id: '',
    warehouse_id: props.warehouses[0]?.id ?? '',
    qty: 1,
    planned_start_date: '',
    planned_end_date: '',
    note: '',
});

// Chỉ hiện BOM/routing của thành phẩm đã chọn (routing chung thì hiện tất cả).
const filteredBoms = computed(() => props.boms.filter((b) => !form.product_id || b.product_id === Number(form.product_id)));
const filteredRoutings = computed(() => props.routings.filter((r) => !r.product_id || !form.product_id || r.product_id === Number(form.product_id)));

function onProductChange() {
    form.bom_id = '';
    form.routing_id = '';
    // Tự chọn BOM đang hoạt động đầu tiên của thành phẩm (nếu có).
    if (filteredBoms.value.length === 1) {
        form.bom_id = filteredBoms.value[0].id;
    }
}

function submit() {
    form.post(route('manufacturing.orders.store'));
}
</script>
