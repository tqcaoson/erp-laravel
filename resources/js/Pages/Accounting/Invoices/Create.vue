<template>
    <div class="mx-auto max-w-4xl space-y-4 p-4 sm:p-6">
        <h1 class="text-xl font-semibold text-slate-800">Lập hóa đơn</h1>

        <form @submit.prevent="submit" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Loại hóa đơn *</label>
                    <select v-model="form.type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="sale">Bán hàng</option>
                        <option value="purchase">Mua hàng</option>
                    </select>
                    <p v-if="form.errors.type" class="mt-1 text-sm text-red-600">{{ form.errors.type }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Ngày hóa đơn *</label>
                    <input v-model="form.invoice_date" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.invoice_date" class="mt-1 text-sm text-red-600">{{ form.errors.invoice_date }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        {{ form.type === 'sale' ? 'ID khách hàng *' : 'ID nhà cung cấp *' }}
                    </label>
                    <input v-model.number="partnerId" type="number" min="1" placeholder="Nhập ID đối tác" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <p class="mt-1 text-xs text-slate-500">Danh mục khách hàng/NCC sẽ thay bằng danh sách chọn sau khi module Sales/Procurement hoàn thành.</p>
                    <p v-if="form.errors.customer_id || form.errors.supplier_id" class="mt-1 text-sm text-red-600">{{ form.errors.customer_id || form.errors.supplier_id }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Hạn thanh toán</label>
                    <input v-model="form.due_date" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                </div>
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-700">Các dòng hóa đơn</h2>
                    <button type="button" @click="addLine" class="rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-200">
                        + Thêm dòng
                    </button>
                </div>

                <div class="space-y-2">
                    <div v-for="(line, index) in form.lines" :key="index" class="grid grid-cols-12 items-start gap-2">
                        <div class="col-span-5">
                            <input v-model="line.description" type="text" placeholder="Mô tả hàng hóa/dịch vụ" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                            <p v-if="form.errors[`lines.${index}.description`]" class="mt-1 text-xs text-red-600">{{ form.errors[`lines.${index}.description`] }}</p>
                        </div>
                        <div class="col-span-2">
                            <NumberInput v-model="line.qty" :decimals="4" :min="0.0001" placeholder="SL" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                            <p v-if="form.errors[`lines.${index}.qty`]" class="mt-1 text-xs text-red-600">{{ form.errors[`lines.${index}.qty`] }}</p>
                        </div>
                        <div class="col-span-2">
                            <NumberInput v-model="line.unit_price" :min="0" placeholder="Đơn giá" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                            <p v-if="form.errors[`lines.${index}.unit_price`]" class="mt-1 text-xs text-red-600">{{ form.errors[`lines.${index}.unit_price`] }}</p>
                        </div>
                        <div class="col-span-2">
                            <NumberInput v-model="line.tax_rate" :decimals="2" :min="0" :max="100" placeholder="Thuế %" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                            <p v-if="form.errors[`lines.${index}.tax_rate`]" class="mt-1 text-xs text-red-600">{{ form.errors[`lines.${index}.tax_rate`] }}</p>
                        </div>
                        <div class="col-span-1 pt-1 text-right">
                            <button type="button" @click="removeLine(index)" :disabled="form.lines.length <= 1" class="text-sm text-red-600 hover:underline disabled:opacity-40">
                                Xóa
                            </button>
                        </div>
                    </div>
                </div>
                <p v-if="form.errors.lines" class="mt-1 text-sm text-red-600">{{ form.errors.lines }}</p>
                <p class="mt-1 text-xs text-slate-400">Nhập số, có thể dùng dấu . hoặc , phân cách nghìn (VD: 20.000 = 20000). Số lượng tối thiểu 0.0001.</p>
            </div>

            <div class="flex flex-wrap items-center gap-6 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-700">
                <p>Tiền hàng: <strong>{{ format.money(subtotal) }}</strong></p>
                <p>Thuế: <strong>{{ format.money(taxAmount) }}</strong></p>
                <p>Tổng cộng: <strong>{{ format.money(subtotal + taxAmount) }}</strong></p>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    Lưu hóa đơn nháp
                </button>
                <Link :href="route('accounting.invoices.index')" class="text-sm text-slate-600 hover:text-slate-800">Quay lại</Link>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import NumberInput from '../../../Components/NumberInput.vue';
import { useFormat } from '../../../composables/useFormat';

const format = useFormat();

const partnerId = ref(null);
const emptyLine = () => ({ description: '', qty: 1, unit_price: 0, tax_rate: 10 });

const form = useForm({
    type: 'sale',
    customer_id: null,
    supplier_id: null,
    invoice_date: new Date().toISOString().slice(0, 10),
    due_date: '',
    lines: [emptyLine()],
});

const subtotal = computed(() => form.lines.reduce((sum, line) => sum + (Number(line.qty) || 0) * (Number(line.unit_price) || 0), 0));
const taxAmount = computed(() => form.lines.reduce((sum, line) => sum + (Number(line.qty) || 0) * (Number(line.unit_price) || 0) * ((Number(line.tax_rate) || 0) / 100), 0));

function addLine() {
    form.lines.push(emptyLine());
}

function removeLine(index) {
    if (form.lines.length > 1) {
        form.lines.splice(index, 1);
    }
}

function submit() {
    form.customer_id = form.type === 'sale' ? partnerId.value : null;
    form.supplier_id = form.type === 'purchase' ? partnerId.value : null;
    form.post(route('accounting.invoices.store'));
}
</script>
