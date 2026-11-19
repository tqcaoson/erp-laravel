<template>
    <div class="mx-auto max-w-2xl space-y-4">
        <h1 class="text-xl font-bold text-slate-800">{{ workCenter ? 'Sửa trung tâm sản xuất' : 'Thêm trung tâm sản xuất' }}</h1>

        <form class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Mã trung tâm</label>
                    <input v-model="form.code" type="text" placeholder="Để trống để tự sinh" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Tên trung tâm *</label>
                    <input v-model="form.name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Chi phí mỗi giờ (₫)</label>
                    <NumberInput v-model="form.cost_per_hour" :min="0" placeholder="VD: 20000" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p class="mt-1 text-xs text-slate-400">Nhập số, có thể dùng dấu . hoặc , phân cách nghìn.</p>
                </div>
                <div class="flex items-end gap-2 pb-2">
                    <input id="is_active" v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                    <label for="is_active" class="text-sm text-slate-700">Đang hoạt động</label>
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    {{ workCenter ? 'Cập nhật' : 'Tạo trung tâm' }}
                </button>
                <Link :href="route('manufacturing.work-centers.index')" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
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
    workCenter: { type: Object, default: null },
});

const form = useForm({
    code: props.workCenter?.code ?? '',
    name: props.workCenter?.name ?? '',
    cost_per_hour: props.workCenter?.cost_per_hour ?? 0,
    is_active: props.workCenter?.is_active ?? true,
});

function submit() {
    if (props.workCenter) {
        form.put(route('manufacturing.work-centers.update', props.workCenter.id));
    } else {
        form.post(route('manufacturing.work-centers.store'));
    }
}
</script>
