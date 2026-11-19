<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold text-slate-800">Cơ hội bán hàng</h1>
            <button
                v-if="can('sales.opportunity.manage')"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                @click="showCreate = true"
            >
                + Thêm cơ hội
            </button>
        </div>

        <!-- Kanban theo giai đoạn -->
        <div class="grid gap-4 xl:grid-cols-6 lg:grid-cols-3 md:grid-cols-2">
            <div v-for="(label, stage) in stages" :key="stage" class="rounded-lg bg-slate-100 p-3">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-700">{{ label }}</h2>
                    <span class="rounded-full bg-white px-2 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-slate-200">
                        {{ (board[stage] ?? []).length }}
                    </span>
                </div>
                <div class="space-y-2">
                    <div
                        v-for="opp in board[stage] ?? []"
                        :key="opp.id"
                        class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm"
                    >
                        <p class="text-sm font-medium text-slate-800">{{ opp.name }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ opp.customer?.name ?? opp.lead?.name ?? 'Chưa gắn khách' }}</p>
                        <p class="mt-1 text-sm font-semibold text-blue-700">{{ money(opp.expected_amount) }}</p>
                        <p v-if="opp.expected_close_date" class="text-xs text-slate-500">
                            Chốt dự kiến: {{ date(opp.expected_close_date) }}
                        </p>
                        <div class="mt-2 flex items-center gap-2">
                            <select
                                :value="opp.stage"
                                class="rounded border border-slate-300 px-1.5 py-1 text-xs focus:border-blue-500 focus:outline-none"
                                @change="moveStage(opp, $event.target.value)"
                            >
                                <option v-for="(l, s) in stages" :key="s" :value="s">{{ l }}</option>
                            </select>
                            <button
                                v-if="can('sales.opportunity.manage')"
                                class="text-xs text-red-600 hover:underline"
                                @click="removeOpp(opp)"
                            >
                                Xóa
                            </button>
                        </div>
                    </div>
                    <p v-if="!(board[stage] ?? []).length" class="py-4 text-center text-xs text-slate-400">Chưa có cơ hội</p>
                </div>
            </div>
        </div>

        <!-- Modal tạo cơ hội -->
        <Modal :show="showCreate" title="Thêm cơ hội bán hàng" @close="showCreate = false">
            <form class="space-y-3" @submit.prevent="createOpp">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Tên cơ hội *</label>
                    <input v-model="form.name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Khách hàng</label>
                        <select v-model="form.customer_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                            <option value="">— Chưa chọn —</option>
                            <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Giai đoạn</label>
                        <select v-model="form.stage" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                            <option v-for="(l, s) in stages" :key="s" :value="s">{{ l }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Giá trị dự kiến (₫)</label>
                        <NumberInput v-model="form.expected_amount" :min="0" placeholder="VD: 20000" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                        <p class="mt-1 text-xs text-slate-400">Nhập số, có thể dùng dấu . hoặc , phân cách nghìn.</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Ngày chốt dự kiến</label>
                        <input v-model="form.expected_close_date" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    </div>
                </div>
                <div class="flex gap-2 pt-1">
                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        Tạo cơ hội
                    </button>
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="showCreate = false">
                        Hủy
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>

<script setup>
import { router, useForm } from '@inertiajs/vue3';
import NumberInput from '../../../Components/NumberInput.vue';
import { ref } from 'vue';
import Modal from '../../../Components/Modal.vue';
import { useFormat } from '../../../composables/useFormat';
import { usePermission } from '../../../composables/usePermission';

defineProps({
    board: { type: Object, required: true },
    stages: { type: Object, required: true },
    customers: { type: Array, default: () => [] },
    leads: { type: Array, default: () => [] },
});

const { money, date } = useFormat();
const { can } = usePermission();
const showCreate = ref(false);

const form = useForm({
    name: '',
    customer_id: '',
    lead_id: '',
    stage: 'lead',
    expected_amount: 0,
    expected_close_date: '',
    description: '',
});

function createOpp() {
    form.post(route('sales.opportunities.store'), {
        onSuccess: () => {
            showCreate.value = false;
            form.reset();
        },
    });
}

function moveStage(opp, stage) {
    if (stage === opp.stage) return;
    router.patch(route('sales.opportunities.stage', opp.id), { stage }, { preserveScroll: true });
}

function removeOpp(opp) {
    if (confirm(`Xóa cơ hội "${opp.name}"?`)) {
        router.delete(route('sales.opportunities.destroy', opp.id), { preserveScroll: true });
    }
}
</script>
