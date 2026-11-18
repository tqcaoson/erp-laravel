<template>
    <span
        :class="classes"
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
    >
        {{ label }}
    </span>
</template>

<script setup>
import { computed } from 'vue';

// Badge trạng thái chứng từ theo mục 7.2: nháp xám, chờ duyệt vàng,
// đã duyệt xanh dương, hoàn tất xanh lá, hủy đỏ.
const props = defineProps({
    status: { type: String, required: true },
});

const MAP = {
    draft: { label: 'Nháp', cls: 'bg-slate-100 text-slate-700 ring-slate-200' },
    pending_approval: { label: 'Chờ duyệt', cls: 'bg-amber-100 text-amber-800 ring-amber-200' },
    pending: { label: 'Chờ xử lý', cls: 'bg-amber-100 text-amber-800 ring-amber-200' },
    approved: { label: 'Đã duyệt', cls: 'bg-blue-100 text-blue-800 ring-blue-200' },
    confirmed: { label: 'Đã xác nhận', cls: 'bg-blue-100 text-blue-800 ring-blue-200' },
    partially_received: { label: 'Nhận một phần', cls: 'bg-sky-100 text-sky-800 ring-sky-200' },
    partially_delivered: { label: 'Giao một phần', cls: 'bg-sky-100 text-sky-800 ring-sky-200' },
    received: { label: 'Đã nhận hàng', cls: 'bg-green-100 text-green-800 ring-green-200' },
    delivered: { label: 'Đã giao', cls: 'bg-green-100 text-green-800 ring-green-200' },
    posted: { label: 'Đã ghi sổ', cls: 'bg-green-100 text-green-800 ring-green-200' },
    invoiced: { label: 'Đã lập hóa đơn', cls: 'bg-green-100 text-green-800 ring-green-200' },
    paid: { label: 'Đã thanh toán', cls: 'bg-green-100 text-green-800 ring-green-200' },
    completed: { label: 'Hoàn tất', cls: 'bg-green-100 text-green-800 ring-green-200' },
    closed: { label: 'Đã đóng', cls: 'bg-slate-200 text-slate-800 ring-slate-300' },
    locked: { label: 'Đã khóa', cls: 'bg-slate-200 text-slate-800 ring-slate-300' },
    cancelled: { label: 'Đã hủy', cls: 'bg-red-100 text-red-800 ring-red-200' },
    reversed: { label: 'Đã đảo', cls: 'bg-red-100 text-red-800 ring-red-200' },
    rejected: { label: 'Từ chối', cls: 'bg-red-100 text-red-800 ring-red-200' },
    overdue: { label: 'Quá hạn', cls: 'bg-red-100 text-red-800 ring-red-200' },
    in_progress: { label: 'Đang thực hiện', cls: 'bg-indigo-100 text-indigo-800 ring-indigo-200' },
    open: { label: 'Mở', cls: 'bg-emerald-100 text-emerald-800 ring-emerald-200' },
    won: { label: 'Thắng', cls: 'bg-green-100 text-green-800 ring-green-200' },
    lost: { label: 'Thua', cls: 'bg-red-100 text-red-800 ring-red-200' },
    active: { label: 'Đang hoạt động', cls: 'bg-green-100 text-green-800 ring-green-200' },
    inactive: { label: 'Ngừng hoạt động', cls: 'bg-slate-100 text-slate-700 ring-slate-200' },
};

const label = computed(() => MAP[props.status]?.label ?? props.status);
const classes = computed(() => `ring-1 ring-inset ${MAP[props.status]?.cls ?? 'bg-slate-100 text-slate-700 ring-slate-200'}`);
</script>
