<template>
    <div class="flex min-h-screen items-center justify-center bg-slate-900 px-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-2xl">
            <div class="mb-8 text-center">
                <span class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-600 text-2xl font-bold text-white">E</span>
                <h1 class="text-2xl font-bold text-slate-900">ERP An Phát</h1>
                <p class="mt-1 text-sm text-slate-500">Đăng nhập để quản lý doanh nghiệp của bạn</p>
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autofocus
                        placeholder="admin@erp.local"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                    />
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Mật khẩu</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        placeholder="••••••••"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                    />
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" />
                    Ghi nhớ đăng nhập
                </label>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-lg bg-blue-600 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-50"
                >
                    {{ form.processing ? 'Đang đăng nhập…' : 'Đăng nhập' }}
                </button>
            </form>

            <div class="mt-6 rounded-lg bg-slate-50 p-4 text-xs leading-relaxed text-slate-500 ring-1 ring-slate-100">
                <p class="font-semibold text-slate-700">Tài khoản demo</p>
                <p>Email: <span class="font-mono">admin@erp.local</span></p>
                <p>Mật khẩu: <span class="font-mono">password</span></p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
}
</script>
