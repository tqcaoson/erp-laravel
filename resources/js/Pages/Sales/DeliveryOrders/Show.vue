<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-800">Phiếu giao {{ deliveryOrder.code }}</h1>
                <p class="text-sm text-slate-500">
                    Đơn bán
                    <Link :href="route('sales.orders.show', deliveryOrder.sales_order_id)" class="font-medium text-blue-600 hover:underline">
                        {{ deliveryOrder.sales_order?.code }}
                    </Link>
                    · {{ deliveryOrder.sales_order?.customer?.name }} · Kho: {{ deliveryOrder.warehouse_name }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <StatusBadge :status="deliveryOrder.status" />
                <button
                    v-if="deliveryOrder.status === 'draft' && can('sales.delivery.validate')"
                    class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                    @click="validateOrder"
                >
                    Xác nhận giao hàng
                </button>
                <button
                    v-if="deliveryOrder.status === 'draft' && can('sales.delivery.validate')"
                    class="rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50"
                    @click="cancelOrder"
                >
                    Hủy phiếu
                </button>
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Hàng giao</h2>
            <DataTable
                :columns="[
                    { key: 'product_name', label: 'Sản phẩm' },
                    { key: 'qty', label: 'Số lượng' },
                    { key: 'unit_cost', label: 'Giá vốn' },
                ]"
                :items="deliveryOrder.lines ?? []"
            >
                <template #cell-product_name="{ row }">
                    {{ row.product_name }}
                    <span v-if="row.product_sku" class="text-xs text-slate-400">({{ row.product_sku }})</span>
                </template>
                <template #cell-qty="{ row }">{{ number(row.qty, 2) }}</template>
                <template #cell-unit_cost="{ row }">{{ deliveryOrder.status === 'validated' ? money(row.unit_cost) : '—' }}</template>
            </DataTable>
            <p class="mt-3 text-xs text-slate-500">
                Khi xác nhận, hệ thống xuất kho thật, ghi giá vốn bình quân và cập nhật số lượng đã giao trên đơn bán.
            </p>
        </div>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat';
import { usePermission } from '../../../composables/usePermission';

const props = defineProps({ deliveryOrder: { type: Object, required: true } });

const { money, number } = useFormat();
const { can } = usePermission();

function validateOrder() {
    if (confirm('Xác nhận đã giao đủ hàng trên phiếu này? Tồn kho sẽ bị trừ.')) {
        router.post(route('sales.deliveries.validate', props.deliveryOrder.id), {}, { preserveScroll: true });
    }
}

function cancelOrder() {
    if (confirm('Hủy phiếu giao hàng này?')) {
        router.post(route('sales.deliveries.cancel', props.deliveryOrder.id), {}, { preserveScroll: true });
    }
}
</script>
