<template>
    <div class="mx-auto max-w-4xl space-y-4 p-4 sm:p-6">
        <h1 class="text-xl font-semibold text-slate-800">Tạo bút toán</h1>

        <form @submit.prevent="submit" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Ngày chứng từ *</label>
                    <input v-model="form.entry_date" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.entry_date" class="mt-1 text-sm text-red-600">{{ form.errors.entry_date }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Diễn giải</label>
                    <input v-model="form.description" type="text" placeholder="Nội dung bút toán..." class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-700">Các dòng bút toán</h2>
                    <button type="button" @click="addLine" class="rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-200">
                        + Thêm dòng
                    </button>
                </div>

                <div class="space-y-2">
                    <div v-for="(line, index) in form.lines" :key="index" class="grid grid-cols-12 items-start gap-2">
                        <div class="col-span-5">
                            <select v-model="line.account_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                                <option :value="null">— Chọn tài khoản —</option>
                                <option v-for="account in accounts" :key="account.id" :value="account.id">
                                    {{ account.code }} — {{ account.name }}
                                </option>
                            </select>
                            <p v-if="form.errors[`lines.${index}.account_id`]" class="mt-1 text-xs text-red-600">{{ form.errors[`lines.${index}.account_id`] }}</p>
                        </div>
                        <div class="col-span-3">
                            <NumberInput v-model="line.debit" :min="0" placeholder="Nợ" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                        </div>
                        <div class="col-span-3">
                            <NumberInput v-model="line.credit" :min="0" placeholder="Có" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                        </div>
                        <div class="col-span-1 pt-1 text-right">
                            <button type="button" @click="removeLine(index)" :disabled="form.lines.length <= 2" class="text-sm text-red-600 hover:underline disabled:opacity-40">
                                Xóa
                            </button>
                        </div>
                        <p v-if="form.errors[`lines.${index}.debit`]" class="col-span-12 text-xs text-red-600">{{ form.errors[`lines.${index}.debit`] }}</p>
                    </div>
                </div>
                <p v-if="form.errors.lines" class="mt-1 text-sm text-red-600">{{ form.errors.lines }}</p>
<p class="mt-1 text-xs text-slate-400">Nhập số, có thể dùng dấu . hoặc , phân cách nghìn.</p>
            </div>

            <!-- Tổng Nợ/Có + cảnh báo mất cân bằng -->
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-slate-50 px-4 py-3 text-sm">
                <p class="text-slate-700">Tổng Nợ: <strong>{{ format.money(totalDebit) }}</strong></p>
                <p class="text-slate-700">Tổng Có: <strong>{{ format.money(totalCredit) }}</strong></p>
                <p v-if="balanced" class="font-medium text-green-700">✓ Bút toán cân bằng</p>
                <p v-else class="font-medium text-red-700">⚠ Chênh lệch {{ format.money(Math.abs(totalDebit - totalCredit)) }} — bút toán chưa cân bằng, vẫn lưu nháp được nhưng không ghi sổ được.</p>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    Lưu bút toán nháp
                </button>
                <Link :href="route('accounting.journal-entries.index')" class="text-sm text-slate-600 hover:text-slate-800">Quay lại</Link>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import NumberInput from '../../../Components/NumberInput.vue';
import { computed } from 'vue';
import { useFormat } from '../../../composables/useFormat';

defineProps({
    accounts: { type: Array, required: true },
});

const format = useFormat();

const emptyLine = () => ({ account_id: null, debit: 0, credit: 0, description: null });

const form = useForm({
    entry_date: new Date().toISOString().slice(0, 10),
    description: '',
    lines: [emptyLine(), emptyLine()],
});

const totalDebit = computed(() => form.lines.reduce((sum, line) => sum + (Number(line.debit) || 0), 0));
const totalCredit = computed(() => form.lines.reduce((sum, line) => sum + (Number(line.credit) || 0), 0));
const balanced = computed(() => Math.abs(totalDebit.value - totalCredit.value) <= 0.01 && totalDebit.value > 0);

function addLine() {
    form.lines.push(emptyLine());
}

function removeLine(index) {
    if (form.lines.length > 2) {
        form.lines.splice(index, 1);
    }
}

function submit() {
    form.post(route('accounting.journal-entries.store'));
}
</script>
