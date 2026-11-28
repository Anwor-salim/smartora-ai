<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="modelValue"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-xs"
        @click.self="$emit('update:modelValue', false)"
      >
        <div
          class="w-full overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-2xl"
          :class="maxWidthClass"
        >
          <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <div>
              <h3 class="text-base font-extrabold text-[#181A1F]">{{ title }}</h3>
              <p v-if="subtitle" class="mt-0.5 text-xs text-slate-500">{{ subtitle }}</p>
            </div>
            <button
              type="button"
              class="rounded-xl p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
              @click="$emit('update:modelValue', false)"
            >
              <X class="h-5 w-5" />
            </button>
          </div>
          <div class="max-h-[75vh] overflow-y-auto p-5">
            <slot />
          </div>
          <div v-if="$slots.footer" class="flex items-center justify-end gap-2 border-t border-gray-100 bg-slate-50/70 px-5 py-3.5">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { X } from 'lucide-vue-next'

const props = withDefaults(defineProps<{
  modelValue: boolean
  title: string
  subtitle?: string
  size?: 'sm' | 'md' | 'lg' | 'xl'
}>(), {
  subtitle: '',
  size: 'lg',
})

defineEmits<{
  (e: 'update:modelValue', value: boolean): void
}>()

const maxWidthClass = computed(() => {
  if (props.size === 'sm') return 'max-w-md'
  if (props.size === 'md') return 'max-w-xl'
  if (props.size === 'xl') return 'max-w-4xl'
  return 'max-w-2xl'
})
</script>
