<template>
    <div class="mx-auto max-w-2xl space-y-4 p-4 sm:p-6">
        <h1 class="text-xl font-semibold text-slate-800">Tạo phiếu thu / chi</h1>

        <form @submit.prevent="submit" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Loại phiếu *</label>
                <select v-model="form.type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <option value="receipt">Phiếu thu</option>
                    <option value="payment">Phiếu chi</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Hóa đơn (nếu thanh toán hóa đơn — sẽ ghi sổ ngay và cập nhật trạng thái hóa đơn)</label>
                <select v-model="form.invoice_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <option :value="null">— Không gắn hóa đơn (phiếu độc lập, lưu nháp) —</option>
                    <option v-for="invoice in openInvoices" :key="invoice.id" :value="invoice.id">
                        {{ invoice.code }} ({{ invoice.type === 'sale' ? 'Bán' : 'Mua' }} — {{ format.money(invoice.total_amount) }})
                    </option>
                </select>
                <p v-if="form.errors.invoice_id" class="mt-1 text-sm text-red-600">{{ form.errors.invoice_id }}</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Số tiền *</label>
                    <input v-model.number="form.amount" type="number" min="0.01" step="0.01" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.amount" class="mt-1 text-sm text-red-600">{{ form.errors.amount }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Ngày thanh toán *</label>
                    <input v-model="form.payment_date" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.payment_date" class="mt-1 text-sm text-red-600">{{ form.errors.payment_date }}</p>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tài khoản ngân hàng (để trống = tiền mặt)</label>
                <select v-model="form.bank_account_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <option :value="null">Tiền mặt (111)</option>
                    <option v-for="account in bankAccounts" :key="account.id" :value="account.id">{{ account.code }} — {{ account.name }}</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Diễn giải</label>
                <input v-model="form.description" type="text" placeholder="Nội dung thu/chi..." class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    Lưu phiếu
                </button>
                <Link :href="route('accounting.payments.index')" class="text-sm text-slate-600 hover:text-slate-800">Quay lại</Link>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { useFormat } from '../../../composables/useFormat';

defineProps({
    bankAccounts: { type: Array, default: () => [] },
    openInvoices: { type: Array, default: () => [] },
});

const format = useFormat();

const form = useForm({
    type: 'receipt',
    invoice_id: null,
    bank_account_id: null,
    amount: null,
    payment_date: new Date().toISOString().slice(0, 10),
    description: '',
});

function submit() {
    form.post(route('accounting.payments.store'));
}
</script>
