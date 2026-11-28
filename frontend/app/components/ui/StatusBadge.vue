<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold whitespace-nowrap"
    :class="badgeClass"
  >
    <span v-if="dot" class="h-1.5 w-1.5 rounded-full" :class="dotClass"></span>
    <slot>{{ label }}</slot>
  </span>
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{
  label?: string
  tone?: 'amber' | 'emerald' | 'rose' | 'sky' | 'slate' | 'pink'
  dot?: boolean
}>(), {
  label: '',
  tone: 'slate',
  dot: false,
})

const badgeClass = computed(() => {
  switch (props.tone) {
    case 'amber':
      return 'bg-amber-50 text-amber-700 ring-1 ring-amber-200/70'
    case 'emerald':
      return 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200/70'
    case 'rose':
      return 'bg-rose-50 text-rose-700 ring-1 ring-rose-200/70'
    case 'sky':
      return 'bg-sky-50 text-sky-700 ring-1 ring-sky-200/70'
    case 'pink':
      return 'bg-pink-50 text-pink-700 ring-1 ring-pink-200/70'
    default:
      return 'bg-slate-100 text-slate-600 ring-1 ring-slate-200/70'
  }
})

const dotClass = computed(() => {
  switch (props.tone) {
    case 'amber':
      return 'bg-amber-500'
    case 'emerald':
      return 'bg-emerald-500'
    case 'rose':
      return 'bg-rose-500'
    case 'sky':
      return 'bg-sky-500'
    case 'pink':
      return 'bg-pink-500'
    default:
      return 'bg-slate-500'
  }
})
</script>
