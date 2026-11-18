<template>
    <div class="mx-auto max-w-4xl space-y-4 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800">Bút toán {{ entry.code }}</h1>
                <p class="text-sm text-slate-500">
                    {{ format.date(entry.entry_date) }} · Kỳ {{ entry.fiscal_period?.code ?? '—' }}
                </p>
            </div>
            <StatusBadge :status="entry.status" />
        </div>

        <p v-if="entry.description" class="text-sm text-slate-700">{{ entry.description }}</p>

        <div v-if="$page.props.errors.status" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
            {{ $page.props.errors.status }}
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tài khoản</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Nợ</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Có</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Diễn giải</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="line in entry.lines" :key="line.id">
                        <td class="px-4 py-3 text-sm text-slate-700">{{ line.account?.code }} — {{ line.account?.name }}</td>
                        <td class="px-4 py-3 text-right text-sm text-slate-700">{{ line.debit > 0 ? format.money(line.debit) : '—' }}</td>
                        <td class="px-4 py-3 text-right text-sm text-slate-700">{{ line.credit > 0 ? format.money(line.credit) : '—' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-500">{{ line.description ?? '—' }}</td>
                    </tr>
                </tbody>
                <tfoot class="bg-slate-50">
                    <tr>
                        <td class="px-4 py-3 text-sm font-semibold text-slate-800">Tổng cộng</td>
                        <td class="px-4 py-3 text-right text-sm font-semibold text-slate-800">{{ format.money(totalDebit) }}</td>
                        <td class="px-4 py-3 text-right text-sm font-semibold text-slate-800">{{ format.money(totalCredit) }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span v-if="balanced" class="font-medium text-green-700">Cân bằng</span>
                            <span v-else class="font-medium text-red-700">Chênh lệch {{ format.money(Math.abs(totalDebit - totalCredit)) }}</span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p v-if="entry.status === 'reversed'" class="text-sm text-slate-600">
            Bút toán này đã bị đảo bởi
            <Link :href="route('accounting.journal-entries.show', entry.reversed_by.id)" class="font-medium text-blue-600 hover:underline">
                {{ entry.reversed_by.code }}
            </Link>.
        </p>

        <div class="flex flex-wrap items-center gap-3">
            <button
                v-if="entry.status === 'draft' && can('accounting.entry.post')"
                @click="postEntry"
                class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
            >
                Ghi sổ
            </button>
            <button
                v-if="entry.status === 'posted' && can('accounting.entry.reverse')"
                @click="reverseEntry"
                class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
            >
                Đảo bút toán
            </button>
            <Link :href="route('accounting.journal-entries.index')" class="text-sm text-slate-600 hover:text-slate-800">Quay lại danh sách</Link>
        </div>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat';
import { usePermission } from '../../../composables/usePermission';

const props = defineProps({
    entry: { type: Object, required: true },
});

const format = useFormat();
const { can } = usePermission();

const totalDebit = computed(() => props.entry.lines.reduce((sum, line) => sum + Number(line.debit), 0));
const totalCredit = computed(() => props.entry.lines.reduce((sum, line) => sum + Number(line.credit), 0));
const balanced = computed(() => Math.abs(totalDebit.value - totalCredit.value) <= 0.01);

function postEntry() {
    router.post(route('accounting.journal-entries.post', props.entry.id));
}

function reverseEntry() {
    if (confirm('Đảo bút toán này? Hệ thống sẽ sinh bút toán đảo ngược và ghi sổ ngay, bút toán gốc được giữ nguyên.')) {
        router.post(route('accounting.journal-entries.reverse', props.entry.id));
    }
}
</script>
