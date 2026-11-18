<template>
    <div class="mx-auto max-w-4xl space-y-4 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800">Hóa đơn {{ invoice.code }}</h1>
                <p class="text-sm text-slate-500">
                    {{ invoice.type === 'sale' ? 'Bán hàng' : 'Mua hàng' }} · {{ format.date(invoice.invoice_date) }}
                    <span v-if="invoice.due_date"> · Hạn thanh toán {{ format.date(invoice.due_date) }}</span>
                </p>
            </div>
            <StatusBadge :status="invoice.status" />
        </div>

        <div v-if="$page.props.errors.status || $page.props.errors.amount" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
            {{ $page.props.errors.status || $page.props.errors.amount }}
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Mô tả</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">SL</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Đơn giá</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Thuế %</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Thành tiền</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="line in invoice.lines" :key="line.id">
                        <td class="px-4 py-3 text-sm text-slate-700">{{ line.description }}</td>
                        <td class="px-4 py-3 text-right text-sm text-slate-700">{{ format.number(line.qty, 2) }}</td>
                        <td class="px-4 py-3 text-right text-sm text-slate-700">{{ format.money(line.unit_price) }}</td>
                        <td class="px-4 py-3 text-right text-sm text-slate-700">{{ line.tax_rate }}%</td>
                        <td class="px-4 py-3 text-right text-sm text-slate-700">{{ format.money(line.line_total) }}</td>
                    </tr>
                </tbody>
                <tfoot class="bg-slate-50">
                    <tr>
                        <td colspan="4" class="px-4 py-2 text-right text-sm text-slate-600">Tiền hàng</td>
                        <td class="px-4 py-2 text-right text-sm font-medium text-slate-800">{{ format.money(invoice.subtotal) }}</td>
                    </tr>
                    <tr>
                        <td colspan="4" class="px-4 py-2 text-right text-sm text-slate-600">Thuế</td>
                        <td class="px-4 py-2 text-right text-sm font-medium text-slate-800">{{ format.money(invoice.tax_amount) }}</td>
                    </tr>
                    <tr>
                        <td colspan="4" class="px-4 py-2 text-right text-sm font-semibold text-slate-800">Tổng cộng</td>
                        <td class="px-4 py-2 text-right text-sm font-semibold text-slate-800">{{ format.money(invoice.total_amount) }}</td>
                    </tr>
                    <tr>
                        <td colspan="4" class="px-4 py-2 text-right text-sm text-slate-600">Đã thanh toán</td>
                        <td class="px-4 py-2 text-right text-sm font-medium text-green-700">{{ format.money(invoice.paid_amount) }}</td>
                    </tr>
                    <tr>
                        <td colspan="4" class="px-4 py-2 text-right text-sm text-slate-600">Còn phải {{ invoice.type === 'sale' ? 'thu' : 'trả' }}</td>
                        <td class="px-4 py-2 text-right text-sm font-medium text-red-700">{{ format.money(invoice.remaining_amount) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Ghi nhận thanh toán -->
        <form
            v-if="(invoice.status === 'posted' || invoice.status === 'paid') && invoice.remaining_amount > 0 && can('accounting.payment.create')"
            @submit.prevent="submitPayment"
            class="space-y-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
        >
            <h2 class="text-sm font-semibold text-slate-700">Ghi nhận thanh toán</h2>
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Số tiền *</label>
                    <input v-model.number="payForm.amount" type="number" min="0.01" step="0.01" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Ngày thanh toán *</label>
                    <input v-model="payForm.payment_date" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Tài khoản ngân hàng (để trống = tiền mặt)</label>
                    <select v-model="payForm.bank_account_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option :value="null">Tiền mặt (111)</option>
                        <option v-for="account in bankAccounts" :key="account.id" :value="account.id">{{ account.code }} — {{ account.name }}</option>
                    </select>
                </div>
            </div>
            <button type="submit" :disabled="payForm.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                Ghi nhận thanh toán
            </button>
        </form>

        <div class="flex flex-wrap items-center gap-3">
            <button
                v-if="invoice.status === 'draft' && can('accounting.invoice.post')"
                @click="postInvoice"
                class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
            >
                Ghi sổ
            </button>
            <button
                v-if="invoice.status === 'draft' && can('accounting.invoice.post')"
                @click="cancelInvoice"
                class="rounded-lg bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300"
            >
                Hủy hóa đơn
            </button>
            <Link :href="route('accounting.invoices.index')" class="text-sm text-slate-600 hover:text-slate-800">Quay lại danh sách</Link>
        </div>
    </div>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat';
import { usePermission } from '../../../composables/usePermission';

const props = defineProps({
    invoice: { type: Object, required: true },
    bankAccounts: { type: Array, default: () => [] },
});

const format = useFormat();
const { can } = usePermission();

const payForm = useForm({
    amount: props.invoice.remaining_amount,
    payment_date: new Date().toISOString().slice(0, 10),
    bank_account_id: null,
    description: '',
});

function postInvoice() {
    router.post(route('accounting.invoices.post', props.invoice.id));
}

function cancelInvoice() {
    if (confirm('Hủy hóa đơn nháp này?')) {
        router.post(route('accounting.invoices.cancel', props.invoice.id));
    }
}

function submitPayment() {
    payForm.post(route('accounting.invoices.pay', props.invoice.id), {
        onSuccess: () => payForm.reset(),
    });
}
</script>
