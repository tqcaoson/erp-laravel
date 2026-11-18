import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** Kiểm tra quyền của user hiện tại (để ẩn/hiện nút theo quyền). */
export function usePermission() {
    const page = usePage();
    const permissions = computed(() => page.props.auth?.user?.permissions ?? []);
    const roles = computed(() => page.props.auth?.user?.roles ?? []);

    const can = (...names) => names.some((name) => permissions.value.includes(name));
    const hasRole = (...names) => names.some((name) => roles.value.includes(name));

    return { can, hasRole, permissions, roles };
}
