<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Activity & Logs</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Audit log of all manual and scheduled backup jobs, output traces, and downloads.</p>
      </div>

      <!-- Status Filter -->
      <div class="flex items-center gap-1.5 p-1 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl">
        <button
          v-for="f in ['all', 'completed', 'failed', 'running']"
          :key="f"
          type="button"
          :class="[
            'px-3 py-1.5 rounded-lg text-xs font-medium capitalize transition-colors',
            currentFilter === f
              ? 'bg-white dark:bg-slate-800 text-teal-600 dark:text-teal-400 font-semibold shadow-xs'
              : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200',
          ]"
          @click="setFilter(f)"
        >
          {{ f }}
        </button>
      </div>
    </div>

    <!-- Runs Table -->
    <div class="bg-white border border-slate-200 dark:bg-slate-900 dark:border-slate-800 rounded-xl overflow-hidden shadow-xs transition-colors">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 dark:bg-slate-950/60 dark:text-slate-400 dark:border-slate-800">
            <tr>
              <th class="px-6 py-3.5 font-medium">Run ID & Type</th>
              <th class="px-6 py-3.5 font-medium">Database</th>
              <th class="px-6 py-3.5 font-medium">Storage Destination</th>
              <th class="px-6 py-3.5 font-medium">Status</th>
              <th class="px-6 py-3.5 font-medium">Size</th>
              <th class="px-6 py-3.5 font-medium">Duration</th>
              <th class="px-6 py-3.5 font-medium">Executed At</th>
              <th class="px-6 py-3.5 font-medium text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
            <tr v-if="runsStore.runs.length === 0">
              <td colspan="8" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                No backup activity found matching the selected filter.
              </td>
            </tr>
            <tr
              v-for="run in runsStore.runs"
              :key="run.id"
              class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors"
            >
              <td class="px-6 py-4 font-mono">
                <div class="flex items-center gap-2">
                  <span class="text-slate-900 dark:text-slate-200 font-bold">#{{ run.id }}</span>
                  <span
                    :class="[
                      'px-1.5 py-0.5 rounded text-[10px] font-sans uppercase font-medium',
                      run.trigger_type === 'manual'
                        ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20'
                        : 'bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700',
                    ]"
                  >
                    {{ run.trigger_type }}
                  </span>
                </div>
              </td>
              <td class="px-6 py-4 text-slate-900 dark:text-slate-200 font-medium">
                <div class="flex items-center gap-1.5">
                  <span class="px-1.5 py-0.5 rounded text-[10px] bg-slate-100 text-teal-700 dark:bg-slate-800 dark:text-teal-400 font-mono uppercase">
                    {{ run.database_connection?.driver || 'db' }}
                  </span>
                  <span>{{ run.database_connection?.name || '—' }}</span>
                </div>
              </td>
              <td class="px-6 py-4 text-slate-700 dark:text-slate-300">
                <span>{{ run.storage_destination?.name || '—' }}</span>
                <span v-if="run.filename" class="text-[10px] text-slate-500 dark:text-slate-400 block font-mono truncate max-w-[180px]">
                  {{ run.filename }}
                </span>
              </td>
              <td class="px-6 py-4">
                <StatusBadge :status="run.status" />
              </td>
              <td class="px-6 py-4 font-mono text-slate-700 dark:text-slate-300">
                {{ run.size ? formatBytes(run.size) : '—' }}
              </td>
              <td class="px-6 py-4 font-mono text-slate-500 dark:text-slate-400">
                {{ run.duration_seconds !== null ? `${run.duration_seconds}s` : '—' }}
              </td>
              <td class="px-6 py-4 text-slate-500 dark:text-slate-400">
                {{ formatDate(run.created_at) }}
              </td>
              <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button
                    v-if="run.status === 'completed'"
                    type="button"
                    class="p-1.5 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-teal-600 dark:text-teal-400 hover:text-teal-700 dark:hover:text-teal-300 transition-colors"
                    title="Download Backup"
                    @click="downloadRun(run)"
                  >
                    <Download class="w-3.5 h-3.5" />
                  </button>

                  <button
                    type="button"
                    class="p-1.5 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors"
                    title="View Logs"
                    @click="viewLog(run)"
                  >
                    <FileText class="w-3.5 h-3.5" />
                  </button>

                  <button
                    type="button"
                    class="p-1.5 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-rose-500 transition-colors"
                    title="Delete Backup"
                    @click="handleDelete(run)"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div
        v-if="runsStore.pagination.lastPage > 1"
        class="px-6 py-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400"
      >
        <span>
          Showing page {{ runsStore.pagination.currentPage }} of {{ runsStore.pagination.lastPage }} ({{ runsStore.pagination.total }} total)
        </span>
        <div class="flex items-center gap-2">
          <button
            type="button"
            :disabled="runsStore.pagination.currentPage === 1"
            class="px-3 py-1 rounded bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 disabled:opacity-40 text-slate-700 dark:text-slate-200 transition-colors"
            @click="goToPage(runsStore.pagination.currentPage - 1)"
          >
            Previous
          </button>
          <button
            type="button"
            :disabled="runsStore.pagination.currentPage === runsStore.pagination.lastPage"
            class="px-3 py-1 rounded bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 disabled:opacity-40 text-slate-700 dark:text-slate-200 transition-colors"
            @click="goToPage(runsStore.pagination.currentPage + 1)"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Log Modal -->
    <Modal v-model="logModalOpen" :title="`Backup Execution Log #${selectedRun?.id}`">
      <div v-if="selectedRun" class="space-y-4">
        <div class="grid grid-cols-2 gap-4 text-xs">
          <div>
            <span class="text-slate-500 dark:text-slate-400">Status:</span>
            <div class="mt-1"><StatusBadge :status="selectedRun.status" /></div>
          </div>
          <div>
            <span class="text-slate-500 dark:text-slate-400">File Size:</span>
            <p class="mt-1 font-mono text-slate-800 dark:text-slate-200">{{ selectedRun.size ? formatBytes(selectedRun.size) : '—' }}</p>
          </div>
          <div>
            <span class="text-slate-500 dark:text-slate-400">Duration:</span>
            <p class="mt-1 font-mono text-slate-800 dark:text-slate-200">{{ selectedRun.duration_seconds ? `${selectedRun.duration_seconds}s` : '—' }}</p>
          </div>
          <div>
            <span class="text-slate-500 dark:text-slate-400">Storage Path:</span>
            <p class="mt-1 font-mono text-slate-800 dark:text-slate-200 truncate" :title="selectedRun.path">{{ selectedRun.path || '—' }}</p>
          </div>
        </div>

        <div v-if="selectedRun.error_message" class="p-3.5 rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-700 dark:text-rose-300 text-xs font-mono whitespace-pre-wrap">
          <p class="font-bold text-rose-600 dark:text-rose-400 mb-1">Execution Error:</p>
          {{ selectedRun.error_message }}
        </div>

        <div v-if="selectedRun.output_log" class="space-y-1.5">
          <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Full Output / Stack Trace</label>
          <pre class="p-3.5 rounded-lg bg-slate-100 border border-slate-200 text-slate-800 dark:bg-slate-950 dark:border-slate-800 text-[11px] font-mono dark:text-slate-300 overflow-x-auto max-h-72 whitespace-pre-wrap">{{ selectedRun.output_log }}</pre>
        </div>
      </div>
    </Modal>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRunsStore } from '@/stores/runs'
import StatusBadge from '@/components/StatusBadge.vue'
import Modal from '@/components/Modal.vue'
import { Download, FileText, Trash2 } from 'lucide-vue-next'

const runsStore = useRunsStore()
const currentFilter = ref('all')
const logModalOpen = ref(false)
const selectedRun = ref(null)

onMounted(() => {
  runsStore.fetchRuns()
})

const setFilter = (filter) => {
  currentFilter.value = filter
  const filters = filter === 'all' ? {} : { status: filter }
  runsStore.fetchRuns(1, filters)
}

const goToPage = (page) => {
  const filters = currentFilter.value === 'all' ? {} : { status: currentFilter.value }
  runsStore.fetchRuns(page, filters)
}

const viewLog = (run) => {
  selectedRun.value = run
  logModalOpen.value = true
}

const downloadRun = (run) => {
  const token = localStorage.getItem('dbm_token')
  window.open(`/api/v1/runs/${run.id}/download?token=${token}`, '_blank')
}

const handleDelete = async (run) => {
  if (confirm(`Are you sure you want to delete backup run #${run.id}? This will also delete the backup archive from storage.`)) {
    try {
      await runsStore.deleteRun(run.id)
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to delete backup run')
    }
  }
}

const formatBytes = (bytes) => {
  if (!bytes || bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const formatDate = (dateStr) => {
  if (!dateStr) return '—'
  const date = new Date(dateStr)
  return date.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>
