<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-800">Lệnh sản xuất {{ order.code }}</h1>
                <p class="text-sm text-slate-500">
                    {{ order.product_name }} · SL kế hoạch {{ number(order.qty, 2) }} · Kho: {{ order.warehouse_name ?? '—' }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <StatusBadge :status="order.status" />
                <button
                    v-if="order.status === 'draft' && can('manufacturing.mo.create')"
                    class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    @click="postAction('confirm')"
                >
                    Xác nhận
                </button>
                <button
                    v-if="order.status === 'confirmed' && can('manufacturing.mo.start')"
                    class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    @click="postAction('start')"
                >
                    Bắt đầu sản xuất
                </button>
                <button
                    v-if="order.status === 'in_progress' && can('manufacturing.mo.complete')"
                    class="rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700"
                    @click="openProgress"
                >
                    Ghi nhận tiến độ
                </button>
                <button
                    v-if="order.status === 'in_progress' && can('manufacturing.mo.complete')"
                    class="rounded-lg bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700"
                    @click="openComplete"
                >
                    Hoàn thành
                </button>
                <button
                    v-if="['draft', 'confirmed'].includes(order.status) && can('manufacturing.mo.create')"
                    class="rounded-lg border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50"
                    @click="cancelOrder"
                >
                    Hủy lệnh
                </button>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Số lượng kế hoạch</p>
                <p class="mt-1 text-lg font-bold text-slate-800">{{ number(order.qty, 2) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Đã sản xuất</p>
                <p class="mt-1 text-lg font-bold text-green-700">{{ number(order.produced_qty, 2) }} / {{ number(order.qty, 2) }} ({{ progressPercent }}%)</p>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-green-600 transition-all" :style="{ width: progressPercent + '%' }"></div>
                </div>
                <p v-if="remainingQty > 0" class="mt-1 text-xs text-slate-500">Còn lại: {{ number(remainingQty, 2) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Định mức / Quy trình</p>
                <p class="mt-1 text-sm font-medium text-slate-800">
                    BOM: {{ order.bom?.code ?? '—' }} · Routing: {{ order.routing?.name ?? '—' }}
                </p>
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Nhu cầu nguyên liệu theo BOM</h2>
            <DataTable
                :columns="[
                    { key: 'name', label: 'Nguyên liệu' },
                    { key: 'qty', label: 'Cần dùng' },
                ]"
                :items="materials"
                empty-text="Lệnh này không có BOM nên không cần nguyên liệu."
            >
                <template #cell-qty="{ row }">{{ number(row.qty, 2) }}</template>
            </DataTable>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Công đoạn</h2>
            <DataTable
                :columns="[
                    { key: 'code', label: 'Mã' },
                    { key: 'operation_name', label: 'Công đoạn' },
                    { key: 'work_center', label: 'Trung tâm' },
                    { key: 'planned_minutes', label: 'Phút chuẩn' },
                    { key: 'status', label: 'Trạng thái' },
                ]"
                :items="order.work_orders ?? []"
                empty-text="Chưa có công đoạn — công đoạn được tạo khi bắt đầu sản xuất (theo routing)."
            >
                <template #cell-work_center="{ row }">{{ row.work_center?.name ?? '—' }}</template>
                <template #cell-planned_minutes="{ row }">{{ number(row.planned_minutes, 0) }}</template>
                <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
            </DataTable>
        </div>

        <div v-if="order.production_logs?.length" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Nhật ký sản xuất</h2>
            <DataTable
                :columns="[
                    { key: 'logged_at', label: 'Thời gian' },
                    { key: 'qty_produced', label: 'Thành phẩm' },
                    { key: 'qty_scrap', label: 'Phế phẩm' },
                ]"
                :items="order.production_logs"
            >
                <template #cell-logged_at="{ row }">{{ datetime(row.logged_at) }}</template>
                <template #cell-qty_produced="{ row }">{{ number(row.qty_produced, 2) }}</template>
                <template #cell-qty_scrap="{ row }">{{ number(row.qty_scrap, 2) }}</template>
            </DataTable>
        </div>

        <!-- Modal ghi nhận tiến độ từng phần -->
        <Modal :show="showProgress" title="Ghi nhận tiến độ" @close="showProgress = false">
            <form class="space-y-3" @submit.prevent="logProgress">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Số lượng thành phẩm đợt này *</label>
                    <NumberInput v-model="progressForm.qty_produced" :decimals="4" :min="0.0001" required placeholder="VD: 10" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="progressForm.errors.qty_produced" class="mt-1 text-sm text-red-600">{{ progressForm.errors.qty_produced }}</p>
                    <p class="mt-1 text-xs text-slate-400">Nhập số, có thể dùng dấu . hoặc , phân cách nghìn.</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Số lượng phế phẩm đợt này</label>
                    <NumberInput v-model="progressForm.qty_scrap" :decimals="4" :min="0" placeholder="VD: 0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="progressForm.errors.qty_scrap" class="mt-1 text-sm text-red-600">{{ progressForm.errors.qty_scrap }}</p>
                </div>
                <p class="text-xs text-slate-500">
                    Hệ thống sẽ xuất kho nguyên liệu theo BOM cho đợt này, nhập kho thành phẩm
                    của đợt này và cộng vào tổng đã sản xuất. Lệnh vẫn ở trạng thái đang sản xuất.
                </p>
                <div class="flex gap-2 pt-1">
                    <button type="submit" :disabled="progressForm.processing" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-50">
                        Ghi nhận
                    </button>
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="showProgress = false">
                        Hủy
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Modal hoàn thành -->
        <Modal :show="showComplete" title="Hoàn thành sản xuất" @close="showComplete = false">
            <form class="space-y-3" @submit.prevent="completeOrder">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Số lượng thành phẩm đợt chốt *</label>
                    <NumberInput v-model="completeForm.qty_produced" :decimals="4" :min="0.0001" required placeholder="VD: 10" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="completeForm.errors.qty_produced" class="mt-1 text-sm text-red-600">{{ completeForm.errors.qty_produced }}</p>
                    <p class="mt-1 text-xs text-slate-400">Nhập số lượng của đợt chốt này (đã trừ phần ghi tiến độ trước đó), có thể dùng dấu . hoặc , phân cách nghìn.</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Số lượng phế phẩm đợt chốt</label>
                    <NumberInput v-model="completeForm.qty_scrap" :decimals="4" :min="0" placeholder="VD: 0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="completeForm.errors.qty_scrap" class="mt-1 text-sm text-red-600">{{ completeForm.errors.qty_scrap }}</p>
                </div>
                <p class="text-xs text-slate-500">
                    Hệ thống chỉ xuất kho nguyên liệu theo BOM cho số lượng đợt chốt này
                    (phần đã ghi tiến độ trước đó không xuất lại), nhập kho thành phẩm với giá vốn
                    = tổng chi phí nguyên liệu / số lượng thành phẩm, rồi đóng lệnh.
                </p>
                <div class="flex gap-2 pt-1">
                    <button type="submit" :disabled="completeForm.processing" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">
                        Hoàn thành
                    </button>
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="showComplete = false">
                        Hủy
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>

<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DataTable from '../../../Components/DataTable.vue';
import Modal from '../../../Components/Modal.vue';
import NumberInput from '../../../Components/NumberInput.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat';
import { usePermission } from '../../../composables/usePermission';

const props = defineProps({
    order: { type: Object, required: true },
    materials: { type: Array, default: () => [] },
});

const { number, datetime } = useFormat();
const { can } = usePermission();
const showComplete = ref(false);
const showProgress = ref(false);

// Tiến độ sản xuất lũy kế (đã sản xuất / kế hoạch).
const progressPercent = computed(() => {
    const qty = Number(props.order.qty) || 0;
    if (qty <= 0) return 0;
    return Math.min(100, Math.round(((Number(props.order.produced_qty) || 0) / qty) * 100));
});

// Số lượng còn lại so với kế hoạch — giá trị mặc định cho cả 2 modal.
const remainingQty = computed(() => Math.max(0, (Number(props.order.qty) || 0) - (Number(props.order.produced_qty) || 0)));

const completeForm = useForm({
    qty_produced: remainingQty.value,
    qty_scrap: 0,
});

const progressForm = useForm({
    qty_produced: remainingQty.value,
    qty_scrap: 0,
});

// Điền lại số lượng còn lại mỗi lần mở modal (props đã đổi sau lần ghi trước).
function openProgress() {
    progressForm.qty_produced = remainingQty.value;
    progressForm.qty_scrap = 0;
    progressForm.clearErrors();
    showProgress.value = true;
}

function openComplete() {
    completeForm.qty_produced = remainingQty.value;
    completeForm.qty_scrap = 0;
    completeForm.clearErrors();
    showComplete.value = true;
}

function postAction(action) {
    router.post(route(`manufacturing.orders.${action}`, props.order.id), {}, { preserveScroll: true });
}

function cancelOrder() {
    if (confirm('Hủy lệnh sản xuất này?')) {
        postAction('cancel');
    }
}

function logProgress() {
    progressForm.post(route('manufacturing.orders.log-progress', props.order.id), {
        preserveScroll: true,
        onSuccess: () => { showProgress.value = false; },
    });
}

function completeOrder() {
    completeForm.post(route('manufacturing.orders.complete', props.order.id), {
        onSuccess: () => { showComplete.value = false; },
    });
}
</script>
