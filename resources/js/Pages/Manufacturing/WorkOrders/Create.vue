<template>
    <div class="mx-auto max-w-3xl space-y-4">
        <h1 class="text-xl font-bold text-slate-800">Thêm công đoạn sản xuất</h1>

        <div v-if="manufacturingOrders.length === 0" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Chưa có lệnh sản xuất nào đang thực hiện. Công đoạn chỉ thêm được cho lệnh đang thực hiện
            — hãy <Link :href="route('manufacturing.orders.index')" class="font-medium underline">bắt đầu một lệnh sản xuất</Link> trước.
        </div>

        <form v-else class="space-y-5 rounded-lg border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Lệnh sản xuất *</label>
                    <select v-model="form.manufacturing_order_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" @change="onOrderChange">
                        <option value="">— Chọn lệnh đang thực hiện —</option>
                        <option v-for="o in manufacturingOrders" :key="o.id" :value="o.id">{{ o.code }}</option>
                    </select>
                    <p v-if="form.errors.manufacturing_order_id" class="mt-1 text-xs text-red-600">{{ form.errors.manufacturing_order_id }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Tên công đoạn *</label>
                    <input v-model="form.operation_name" type="text" required placeholder="VD: Kiểm tra chất lượng" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
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
                    <p class="mt-1 text-xs text-slate-400">Thứ tự phải khác với các công đoạn đã có trong lệnh.</p>
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
            <p class="text-xs text-slate-500">Công đoạn mới ở trạng thái "Chờ xử lý". Mã công đoạn tự sinh.</p>

            <div class="flex gap-2">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    Thêm công đoạn
                </button>
                <Link :href="route('manufacturing.work-orders.index')" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
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
    manufacturingOrders: { type: Array, required: true },
    workCenters: { type: Array, required: true },
    nextSequences: { type: Object, default: () => ({}) },
    selectedOrderId: { type: [Number, null], default: null },
});

const form = useForm({
    manufacturing_order_id: props.selectedOrderId ?? '',
    operation_name: '',
    work_center_id: '',
    sequence: props.selectedOrderId ? (props.nextSequences[props.selectedOrderId] ?? 1) : 1,
    planned_minutes: 0,
    actual_minutes: null,
});

// Chọn lệnh khác thì tự điền thứ tự tiếp theo của lệnh đó.
function onOrderChange() {
    const next = props.nextSequences[form.manufacturing_order_id];
    if (next) form.sequence = next;
}

function submit() {
    form.post(route('manufacturing.work-orders.store'));
}
</script>
