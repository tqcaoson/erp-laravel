<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Báo cáo</h1>
            <p class="mt-1 text-sm text-slate-500">Các báo cáo có sẵn. Mỗi báo cáo đều xuất được file CSV (chưa hỗ trợ PDF).</p>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <Link
                v-for="r in reports"
                :key="r.key"
                :href="r.url"
                class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:ring-blue-400"
            >
                <h2 class="text-base font-semibold text-slate-900">{{ r.name }}</h2>
                <p class="mt-2 text-sm text-slate-500">{{ r.description }}</p>
                <p class="mt-3 text-sm font-medium text-blue-600">Mở báo cáo →</p>
            </Link>
        </div>

        <div class="rounded-lg bg-slate-50 p-4 text-sm text-slate-500 ring-1 ring-slate-200">
            <p class="font-semibold text-slate-700">Lưu ý về nguồn số liệu</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>Báo cáo doanh thu đọc từ hóa đơn bán đã ghi sổ; dashboard đọc từ bảng tổng hợp theo ngày (cập nhật bằng job khi hóa đơn ghi sổ).</li>
                <li>Báo cáo xuất nhập tồn ưu tiên bảng tổng hợp theo ngày, fallback về sổ kho (stock_moves) khi summary trống.</li>
                <li>Xuất CSV: file có BOM UTF-8 để mở đúng tiếng Việt trong Excel.</li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    reports: { type: Array, default: () => [] },
});
</script>
