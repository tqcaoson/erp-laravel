<template>
    <div class="mx-auto max-w-3xl space-y-4">
        <h1 class="text-xl font-bold text-slate-800">Sửa công đoạn — {{ workOrder.operation_name }}</h1>

        <form class="space-y-5 rounded-lg border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Lệnh sản xuất</label>
                    <input :value="workOrder.manufacturing_order?.code ?? '—'" type="text" disabled class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500" />
                    <p class="mt-1 text-xs text-slate-400">Công đoạn không chuyển sang lệnh khác được.</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Tên công đoạn *</label>
                    <input v-model="form.operation_name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.operation_name" class="mt-1 text-xs text-red-600">{{ form.errors.operation_name }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Trung tâm sản xuất *</label>
                    <select v-model="form.work_center_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">— Chọn —</option>
                        <option v-for="w in workCenters" :key="w.id" :value="w.id">{{ w.name }} ({{ w.code }})</option>
                    </select>
                    <p v-if="form.errors.work_center_id" class="mt-1 text-xs text-red-600">{{ form.errors.work_center_id }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Thứ tự trong lệnh *</label>
                    <input v-model.number="form.sequence" type="number" min="1" step="1" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.sequence" class="mt-1 text-xs text-red-600">{{ form.errors.sequence }}</p>
                    <p class="mt-1 text-xs text-slate-400">Thứ tự phải khác với các công đoạn khác trong lệnh.</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Phút chuẩn *</label>
                    <NumberInput v-model="form.planned_minutes" :decimals="2" :min="0" required placeholder="VD: 15" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.planned_minutes" class="mt-1 text-xs text-red-600">{{ form.errors.planned_minutes }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Phút thực tế</label>
                    <NumberInput v-model="form.actual_minutes" :decimals="2" :min="0" placeholder="Để trống nếu chưa làm" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.actual_minutes" class="mt-1 text-xs text-red-600">{{ form.errors.actual_minutes }}</p>
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    Lưu thay đổi
                </button>
                <Link :href="route('manufacturing.work-orders.index', { 'filter[manufacturing_order_id]': workOrder.manufacturing_order_id })" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    Hủy
                </Link>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import NumberInput from '../../../Components/NumberInput.vue';

const props = defineProps({
    workOrder: { type: Object, required: true },
    workCenters: { type: Array, required: true },
});

const form = useForm({
    operation_name: props.workOrder.operation_name,
    work_center_id: props.workOrder.work_center_id,
    sequence: props.workOrder.sequence,
    planned_minutes: props.workOrder.planned_minutes === null ? 0 : Number(props.workOrder.planned_minutes),
    actual_minutes: props.workOrder.actual_minutes === null ? null : Number(props.workOrder.actual_minutes),
});

function submit() {
    form.put(route('manufacturing.work-orders.update', props.workOrder.id));
}
</script>
