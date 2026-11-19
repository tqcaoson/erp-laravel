<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Tổng quan</h1>
            <p class="mt-1 text-sm text-slate-500">Số liệu kinh doanh, kho, nhân sự — cập nhật theo thời gian thực, cache 5 phút.</p>
        </div>

        <!-- Thẻ KPI -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div v-if="can('sales.order.view', 'reporting.report.view')" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <p class="text-sm font-medium text-slate-500">Doanh thu tháng này</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ money(kpi.revenue_this_month) }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ number(kpi.orders_this_month) }} đơn bán trong tháng</p>
            </div>
            <div v-if="can('inventory.stock.view')" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <p class="text-sm font-medium text-slate-500">Giá trị tồn kho</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ money(kpi.stock_value) }}</p>
                <p class="mt-1 text-xs text-slate-400">Tổng số lượng × giá vốn bình quân</p>
            </div>
            <div v-if="can('accounting.invoice.view')" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <p class="text-sm font-medium text-slate-500">Công nợ phải thu</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ money(kpi.receivables) }}</p>
                <p class="mt-1 text-xs text-slate-400">Hóa đơn bán đã ghi sổ, chưa thanh toán</p>
            </div>
            <div v-if="can('hr.employee.view')" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <p class="text-sm font-medium text-slate-500">Nhân viên đang hoạt động</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ number(kpi.active_employees) }} người</p>
                <Link :href="route('hr.employees.index')" class="mt-1 inline-block text-xs font-medium text-blue-600 hover:underline">
                    Xem danh sách nhân viên →
                </Link>
            </div>
            <div v-if="can('hr.leave.view')" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <p class="text-sm font-medium text-slate-500">Đơn nghỉ phép chờ duyệt</p>
                <p class="mt-2 text-2xl font-bold" :class="kpi.pending_leave_requests > 0 ? 'text-amber-600' : 'text-slate-900'">
                    {{ number(kpi.pending_leave_requests) }} đơn
                </p>
                <Link :href="route('hr.leave-requests.index')" class="mt-1 inline-block text-xs font-medium text-blue-600 hover:underline">
                    Xem đơn nghỉ phép →
                </Link>
            </div>
            <div v-if="can('reporting.report.view')" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <p class="text-sm font-medium text-slate-500">Báo cáo</p>
                <p class="mt-2 text-lg font-bold text-slate-900">Doanh thu · Công nợ · Tồn kho</p>
                <Link :href="route('reports.index')" class="mt-1 inline-block text-xs font-medium text-blue-600 hover:underline">
                    Mở trang báo cáo →
                </Link>
            </div>
        </div>

        <!-- Doanh thu 6 tháng gần nhất (biểu đồ cột đơn giản bằng div) -->
        <div v-if="can('sales.order.view', 'reporting.report.view')" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="text-base font-semibold text-slate-900">Doanh thu 6 tháng gần nhất</h2>
            <p class="mt-1 text-xs text-slate-400">Nguồn: bảng tổng hợp doanh thu theo ngày (fallback về hóa đơn nếu summary trống).</p>
            <div v-if="monthlyRevenue.length" class="mt-4 flex h-44 items-end gap-3">
                <div v-for="m in monthlyRevenue" :key="m.month" class="flex flex-1 flex-col items-center gap-1">
                    <p class="text-[11px] font-medium text-slate-600">{{ shortMoney(m.revenue) }}</p>
                    <div
                        class="w-full max-w-16 rounded-t bg-blue-500"
                        :style="{ height: barHeight(m.revenue) }"
                        :title="`${m.label}: ${money(m.revenue)} (${m.orders} đơn)`"
                    />
                    <p class="text-[11px] text-slate-500">{{ m.label }}</p>
                </div>
            </div>
            <p v-else class="mt-4 text-sm text-slate-400">Chưa có dữ liệu doanh thu.</p>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wider text-slate-500">
                            <th class="py-2 pr-4 font-semibold">Tháng</th>
                            <th class="py-2 pr-4 text-right font-semibold">Số đơn</th>
                            <th class="py-2 text-right font-semibold">Doanh thu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="m in monthlyRevenue" :key="m.month">
                            <td class="py-2 pr-4 text-slate-700">{{ m.label }}</td>
                            <td class="py-2 pr-4 text-right text-slate-700">{{ number(m.orders) }}</td>
                            <td class="py-2 text-right font-medium text-slate-900">{{ money(m.revenue) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Cảnh báo -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div v-if="can('inventory.stock.view')" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-base font-semibold text-slate-900">Hàng dưới mức tồn tối thiểu</h2>
                <ul v-if="alerts.low_stock.length" class="mt-3 space-y-2">
                    <li v-for="p in alerts.low_stock" :key="p.id" class="flex items-center justify-between text-sm">
                        <span class="text-slate-700">{{ p.name }} <span class="text-xs text-slate-400">({{ p.sku }})</span></span>
                        <span class="font-medium text-red-600">{{ number(p.qty) }} / {{ number(p.reorder_min) }}</span>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-slate-400">Không có sản phẩm nào dưới mức tối thiểu.</p>
            </div>
            <div v-if="can('accounting.invoice.view')" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-base font-semibold text-slate-900">Hóa đơn quá hạn</h2>
                <ul v-if="alerts.overdue_invoices.length" class="mt-3 space-y-2">
                    <li v-for="inv in alerts.overdue_invoices" :key="inv.id" class="flex items-center justify-between text-sm">
                        <span class="text-slate-700">{{ inv.code }} <span class="text-xs text-slate-400">· hạn {{ date(inv.due_date) }}</span></span>
                        <span class="font-medium text-red-600">{{ money(inv.total_amount) }}</span>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-slate-400">Không có hóa đơn quá hạn.</p>
            </div>
            <div v-if="can('hr.employee.view')" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-base font-semibold text-slate-900">Hợp đồng sắp hết hạn (30 ngày)</h2>
                <ul v-if="alerts.expiring_contracts.length" class="mt-3 space-y-2">
                    <li v-for="c in alerts.expiring_contracts" :key="c.id" class="flex items-center justify-between text-sm">
                        <span class="text-slate-700">{{ c.employee_name }} <span class="text-xs text-slate-400">({{ c.code }})</span></span>
                        <span class="font-medium text-amber-600">{{ date(c.end_date) }}</span>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-slate-400">Không có hợp đồng sắp hết hạn.</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useFormat } from '../composables/useFormat.js';
import { usePermission } from '../composables/usePermission.js';

const props = defineProps({
    kpi: { type: Object, required: true },
    alerts: { type: Object, required: true },
    monthlyRevenue: { type: Array, default: () => [] },
});

const { money, number, date } = useFormat();
const { can } = usePermission();

const maxRevenue = computed(() => Math.max(1, ...props.monthlyRevenue.map((m) => Number(m.revenue) || 0)));

/** Chiều cao cột tỉ lệ với doanh thu tháng cao nhất (tối thiểu 4px). */
function barHeight(revenue) {
    return `${Math.max(4, Math.round((Number(revenue) / maxRevenue.value) * 110))}px`;
}

/** Tiền rút gọn cho nhãn trên cột (vd 12,5 tr). */
function shortMoney(value) {
    const v = Number(value) || 0;
    if (v >= 1_000_000_000) return `${(v / 1_000_000_000).toLocaleString('vi-VN', { maximumFractionDigits: 1 })} tỷ`;
    if (v >= 1_000_000) return `${(v / 1_000_000).toLocaleString('vi-VN', { maximumFractionDigits: 1 })} tr`;
    if (v >= 1_000) return `${(v / 1_000).toLocaleString('vi-VN', { maximumFractionDigits: 0 })} nghìn`;
    return number(v);
}
</script>
