<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-800">Báo giá {{ quotation.code }}</h1>
                <p class="text-sm text-slate-500">
                    {{ quotation.customer?.name }} · Hiệu lực đến {{ date(quotation.valid_until) }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <StatusBadge :status="quotation.status" />
                <template v-if="['draft', 'sent'].includes(quotation.status) && can('sales.quotation.create')">
                    <button
                        v-if="quotation.status === 'draft'"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700"
                        @click="setStatus('sent')"
                    >
                        Gửi báo giá
                    </button>
                    <button class="rounded-lg bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700" @click="setStatus('accepted')">
                        Khách chấp nhận
                    </button>
                    <button class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="setStatus('rejected')">
                        Từ chối
                    </button>
                </template>
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Dòng sản phẩm</h2>
            <DataTable
                :columns="[
                    { key: 'product_name', label: 'Sản phẩm' },
                    { key: 'qty', label: 'Số lượng' },
                    { key: 'unit_price', label: 'Đơn giá' },
                    { key: 'line_total', label: 'Thành tiền' },
                ]"
                :items="quotation.lines ?? []"
            >
                <template #cell-product_name="{ row }">
                    {{ row.product_name }}
                    <span v-if="row.product_sku" class="text-xs text-slate-400">({{ row.product_sku }})</span>
                </template>
                <template #cell-qty="{ row }">{{ number(row.qty, 2) }}</template>
                <template #cell-unit_price="{ row }">{{ money(row.unit_price) }}</template>
                <template #cell-line_total="{ row }">{{ money(row.line_total) }}</template>
            </DataTable>
            <dl class="ml-auto mt-3 w-64 space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Tạm tính</dt><dd>{{ money(quotation.subtotal) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Thuế</dt><dd>{{ money(quotation.tax_amount) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Chiết khấu</dt><dd>− {{ money(quotation.discount_amount) }}</dd></div>
                <div class="flex justify-between border-t border-slate-200 pt-1 font-bold"><dt>Tổng cộng</dt><dd>{{ money(quotation.total_amount) }}</dd></div>
            </dl>
        </div>

        <div v-if="quotation.status === 'accepted' && can('sales.order.create')" class="rounded-lg border border-green-200 bg-green-50 p-4">
            <p class="text-sm font-medium text-green-800">Khách hàng đã chấp nhận báo giá — có thể chuyển thành đơn bán.</p>
            <form class="mt-3 flex flex-wrap items-end gap-3" @submit.prevent="convert">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Kho giao hàng *</label>
                    <select v-model="convertForm.warehouse_id" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">— Chọn kho —</option>
                        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                    </select>
                </div>
                <button type="submit" :disabled="convertForm.processing" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">
                    Chuyển thành đơn bán
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { router, useForm } from '@inertiajs/vue3';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat';
import { usePermission } from '../../../composables/usePermission';

const props = defineProps({
    quotation: { type: Object, required: true },
    warehouses: { type: Array, default: () => [] },
});

const { money, date, number } = useFormat();
const { can } = usePermission();

const convertForm = useForm({ warehouse_id: '' });

function setStatus(status) {
    router.post(route('sales.quotations.status', props.quotation.id), { status }, { preserveScroll: true });
}

function convert() {
    convertForm.post(route('sales.quotations.convert', props.quotation.id));
}
</script>
