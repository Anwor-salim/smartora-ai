<template>
  <div class="flex flex-col items-center gap-6 sm:flex-row sm:items-center sm:justify-between">
    <!-- Breakdown rows -->
    <div class="w-full flex-1 space-y-4">
      <div
        v-for="item in items"
        :key="item.key"
        class="flex items-center justify-between gap-3"
      >
        <div class="flex items-center gap-2.5 min-w-0">
          <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: item.color }"></span>
          <div class="min-w-0">
            <p class="truncate text-sm font-bold text-[#181A1F]">{{ item.label }}</p>
            <p class="text-xs font-semibold text-slate-400">{{ item.count }} {{ unitLabel }}</p>
          </div>
        </div>

        <span
          class="rounded-lg px-2.5 py-1 text-xs font-extrabold"
          :style="{ backgroundColor: `${item.color}18`, color: item.color }"
        >
          {{ item.percent }}%
        </span>
      </div>
    </div>

    <!-- SVG Ring Donut -->
    <div class="relative flex h-40 w-40 shrink-0 items-center justify-center">
      <svg viewBox="0 0 120 120" class="h-full w-full -rotate-90">
        <circle
          cx="60"
          cy="60"
          r="46"
          fill="none"
          stroke="#F1F5F9"
          stroke-width="14"
        />
        <circle
          v-for="seg in segments"
          :key="seg.key"
          cx="60"
          cy="60"
          r="46"
          fill="none"
          :stroke="seg.color"
          stroke-width="14"
          stroke-linecap="round"
          :stroke-dasharray="`${seg.length} ${circumference}`"
          :stroke-dashoffset="-seg.offset"
          class="transition-all duration-500"
        />
      </svg>

      <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
        <span class="text-[11px] font-semibold text-slate-400">{{ centerLabel }}</span>
        <span class="mt-0.5 text-xl font-black text-[#181A1F]">{{ total }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
interface BreakdownItem {
  key: string
  label: string
  count: number
  percent: number
  color: string
}

const props = withDefaults(defineProps<{
  items: BreakdownItem[]
  total: number
  centerLabel?: string
  unitLabel?: string
}>(), {
  centerLabel: 'الإجمالي',
  unitLabel: 'سجل',
})

const radius = 46
const circumference = 2 * Math.PI * radius

const segments = computed(() => {
  const sum = props.items.reduce((acc, i) => acc + Math.max(0, i.count), 0)
  if (sum === 0) return []

  let runningOffset = 0
  return props.items
    .filter(i => i.count > 0)
    .map(i => {
      const fraction = i.count / sum
      const length = fraction * circumference
      const seg = {
        key: i.key,
        color: i.color,
        length,
        offset: runningOffset,
      }
      runningOffset += length
      return seg
    })
})
</script>
