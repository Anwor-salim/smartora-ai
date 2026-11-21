<template>
  <span :class="['status-badge', type, size]">
    {{ label }}
  </span>
</template>

<script setup lang="ts">
const props = defineProps<{
  type: string
  label: string
  size?: 'sm' | 'md' | 'lg'
}>()

const size = props.size || 'md'
</script>

<style scoped>
.status-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  border-radius: 999px;
  white-space: nowrap;
}

.sm { padding: 0.125rem 0.5rem; font-size: 0.75rem; }
.md { padding: 0.25rem 0.75rem; font-size: 0.875rem; }
.lg { padding: 0.5rem 1rem; font-size: 1rem; }

/* Statuses */
.paid { background-color: #d1fae5; color: #065f46; }
.unpaid { background-color: #fee2e2; color: #991b1b; }
.partially_paid { background-color: #fef3c7; color: #92400e; }
.draft { background-color: #f1f5f9; color: #475569; }

/* Types */
.cash { border: 1px solid #93c5fd; color: #1e40af; }
.credit { border: 1px solid #c4b5fd; color: #5b21b6; }
.return { border: 1px solid #fca5a5; color: #991b1b; }
</style>
