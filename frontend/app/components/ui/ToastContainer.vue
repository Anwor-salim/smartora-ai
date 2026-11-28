<template>
  <div class="fixed top-4 left-4 z-50 flex w-[calc(100%-2rem)] max-w-sm flex-col gap-2.5 pointer-events-none">
    <TransitionGroup
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-2 scale-95"
      enter-to-class="opacity-100 translate-y-0 scale-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0 scale-100"
      leave-to-class="opacity-0 -translate-y-2 scale-95"
    >
      <div
        v-for="item in toasts"
        :key="item.id"
        class="pointer-events-auto flex items-start gap-3 rounded-2xl border bg-white p-3.5 shadow-lg shadow-slate-900/5"
        :class="borderClass(item.type)"
      >
        <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl" :class="iconBgClass(item.type)">
          <CheckCircle2 v-if="item.type === 'success'" class="h-4 w-4 text-emerald-600" />
          <XCircle v-else-if="item.type === 'error'" class="h-4 w-4 text-rose-600" />
          <AlertTriangle v-else-if="item.type === 'warning'" class="h-4 w-4 text-amber-600" />
          <Info v-else class="h-4 w-4 text-sky-600" />
        </div>
        <div class="min-w-0 flex-1">
          <p class="text-sm font-bold text-[#181A1F]">{{ item.title }}</p>
          <p v-if="item.message" class="mt-0.5 text-xs text-slate-500">{{ item.message }}</p>
        </div>
        <button
          type="button"
          class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
          @click="remove(item.id)"
        >
          <X class="h-4 w-4" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup lang="ts">
import { CheckCircle2, XCircle, AlertTriangle, Info, X } from 'lucide-vue-next'

const { toasts, remove } = useToast()

const borderClass = (type: string) => {
  if (type === 'success') return 'border-emerald-100'
  if (type === 'error') return 'border-rose-100'
  if (type === 'warning') return 'border-amber-100'
  return 'border-sky-100'
}

const iconBgClass = (type: string) => {
  if (type === 'success') return 'bg-emerald-50'
  if (type === 'error') return 'bg-rose-50'
  if (type === 'warning') return 'bg-amber-50'
  return 'bg-sky-50'
}
</script>
