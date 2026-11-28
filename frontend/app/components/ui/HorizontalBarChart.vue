<template>
  <div class="space-y-4">
    <!-- Legend -->
    <div class="flex flex-wrap items-center justify-center gap-4 text-xs font-semibold text-slate-500">
      <span v-for="bar in bars" :key="bar.key" class="inline-flex items-center gap-1.5">
        <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: bar.color }"></span>
        <span>{{ bar.label }}</span>
      </span>
    </div>

    <!-- Horizontal Bars -->
    <div class="space-y-4 pt-2">
      <div v-for="bar in bars" :key="bar.key" class="grid grid-cols-12 items-center gap-3">
        <div class="col-span-3 truncate text-xs font-bold text-[#181A1F] sm:text-sm">
          {{ bar.label }}
        </div>
        <div class="col-span-7">
          <div class="h-4 w-full overflow-hidden rounded-full bg-slate-100">
            <div
              class="h-full rounded-full transition-all duration-500"
              :style="{
                width: `${bar.widthPercent}%`,
                backgroundColor: bar.color,
              }"
            ></div>
          </div>
        </div>
        <div class="col-span-2 text-end text-xs font-extrabold text-slate-600">
          {{ bar.count }}
        </div>
      </div>
    </div>

    <!-- Axis Ticks -->
    <div class="grid grid-cols-12 items-center gap-3 pt-1">
      <div class="col-span-3"></div>
      <div class="col-span-7 flex justify-between border-t border-gray-100 pt-2 text-[11px] font-semibold text-slate-400">
        <span>0</span>
        <span>{{ Math.ceil(maxCount * 0.5) }}</span>
        <span>{{ maxCount }}</span>
      </div>
      <div class="col-span-2"></div>
    </div>
  </div>
</template>

<script setup lang="ts">
interface BarRow {
  key: string
  label: string
  count: number
  color: string
}

const props = defineProps<{
  items: BarRow[]
}>()

const maxCount = computed(() => {
  const max = Math.max(...props.items.map(i => i.count), 0)
  return max > 0 ? max : 4
})

const bars = computed(() =>
  props.items.map(i => ({
    ...i,
    widthPercent: i.count > 0 ? Math.max(8, Math.round((i.count / maxCount.value) * 100)) : 4,
  }))
)
</script>
