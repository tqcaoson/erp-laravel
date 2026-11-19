<template>
    <div class="mx-auto max-w-3xl space-y-4">
        <h1 class="text-xl font-bold text-slate-800">Tạo quy trình sản xuất</h1>

        <form class="space-y-5 rounded-lg border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Mã quy trình</label>
                    <input v-model="form.code" type="text" placeholder="Để trống để tự sinh" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Tên quy trình *</label>
                    <input v-model="form.name" type="text" required placeholder="VD: Quy trình đóng chai nước ép" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Sản phẩm áp dụng</label>
                    <select v-model="form.product_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">— Dùng chung cho nhiều sản phẩm —</option>
                        <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option>
                    </select>
                </div>
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-700">Công đoạn theo thứ tự</h2>
                    <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50" @click="addOperation">
                        + Thêm công đoạn
                    </button>
                </div>
                <div class="space-y-2">
                    <div v-for="(op, i) in form.operations" :key="i" class="flex flex-wrap items-end gap-2 rounded-lg border border-slate-200 p-3">
                        <span class="pb-1.5 text-sm font-semibold text-slate-400">#{{ i + 1 }}</span>
                        <div class="min-w-48 flex-1">
                            <label class="mb-1 block text-xs font-medium text-slate-500">Tên công đoạn *</label>
                            <input v-model="op.operation_name" type="text" required placeholder="VD: Rửa chai" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none" />
                        </div>
                        <div class="min-w-48 flex-1">
                            <label class="mb-1 block text-xs font-medium text-slate-500">Trung tâm sản xuất *</label>
                            <select v-model="op.work_center_id" required class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                                <option value="">— Chọn —</option>
                                <option v-for="w in workCenters" :key="w.id" :value="w.id">{{ w.name }} ({{ w.code }})</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Thời gian chuẩn (phút)</label>
                            <input v-model.number="op.standard_minutes" type="number" min="0" step="0.01" class="w-28 rounded border border-slate-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none" />
                        </div>
                        <button type="button" class="pb-1.5 text-sm text-red-600 hover:underline" :disabled="form.operations.length <= 1" @click="removeOperation(i)">Xóa</button>
                    </div>
                </div>
                <p v-if="form.errors.operations" class="mt-1 text-xs text-red-600">{{ form.errors.operations }}</p>
                <p class="mt-2 text-xs text-slate-500">
                    Thứ tự công đoạn là thứ tự hiển thị ở đây. Thời gian chuẩn dùng để tính
                    phút kế hoạch cho công đoạn khi lệnh sản xuất bắt đầu.
                </p>
            </div>

            <div class="flex gap-2">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    Tạo quy trình
                </button>
                <Link :href="route('manufacturing.routings.index')" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    Hủy
                </Link>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';

defineProps({
    products: { type: Array, required: true },
    workCenters: { type: Array, required: true },
});

const emptyOperation = () => ({ work_center_id: '', operation_name: '', standard_minutes: 0 });

const form = useForm({
    code: '',
    name: '',
    product_id: '',
    operations: [emptyOperation()],
});

function addOperation() {
    form.operations.push(emptyOperation());
}

function removeOperation(index) {
    if (form.operations.length > 1) form.operations.splice(index, 1);
}

function submit() {
    form.post(route('manufacturing.routings.store'));
}
</script>
