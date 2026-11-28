<template>
  <component
    :is="to ? NuxtLinkComponent : 'div'"
    :to="to"
    class="group flex items-center justify-between gap-4 rounded-2xl border border-gray-100 bg-white p-5 transition duration-200 hover:border-gray-200 hover:shadow-sm"
    :class="{ 'cursor-pointer': Boolean(to) }"
  >
    <div class="min-w-0 flex-1 text-start">
      <p class="truncate text-xs font-semibold text-slate-500">{{ label }}</p>
      <div class="mt-1.5 flex items-baseline gap-2">
        <span class="text-2xl font-black tracking-tight text-[#181A1F] sm:text-[26px]">{{ value }}</span>
        <span v-if="unit" class="text-sm font-bold text-slate-700">{{ unit }}</span>
      </div>
      <div v-if="subtext || (dots && dots.length)" class="mt-2 flex flex-wrap items-center gap-2 text-[11px] font-medium text-slate-400">
        <template v-if="dots && dots.length">
          <span v-for="(dot, i) in dots" :key="i" class="inline-flex items-center gap-1 text-slate-500">
            <span class="h-1.5 w-1.5 rounded-full" :style="{ backgroundColor: dot.color }"></span>
            <span>{{ dot.label }}</span>
          </span>
        </template>
        <span v-else>{{ subtext }}</span>
      </div>
    </div>

    <div
      class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl transition duration-200 group-hover:scale-105"
      :class="iconBoxClass"
    >
      <slot name="icon" />
    </div>
  </component>
</template>

<script setup lang="ts">
const NuxtLinkComponent = resolveComponent('NuxtLink')

const props = withDefaults(defineProps<{
  label: string
  value: string | number
  unit?: string
  subtext?: string
  dots?: Array<{ label: string; color: string }>
  tone?: 'amber' | 'sky' | 'emerald' | 'indigo' | 'teal' | 'rose' | 'slate'
  to?: string
}>(), {
  unit: '',
  subtext: '',
  dots: () => [],
  tone: 'amber',
  to: '',
})

const iconBoxClass = computed(() => {
  switch (props.tone) {
    case 'sky':
      return 'bg-sky-50 text-sky-600'
    case 'emerald':
      return 'bg-emerald-50 text-emerald-600'
    case 'indigo':
      return 'bg-indigo-50 text-indigo-600'
    case 'teal':
      return 'bg-teal-50 text-teal-600'
    case 'rose':
      return 'bg-rose-50 text-rose-600'
    case 'slate':
      return 'bg-slate-100 text-slate-600'
    default:
      return 'bg-amber-50 text-amber-600'
  }
})
</script>
