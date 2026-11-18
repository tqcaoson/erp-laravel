<template>
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500"
                            :class="{ 'cursor-pointer select-none hover:text-slate-700': column.sortable }"
                            @click="column.sortable && toggleSort(column.key)"
                        >
                            {{ column.label }}
                            <span v-if="column.sortable && sortKey === column.key">
                                {{ sortDir === 'asc' ? '↑' : '↓' }}
                            </span>
                        </th>
                        <th v-if="$slots.actions" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">
                            Thao tác
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="row in rows" :key="row.id" class="hover:bg-slate-50">
                        <td v-for="column in columns" :key="column.key" class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">
                            <slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">
                                {{ row[column.key] ?? '—' }}
                            </slot>
                        </td>
                        <td v-if="$slots.actions" class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            <slot name="actions" :row="row" />
                        </td>
                    </tr>
                    <tr v-if="rows.length === 0">
                        <td :colspan="columns.length + ($slots.actions ? 1 : 0)" class="px-4 py-10 text-center text-sm text-slate-400">
                            {{ emptyText }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Phân trang -->
        <div v-if="paginator" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3">
            <p class="text-sm text-slate-500">
                Hiển thị {{ paginator.from ?? 0 }}–{{ paginator.to ?? 0 }} trong tổng {{ paginator.total }} bản ghi
            </p>
            <div class="flex items-center gap-1">
                <Link
                    v-for="link in paginator.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    :class="[
                        'rounded-md px-3 py-1.5 text-sm',
                        link.active ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100',
                        !link.url && 'pointer-events-none opacity-40',
                    ]"
                    preserve-scroll
                    v-html="link.label"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    // Mảng cột: [{ key: 'code', label: 'Mã', sortable: true }]
    columns: { type: Array, required: true },
    // Đối tượng phân trang của Laravel (có .data và .links) hoặc mảng thường.
    items: { type: [Object, Array], required: true },
    emptyText: { type: String, default: 'Chưa có dữ liệu.' },
});

const rows = computed(() => (Array.isArray(props.items) ? props.items : props.items.data ?? []));
const paginator = computed(() => (Array.isArray(props.items) ? null : props.items));

// Sắp xếp theo chuẩn API: sort=-created_at (giảm dần) hoặc sort=code (tăng dần).
const sortParam = computed(() => new URLSearchParams(window.location.search).get('sort') ?? '');
const sortKey = computed(() => sortParam.value.replace(/^-/, ''));
const sortDir = computed(() => (sortParam.value.startsWith('-') ? 'desc' : 'asc'));

function toggleSort(key) {
    const next = sortKey.value === key && sortDir.value === 'asc' ? `-${key}` : key;
    router.get(window.location.pathname, { sort: next }, { preserveState: true, preserveScroll: true });
}
</script>
