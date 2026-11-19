<template>
    <div class="mx-auto max-w-2xl space-y-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Sửa người dùng</h1>
            <p class="mt-1 text-sm text-slate-500">{{ user.email }}</p>
        </div>

        <form @submit.prevent="submit" class="space-y-4 rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tên *</label>
                <input v-model="form.name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Email *</label>
                <input v-model="form.email" type="email" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Mật khẩu mới (tối thiểu 8 ký tự)</label>
                <input v-model="form.password" type="password" minlength="8" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" placeholder="Để trống để giữ nguyên mật khẩu cũ" />
                <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Vai trò</label>
                <div class="space-y-2 rounded-lg border border-slate-200 p-3">
                    <label v-for="role in roles" :key="role.id" class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" :value="role.id" v-model="form.roles" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                        {{ role.label }}
                    </label>
                    <p v-if="!roles.length" class="text-sm text-slate-400">Chưa có vai trò nào.</p>
                </div>
                <p v-if="form.errors.roles" class="mt-1 text-sm text-red-600">{{ form.errors.roles }}</p>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" v-model="form.is_active" :disabled="isSelf" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 disabled:opacity-50" />
                Tài khoản đang hoạt động
                <span v-if="isSelf" class="text-xs text-slate-400">(không thể tự vô hiệu tài khoản của chính mình)</span>
            </label>

            <div class="flex justify-end gap-2 pt-2">
                <Link :href="route('admin.users.index')" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Hủy</Link>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                    {{ form.processing ? 'Đang lưu…' : 'Lưu thay đổi' }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    user: { type: Object, required: true },
    roles: { type: Array, default: () => [] },
});

const page = usePage();
const isSelf = computed(() => page.props.auth?.user?.id === props.user.id);

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    roles: (props.user.roles ?? []).map((r) => r.id),
    is_active: !!props.user.is_active,
});

function submit() {
    form.put(route('admin.users.update', props.user.id));
}
</script>
