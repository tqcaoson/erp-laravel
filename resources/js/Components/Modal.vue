<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-900/50" @click="$emit('close')" />
                <div class="relative w-full max-w-lg rounded-xl bg-white p-6 shadow-xl" role="dialog" aria-modal="true">
                    <div class="mb-4 flex items-start justify-between">
                        <h3 class="text-lg font-semibold text-slate-900">{{ title }}</h3>
                        <button type="button" class="text-slate-400 hover:text-slate-600" @click="$emit('close')">✕</button>
                    </div>
                    <slot />
                    <div v-if="$slots.footer" class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-4">
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue';

defineProps({
    show: { type: Boolean, required: true },
    title: { type: String, default: '' },
});

const emit = defineEmits(['close']);

function onKeydown(event) {
    if (event.key === 'Escape') emit('close');
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.15s ease;
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>
