<template>
    <div class="mx-auto max-w-3xl space-y-4">
        <h1 class="text-xl font-bold text-slate-800">Tạo định mức (BOM)</h1>

        <form class="space-y-5 rounded-lg border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Mã BOM</label>
                    <input v-model="form.code" type="text" placeholder="Để trống để tự sinh" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Thành phẩm *</label>
                    <select v-model="form.product_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">— Chọn thành phẩm —</option>
                        <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option>
                    </select>
                    <p v-if="form.errors.product_id" class="mt-1 text-xs text-red-600">{{ form.errors.product_id }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Phiên bản</label>
                    <NumberInput v-model="form.version" :min="1" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-700">Nguyên liệu cho 1 đơn vị thành phẩm</h2>
                    <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50" @click="addLine">
                        + Thêm dòng
                    </button>
                </div>
                <div class="space-y-2">
                    <div v-for="(line, i) in form.lines" :key="i" class="flex flex-wrap items-end gap-2 rounded-lg border border-slate-200 p-3">
                        <div class="min-w-56 flex-1">
                            <label class="mb-1 block text-xs font-medium text-slate-500">Nguyên liệu / bán thành phẩm</label>
                            <select v-model="line.component_product_id" required class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                                <option value="">— Chọn —</option>
                                <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Số lượng</label>
                            <NumberInput v-model="line.qty" :decimals="4" :min="0.0001" required placeholder="VD: 10" class="w-24 rounded border border-slate-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Đơn vị</label>
                            <select v-model="line.unit_id" required class="w-28 rounded border border-slate-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                                <option value="">— Chọn —</option>
                                <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Hao hụt %</label>
                            <NumberInput v-model="line.scrap_rate" :decimals="2" :min="0" :max="100" placeholder="0" class="w-20 rounded border border-slate-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none" />
                        </div>
                        <button type="button" class="pb-1.5 text-sm text-red-600 hover:underline" :disabled="form.lines.length <= 1" @click="removeLine(i)">Xóa</button>
                    </div>
                </div>
                <p v-if="form.errors.lines" class="mt-1 text-xs text-red-600">{{ form.errors.lines }}</p>
<p class="mt-1 text-xs text-slate-400">Nhập số, có thể dùng dấu . hoặc , phân cách nghìn.</p>
                <p class="mt-2 text-xs text-slate-500">
                    Nguyên liệu là bán thành phẩm có BOM riêng sẽ được triển khai đệ quy khi kiểm tra tồn.
                </p>
            </div>

            <div class="flex gap-2">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    Tạo BOM
                </button>
                <Link :href="route('manufacturing.boms.index')" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    Hủy
                </Link>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import NumberInput from '../../../Components/NumberInput.vue';

defineProps({
    products: { type: Array, required: true },
    units: { type: Array, required: true },
});

const emptyLine = () => ({ component_product_id: '', qty: 1, unit_id: '', scrap_rate: 0 });

const form = useForm({
    code: '',
    product_id: '',
    version: 1,
    is_active: true,
    note: '',
    lines: [emptyLine()],
});

function addLine() {
    form.lines.push(emptyLine());
}

function removeLine(index) {
    if (form.lines.length > 1) form.lines.splice(index, 1);
}

function submit() {
    form.post(route('manufacturing.boms.store'));
}
</script>
