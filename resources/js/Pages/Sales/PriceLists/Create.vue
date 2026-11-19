<template>
    <div class="mx-auto max-w-3xl space-y-4">
        <h1 class="text-xl font-bold text-slate-800">Tạo bảng giá</h1>

        <form class="space-y-5 rounded-lg border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Mã bảng giá</label>
                    <input v-model="form.code" type="text" placeholder="Để trống để tự sinh" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Tên bảng giá *</label>
                    <input v-model="form.name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                </div>
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-700">Giá theo sản phẩm</h2>
                    <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50" @click="addItem">
                        + Thêm dòng
                    </button>
                </div>
                <div class="space-y-2">
                    <div v-for="(item, i) in form.items" :key="i" class="flex flex-wrap items-end gap-2 rounded-lg border border-slate-200 p-3">
                        <div class="min-w-56 flex-1">
                            <label class="mb-1 block text-xs font-medium text-slate-500">Sản phẩm</label>
                            <select v-model="item.product_id" required class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                                <option value="">— Chọn —</option>
                                <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Giá (₫)</label>
                            <NumberInput v-model="item.price" :min="0" required placeholder="VD: 20000" class="w-32 rounded border border-slate-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">SL tối thiểu</label>
                            <NumberInput v-model="item.min_qty" :decimals="4" :min="0.0001" placeholder="VD: 10" class="w-24 rounded border border-slate-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none" />
                        </div>
                        <button type="button" class="pb-1.5 text-sm text-red-600 hover:underline" @click="removeItem(i)">Xóa</button>
                    </div>
                </div>
            </div>

            <p class="mt-1 text-xs text-slate-400">Nhập số, có thể dùng dấu . hoặc , phân cách nghìn (VD: 20.000 = 20000).</p>

            <div class="flex gap-2">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    Tạo bảng giá
                </button>
                <Link :href="route('sales.price-lists.index')" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    Hủy
                </Link>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import NumberInput from '../../../Components/NumberInput.vue';

defineProps({ products: { type: Array, required: true } });

const emptyItem = () => ({ product_id: '', price: 0, min_qty: 1 });

const form = useForm({
    code: '',
    name: '',
    is_active: true,
    items: [emptyItem()],
});

function addItem() {
    form.items.push(emptyItem());
}

function removeItem(index) {
    form.items.splice(index, 1);
}

function submit() {
    form.post(route('sales.price-lists.store'));
}
</script>
