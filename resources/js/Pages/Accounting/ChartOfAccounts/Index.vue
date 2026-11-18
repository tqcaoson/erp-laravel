<template>
    <div class="space-y-4 p-4 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800">Hệ thống tài khoản</h1>
                <p class="text-sm text-slate-500">Danh mục tài khoản kế toán theo TT200, xếp theo cây cha–con.</p>
            </div>
            <Link
                v-if="can('accounting.account.manage')"
                :href="route('accounting.chart-of-accounts.create')"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
                + Thêm tài khoản
            </Link>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Mã</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tên tài khoản</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Loại</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Trạng thái</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template v-for="account in tree" :key="account.id">
                        <AccountRow :account="account" :depth="0" />
                    </template>
                    <tr v-if="tree.length === 0">
                        <td colspan="4" class="px-4 py-10 text-center text-sm text-slate-400">
                            Chưa có tài khoản nào. Hãy chạy ChartOfAccountsSeeder để nạp hệ thống tài khoản mẫu.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { defineComponent, h } from 'vue';
import { usePermission } from '../../../composables/usePermission';

defineProps({
    tree: { type: Array, required: true },
    types: { type: Object, required: true },
});

const { can } = usePermission();

// Hàng tài khoản đệ quy: tài khoản con thụt vào theo cấp.
const AccountRow = defineComponent({
    name: 'AccountRow',
    props: { account: { type: Object, required: true }, depth: { type: Number, required: true } },
    setup(props) {
        return () => [
            h('tr', { class: 'hover:bg-slate-50' }, [
                h('td', { class: 'whitespace-nowrap px-4 py-3 text-sm font-medium text-slate-800' }, [
                    h('span', { style: { paddingLeft: `${props.depth * 1.5}rem` } }, props.account.code),
                ]),
                h('td', { class: 'px-4 py-3 text-sm text-slate-700' }, props.account.name),
                h('td', { class: 'whitespace-nowrap px-4 py-3 text-sm text-slate-500' }, props.account.type_label),
                h('td', { class: 'whitespace-nowrap px-4 py-3 text-sm' },
                    props.account.is_active
                        ? h('span', { class: 'rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800' }, 'Đang dùng')
                        : h('span', { class: 'rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700' }, 'Ngừng dùng')),
            ]),
            ...props.account.children.map((child) =>
                h(AccountRow, { account: child, depth: props.depth + 1, key: child.id }),
            ),
        ];
    },
});
</script>
