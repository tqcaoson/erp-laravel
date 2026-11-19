<template>
    <div class="mx-auto max-w-2xl space-y-4">
        <h1 class="text-xl font-semibold text-slate-800">Phiếu nhập / xuất / chuyển kho</h1>
        <form @submit.prevent="submit" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Loại phiếu *</label>
                <select v-model="form.type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <option value="receive">Nhập kho</option>
                    <option value="issue">Xuất kho</option>
                    <option value="transfer">Chuyển kho</option>
                </select>
                <p v-if="form.errors.type" class="mt-1 text-sm text-red-600">{{ form.errors.type }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Sản phẩm *</label>
                <select v-model="form.product_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <option value="">— Chọn sản phẩm —</option>
                    <option v-for="p in products" :key="p.id" :value="p.id">{{ p.sku }} — {{ p.name }} (giá vốn {{ money(p.average_cost) }})</option>
                </select>
                <p v-if="form.errors.product_id" class="mt-1 text-sm text-red-600">{{ form.errors.product_id }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ form.type === 'transfer' ? 'Kho nguồn *' : 'Kho *' }}</label>
                    <select v-model="form.warehouse_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">— Chọn kho —</option>
                        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                    </select>
                    <p v-if="form.errors.warehouse_id" class="mt-1 text-sm text-red-600">{{ form.errors.warehouse_id }}</p>
                </div>
                <div v-if="form.type === 'transfer'">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Kho đích *</label>
                    <select v-model="form.to_warehouse_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">— Chọn kho đích —</option>
                        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                    </select>
                    <p v-if="form.errors.to_warehouse_id" class="mt-1 text-sm text-red-600">{{ form.errors.to_warehouse_id }}</p>
                </div>
                <div v-if="form.type !== 'transfer'">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Vị trí (tùy chọn)</label>
                    <select v-model="form.location_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option :value="null">— Không gắn vị trí —</option>
                        <option v-for="l in locationsOf(form.warehouse_id)" :key="l.id" :value="l.id">{{ l.code }} — {{ l.name }}</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Số lượng *</label>
                    <NumberInput v-model="form.qty" :decimals="4" :min="0.0001" placeholder="VD: 10" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.qty" class="mt-1 text-sm text-red-600">{{ form.errors.qty }}</p>
                    <p class="mt-1 text-xs text-slate-400">Nhập số, có thể dùng dấu . hoặc , phân cách nghìn. Số lượng tối thiểu 0.0001.</p>
                </div>
                <div v-if="form.type === 'receive'">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Giá nhập (đơn vị)</label>
                    <NumberInput v-model="form.unit_cost" :min="0" placeholder="VD: 20000" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.unit_cost" class="mt-1 text-sm text-red-600">{{ form.errors.unit_cost }}</p>
                    <p class="mt-1 text-xs text-slate-400">Giá vốn bình quân sẽ được tính lại sau khi nhập.</p>
                </div>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    {{ form.processing ? 'Đang ghi…' : 'Ghi phiếu kho' }}
                </button>
                <Link :href="route('inventory.stock.index')" class="text-sm text-slate-600 hover:text-slate-800">Quay lại tồn kho</Link>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import NumberInput from '../../../Components/NumberInput.vue';
import { useFormat } from '../../../composables/useFormat.js';

const props = defineProps({
    products: { type: Array, required: true },
    warehouses: { type: Array, required: true },
    locations: { type: Array, required: true },
});

const { money } = useFormat();

const form = useForm({
    type: 'receive', product_id: '', warehouse_id: '', to_warehouse_id: '',
    location_id: null, to_location_id: null, qty: null, unit_cost: 0,
});

function locationsOf(warehouseId) {
    if (!warehouseId) return props.locations;
    return props.locations.filter((l) => l.warehouse_id === Number(warehouseId));
}

function submit() {
    form.post(route('inventory.stock.move'));
}
</script>
