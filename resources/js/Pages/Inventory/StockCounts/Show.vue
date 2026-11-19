<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800">Phiếu kiểm kê {{ count.code }}</h1>
                <p class="text-sm text-slate-500">Kho {{ count.warehouse?.name }} — <StatusBadge :status="count.status" /></p>
            </div>
            <div class="flex gap-2">
                <button v-if="['draft', 'in_progress'].includes(count.status)" @click="complete" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                    Hoàn tất kiểm kê
                </button>
                <button v-if="['draft', 'in_progress'].includes(count.status)" @click="cancel" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Hủy phiếu
                </button>
            </div>
        </div>

        <form @submit.prevent="saveCounted" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <p class="px-4 pt-3 text-xs text-slate-400">Nhập số, có thể dùng dấu . hoặc , phân cách nghìn.</p>
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Sản phẩm</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Số hệ thống</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Số thực tế</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Chênh lệch</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="line in lines" :key="line.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm text-slate-700">{{ line.product?.sku }} — {{ line.product?.name }}</td>
                        <td class="px-4 py-3 text-right text-sm text-slate-700">{{ number(line.system_qty, 2) }}</td>
                        <td class="px-4 py-3 text-right">
                            <NumberInput
                                v-model="line.counted_qty"
                                :decimals="4" :min="0"
                                :disabled="!['draft', 'in_progress'].includes(count.status)"
                                class="w-32 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm focus:border-blue-500 focus:outline-none disabled:bg-slate-50"
                            />
                        </td>
                        <td class="px-4 py-3 text-right text-sm" :class="diff(line) === 0 ? 'text-slate-500' : diff(line) > 0 ? 'font-medium text-green-700' : 'font-medium text-red-700'">
                            {{ line.counted_qty === null || line.counted_qty === '' ? '—' : (diff(line) > 0 ? '+' : '') + number(diff(line), 2) }}
                        </td>
                    </tr>
                    <tr v-if="lines.length === 0">
                        <td colspan="4" class="px-4 py-10 text-center text-sm text-slate-400">Phiếu kiểm kê chưa có dòng nào.</td>
                    </tr>
                </tbody>
            </table>
            <div v-if="['draft', 'in_progress'].includes(count.status)" class="border-t border-slate-200 px-4 py-3">
                <button type="submit" :disabled="saving" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    {{ saving ? 'Đang lưu…' : 'Lưu số thực tế' }}
                </button>
                <p class="mt-2 text-xs text-slate-400">Hoàn tất kiểm kê sẽ sinh phiếu điều chỉnh chênh lệch vào tồn kho (thừa thì nhập, thiếu thì xuất).</p>
            </div>
        </form>
    </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import NumberInput from '../../../Components/NumberInput.vue';
import { reactive, ref } from 'vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat.js';

const props = defineProps({ count: { type: Object, required: true } });
const { number } = useFormat();
const saving = ref(false);

const lines = reactive(props.count.lines.map((l) => ({
    id: l.id, product: l.product,
    system_qty: Number(l.system_qty),
    counted_qty: l.counted_qty === null ? null : Number(l.counted_qty),
})));

function diff(line) {
    if (line.counted_qty === null || line.counted_qty === '') return 0;
    return Number(line.counted_qty) - line.system_qty;
}

function saveCounted() {
    saving.value = true;
    router.put(
        route('inventory.stock-counts.update-counted', props.count.id),
        { lines: lines.filter((l) => l.counted_qty !== null && l.counted_qty !== '').map((l) => ({ line_id: l.id, counted_qty: l.counted_qty })) },
        { preserveScroll: true, onFinish: () => (saving.value = false) },
    );
}

function complete() {
    if (confirm('Hoàn tất kiểm kê và điều chỉnh chênh lệch vào tồn kho?')) {
        router.post(route('inventory.stock-counts.complete', props.count.id));
    }
}

function cancel() {
    if (confirm('Hủy phiếu kiểm kê này?')) {
        router.post(route('inventory.stock-counts.cancel', props.count.id));
    }
}
</script>
