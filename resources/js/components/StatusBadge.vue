<template>
  <span
    :class="[
      'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium',
      badgeClass,
    ]"
  >
    <span :class="['w-1.5 h-1.5 rounded-full', dotClass]" />
    <span class="capitalize">{{ status }}</span>
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: {
    type: String,
    required: true,
  },
})

const badgeClass = computed(() => {
  switch (props.status?.toLowerCase()) {
    case 'completed':
      return 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
    case 'running':
      return 'bg-amber-500/10 text-amber-400 border border-amber-500/20'
    case 'pending':
      return 'bg-sky-500/10 text-sky-400 border border-sky-500/20'
    case 'failed':
      return 'bg-rose-500/10 text-rose-400 border border-rose-500/20'
    default:
      return 'bg-slate-800 text-slate-400 border border-slate-700'
  }
})

const dotClass = computed(() => {
  switch (props.status?.toLowerCase()) {
    case 'completed':
      return 'bg-emerald-400'
    case 'running':
      return 'bg-amber-400 animate-ping'
    case 'pending':
      return 'bg-sky-400'
    case 'failed':
      return 'bg-rose-400'
    default:
      return 'bg-slate-400'
  }
})
</script>
