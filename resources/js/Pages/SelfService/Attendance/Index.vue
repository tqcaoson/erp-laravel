<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Chấm công của tôi</h1>
            <p v-if="employee" class="mt-1 text-sm text-slate-500">{{ employee.full_name }} ({{ employee.code }}) — chỉ chấm trong ngày hôm nay, giờ đã chấm không sửa lại được.</p>
        </div>

        <div v-if="!employee" class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
            Tài khoản chưa gắn với hồ sơ nhân viên nên chưa xem hay chấm công được. Vui lòng liên hệ phòng nhân sự để được gắn hồ sơ.
        </div>

        <template v-else>
            <div class="flex flex-wrap items-center gap-3 rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <div class="flex-1">
                    <p class="text-sm text-slate-500">Hôm nay</p>
                    <p class="text-sm font-medium text-slate-900">
                        Giờ vào: <span class="font-semibold">{{ todayLog?.check_in ? datetime(todayLog.check_in) : '—' }}</span>
                        ·
                        Giờ ra: <span class="font-semibold">{{ todayLog?.check_out ? datetime(todayLog.check_out) : '—' }}</span>
                    </p>
                </div>
                <button
                    v-if="!todayLog?.check_in"
                    @click="checkIn"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                >
                    Chấm vào
                </button>
                <button
                    v-else-if="!todayLog?.check_out"
                    @click="checkOut"
                    class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700"
                >
                    Chấm ra
                </button>
                <span v-else class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-600">Đã chấm đủ hôm nay</span>
            </div>

            <!-- Lịch chấm công theo tháng -->
            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <div class="mb-3 flex items-center justify-between">
                    <button type="button" class="rounded-lg px-3 py-1.5 text-lg font-semibold text-slate-600 hover:bg-slate-100" @click="goToMonth(prevMonth)" aria-label="Tháng trước">
                        ‹
                    </button>
                    <div class="flex items-center gap-3">
                        <h2 class="text-base font-semibold text-slate-800">{{ monthLabel }}</h2>
                        <button
                            v-if="!isCurrentMonth"
                            type="button"
                            class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50"
                            @click="goToMonth(currentMonth)"
                        >
                            Tháng này
                        </button>
                    </div>
                    <button type="button" class="rounded-lg px-3 py-1.5 text-lg font-semibold text-slate-600 hover:bg-slate-100" @click="goToMonth(nextMonth)" aria-label="Tháng sau">
                        ›
                    </button>
                </div>

                <div class="grid grid-cols-7 gap-1 text-center text-xs font-semibold text-slate-500">
                    <div v-for="d in ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN']" :key="d" class="py-1">{{ d }}</div>
                </div>
                <div class="grid grid-cols-7 gap-1">
                    <div
                        v-for="(cell, index) in calendarCells"
                        :key="index"
                        :class="[
                            'min-h-[4.5rem] rounded-lg p-1.5 text-left align-top ring-1 ring-inset',
                            cell.blank ? 'bg-transparent ring-transparent' : cell.isToday
                                ? 'bg-blue-50 ring-2 ring-blue-500'
                                : cell.isWeekend
                                    ? 'bg-slate-50 ring-slate-100'
                                    : 'bg-white ring-slate-200',
                        ]"
                    >
                        <template v-if="!cell.blank">
                            <p :class="['text-sm font-semibold', cell.isToday ? 'text-blue-700' : 'text-slate-800']">{{ cell.day }}</p>
                            <template v-if="cell.log">
                                <p class="mt-0.5 text-[11px] leading-tight text-slate-600">
                                    Vào {{ timeHHmm(cell.log.check_in) }}<br />
                                    Ra {{ timeHHmm(cell.log.check_out) }}
                                </p>
                                <span v-if="cell.log.status === 'late'" class="mt-1 inline-block rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-800">Đi muộn</span>
                                <span v-else-if="cell.log.status === 'absent'" class="mt-1 inline-block rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-medium text-red-800">Vắng</span>
                                <span v-else-if="cell.log.status === 'leave'" class="mt-1 inline-block rounded-full bg-green-100 px-1.5 py-0.5 text-[10px] font-medium text-green-800">Nghỉ phép</span>
                                <span v-if="cell.log.check_in && !cell.log.check_out" class="mt-1 inline-block rounded-full bg-yellow-100 px-1.5 py-0.5 text-[10px] font-medium text-yellow-800">Chưa chấm ra</span>
                            </template>
                            <template v-else-if="cell.isLeave">
                                <span class="mt-1 inline-block rounded-full bg-green-100 px-1.5 py-0.5 text-[10px] font-medium text-green-800">Nghỉ phép</span>
                            </template>
                            <template v-else-if="cell.isMissing">
                                <span class="mt-1 inline-block rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-medium text-red-800">Thiếu chấm công</span>
                            </template>
                        </template>
                    </div>
                </div>
                <p class="mt-3 text-xs text-slate-400">
                    Lịch chỉ phân biệt thứ 7/chủ nhật và đơn nghỉ đã duyệt (chưa có ngày lễ).
                    Ngày làm việc đã qua mà không có bản ghi chấm công và không có đơn nghỉ sẽ hiện "Thiếu chấm công".
                </p>
            </div>

            <!-- Lịch sử chi tiết (bảng cũ, phân trang) -->
            <h2 class="text-base font-semibold text-slate-800">Lịch sử chi tiết</h2>

            <div class="flex flex-wrap gap-3 rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <input v-model="filterForm.filter.work_date" type="date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" @change="applyFilters" />
            </div>

            <DataTable :columns="columns" :items="logs" empty-text="Chưa có bản ghi chấm công nào.">
                <template #cell-work_date="{ value }">
                    {{ date(value) }}
                </template>
                <template #cell-check_in="{ value }">
                    {{ datetime(value) }}
                </template>
                <template #cell-check_out="{ value }">
                    {{ datetime(value) }}
                </template>
                <template #cell-shift="{ row }">
                    {{ row.shift?.name ?? '—' }}
                </template>
                <template #cell-status="{ value }">
                    <StatusBadge :status="attendanceBadge(value)" />
                </template>
            </DataTable>
        </template>
    </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import DataTable from '../../../Components/DataTable.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useFormat } from '../../../composables/useFormat.js';

const props = defineProps({
    employee: { type: Object, default: null },
    logs: { type: Object, default: null },
    todayLog: { type: Object, default: null },
    filters: { type: Object, default: () => ({}) },
    month: { type: String, default: '' },
    logsByDate: { type: Object, default: () => ({}) },
    approvedLeaveDates: { type: Array, default: () => [] },
});

const { date, datetime } = useFormat();

const columns = [
    { key: 'work_date', label: 'Ngày làm việc' },
    { key: 'shift', label: 'Ca' },
    { key: 'check_in', label: 'Giờ vào' },
    { key: 'check_out', label: 'Giờ ra' },
    { key: 'status', label: 'Trạng thái' },
];

const filterForm = reactive({
    filter: {
        work_date: props.filters.filter?.work_date ?? '',
    },
});

function applyFilters() {
    router.get(route('my.attendance.index'), filterForm, { preserveState: true, preserveScroll: true });
}

function checkIn() {
    router.post(route('my.attendance.store'), { action: 'check_in' }, { preserveScroll: true });
}

function checkOut() {
    router.post(route('my.attendance.store'), { action: 'check_out' }, { preserveScroll: true });
}

function attendanceBadge(status) {
    return { present: 'completed', late: 'pending', absent: 'rejected', leave: 'approved' }[status] ?? status;
}

// ---- Lịch tháng (vẽ bằng Date thuần, không thêm thư viện) ----

/** Chuỗi YYYY-MM-DD theo giờ địa phương (không dùng toISOString vì nó đổi sang UTC). */
function localIso(year, monthIndex, day) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${year}-${pad(monthIndex + 1)}-${pad(day)}`;
}

const today = new Date();
const todayStr = localIso(today.getFullYear(), today.getMonth(), today.getDate());
const currentMonth = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;

/** Tháng đang xem (server đã validate, sai thì về tháng hiện tại). */
const viewMonth = computed(() => props.month || currentMonth);
const isCurrentMonth = computed(() => viewMonth.value === currentMonth);

const monthLabel = computed(() => {
    const [y, m] = viewMonth.value.split('-');
    return `Tháng ${Number(m)}/${y}`;
});

/** Dịch tháng đang xem ±1 tháng, trả về chuỗi YYYY-MM. */
function shiftMonth(delta) {
    const [y, m] = viewMonth.value.split('-').map(Number);
    const shifted = new Date(y, m - 1 + delta, 1);
    return `${shifted.getFullYear()}-${String(shifted.getMonth() + 1).padStart(2, '0')}`;
}

const prevMonth = computed(() => shiftMonth(-1));
const nextMonth = computed(() => shiftMonth(1));

function goToMonth(month) {
    router.get(route('my.attendance.index'), { month }, { preserveState: true });
}

/**
 * Các ô của lưới lịch: ô trống bù offset đầu tháng + 1 ô mỗi ngày.
 * Quy tắc badge (ưu tiên log nếu có, đơn duyệt chỉ tô khi không có log):
 * - Có log: hiện giờ vào/ra HH:mm + badge theo status đã lưu (late/absent/
 *   leave; không suy diễn "late" từ giờ chấm) + "Chưa chấm ra" nếu thiếu giờ ra.
 * - Không log + có đơn nghỉ đã duyệt: "Nghỉ phép".
 * - Không log + không phép + ngày làm việc (T2–T6) đã qua: "Thiếu chấm công".
 * - Ngày tương lai không đánh dấu thiếu; T7/CN chỉ tô nền xám.
 */
const calendarCells = computed(() => {
    const [y, m] = viewMonth.value.split('-').map(Number);
    const daysInMonth = new Date(y, m, 0).getDate();
    // Lưới bắt đầu từ T2: offset = số ô trống trước ngày 1.
    const offset = (new Date(y, m - 1, 1).getDay() + 6) % 7;
    const leaveSet = new Set(props.approvedLeaveDates ?? []);

    const cells = [];
    for (let i = 0; i < offset; i++) cells.push({ blank: true });
    for (let day = 1; day <= daysInMonth; day++) {
        const iso = localIso(y, m - 1, day);
        const weekday = new Date(y, m - 1, day).getDay();
        const isWeekend = weekday === 0 || weekday === 6;
        const log = props.logsByDate?.[iso] ?? null;
        const isLeave = leaveSet.has(iso);
        cells.push({
            blank: false,
            day,
            iso,
            log,
            isWeekend,
            isToday: iso === todayStr,
            isLeave,
            // Ngày làm việc đã qua, không log, không phép → thiếu chấm công.
            isMissing: !log && !isLeave && !isWeekend && iso < todayStr,
        });
    }
    return cells;
});

/** Giờ HH:mm từ chuỗi datetime (không có giờ thì '—'). */
function timeHHmm(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '—';
    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}
</script>
