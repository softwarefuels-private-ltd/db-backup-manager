<template>
  <div class="space-y-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Overview</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Live status of database instances, backup schedules, and remote storage.</p>
      </div>
      <div class="flex items-center gap-3">
        <RouterLink
          to="/schedules"
          class="px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white dark:text-slate-950 rounded-lg text-sm font-semibold transition-colors flex items-center gap-2 shadow-sm"
        >
          <CalendarClock class="w-4 h-4" />
          <span>New Schedule</span>
        </RouterLink>
      </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      <div class="bg-white border border-slate-200 dark:bg-slate-900 dark:border-slate-800 rounded-xl p-5 space-y-2 shadow-xs transition-colors">
        <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
          <span class="text-xs font-medium">Databases</span>
          <Database class="w-4 h-4 text-teal-500 dark:text-teal-400" />
        </div>
        <p class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ dashboardStore.stats.total_connections }}</p>
        <span class="text-[11px] text-slate-500 dark:text-slate-400">Connected target databases</span>
      </div>

      <div class="bg-white border border-slate-200 dark:bg-slate-900 dark:border-slate-800 rounded-xl p-5 space-y-2 shadow-xs transition-colors">
        <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
          <span class="text-xs font-medium">Active Schedules</span>
          <CalendarClock class="w-4 h-4 text-sky-500 dark:text-sky-400" />
        </div>
        <p class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ dashboardStore.stats.active_schedules }} / {{ dashboardStore.stats.total_schedules }}</p>
        <span class="text-[11px] text-slate-500 dark:text-slate-400">Cron automated backups</span>
      </div>

      <div class="bg-white border border-slate-200 dark:bg-slate-900 dark:border-slate-800 rounded-xl p-5 space-y-2 shadow-xs transition-colors">
        <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
          <span class="text-xs font-medium">Storage Used</span>
          <HardDrive class="w-4 h-4 text-purple-500 dark:text-purple-400" />
        </div>
        <p class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ formatBytes(dashboardStore.stats.total_storage_bytes) }}</p>
        <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ dashboardStore.stats.total_destinations }} storage disks</span>
      </div>

      <div class="bg-white border border-slate-200 dark:bg-slate-900 dark:border-slate-800 rounded-xl p-5 space-y-2 shadow-xs transition-colors">
        <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
          <span class="text-xs font-medium">Total Runs</span>
          <CheckCircle2 class="w-4 h-4 text-emerald-500 dark:text-emerald-400" />
        </div>
        <div class="flex items-baseline gap-2">
          <p class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ dashboardStore.stats.completed_runs }}</p>
          <span v-if="dashboardStore.stats.failed_runs > 0" class="text-xs text-rose-500 dark:text-rose-400 font-medium">
            ({{ dashboardStore.stats.failed_runs }} failed)
          </span>
        </div>
        <span class="text-[11px] text-slate-500 dark:text-slate-400">Total backup executions</span>
      </div>
    </div>

    <!-- Database Connection Status and Health (New Row Above Recent Backup Activity) -->
    <div class="bg-white border border-slate-200 dark:bg-slate-900 dark:border-slate-800 rounded-xl overflow-hidden flex flex-col shadow-xs transition-colors">
      <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="p-2 rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400">
            <Activity class="w-4 h-4" />
          </div>
          <div>
            <div class="flex items-center gap-2">
              <h3 class="font-semibold text-sm text-slate-900 dark:text-slate-100">Database Connection Health</h3>
              <span
                v-if="totalDatabases > 0"
                :class="[
                  'px-2 py-0.5 rounded-full text-[10px] font-medium border flex items-center gap-1.5',
                  allDatabasesHealthy
                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20'
                    : 'bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-500/20',
                ]"
              >
                <span
                  class="w-1.5 h-1.5 rounded-full"
                  :class="allDatabasesHealthy ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'"
                />
                {{ allDatabasesHealthy ? 'All Systems Healthy' : `${unhealthyCount} Unreachable` }}
              </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Live latency, driver status, and target reachability.</p>
          </div>
        </div>

        <div class="flex items-center gap-2.5 self-end sm:self-auto">
          

          <button
            type="button"
            :disabled="dashboardStore.checkingHealth"
            class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 text-xs font-medium transition-colors flex items-center gap-1.5 disabled:opacity-50"
            title="Refresh database connectivity & health checks"
            @click="handleCheckAllHealth"
          >
            <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': dashboardStore.checkingHealth }" />
            <span>{{ dashboardStore.checkingHealth ? 'Checking...' : 'Check Health' }}</span>
          </button>
          <RouterLink to="/connections" class="text-xs text-teal-600 dark:text-teal-400 hover:underline font-medium">
            Manage →
          </RouterLink>
        </div>
      </div>

      <!-- Test Feedback Toast / Alert -->
      <div
        v-if="testFeedback"
        :class="[
          'mx-6 mt-4 p-3 rounded-lg text-xs flex items-center justify-between border transition-all',
          testFeedback.success
            ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-700 dark:text-emerald-300'
            : 'bg-rose-500/10 border-rose-500/20 text-rose-700 dark:text-rose-300',
        ]"
      >
        <div class="flex items-center gap-2">
          <CheckCircle2 v-if="testFeedback.success" class="w-4 h-4 shrink-0 text-emerald-500 dark:text-emerald-400" />
          <AlertCircle v-else class="w-4 h-4 shrink-0 text-rose-500 dark:text-rose-400" />
          <span>{{ testFeedback.message }}</span>
        </div>
        <button type="button" class="text-xs opacity-70 hover:opacity-100 p-1" @click="testFeedback = null">✕</button>
      </div>

      <div class="overflow-x-auto flex-1">
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 dark:bg-slate-950/40 dark:text-slate-400 dark:border-slate-800">
            <tr>
              <th class="px-6 py-3 font-medium">Database</th>
        
              <th class="px-6 py-3 font-medium">Connection Status</th>
              <th class="px-6 py-3 font-medium">Latency</th>
              <th class="px-6 py-3 font-medium">Last Backup</th>
              <th class="px-6 py-3 font-medium">Checked</th>
              <th class="px-6 py-3 font-medium text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
            <tr v-if="dashboardStore.databaseConnections.length === 0">
              <td colspan="7" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">
                <div class="flex flex-col items-center justify-center gap-2">
                  <Database class="w-6 h-6 text-slate-400" />
                  <p class="text-xs">No database connections configured yet.</p>
                  <RouterLink
                    to="/connections"
                    class="mt-1 px-3 py-1.5 bg-teal-500 hover:bg-teal-600 text-white dark:text-slate-950 rounded-lg text-xs font-semibold transition-colors"
                  >
                    + Add Database Connection
                  </RouterLink>
                </div>
              </td>
            </tr>
            <tr
              v-for="conn in dashboardStore.databaseConnections"
              :key="conn.id"
              class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors"
            >
              <td class="px-6 py-3.5 font-medium text-slate-900 dark:text-slate-200">
                <div class="flex items-center gap-2.5">
                  <span
                    :class="[
                      'px-1.5 py-0.5 rounded text-[10px] font-mono font-bold uppercase tracking-wider',
                      driverBadgeClass(conn.driver),
                    ]"
                  >
                    {{ conn.driver }}
                  </span>
                  <div>
                    <RouterLink
                      to="/connections"
                      class="hover:text-teal-600 dark:hover:text-teal-400 transition-colors font-semibold"
                    >
                      {{ conn.name }}
                    </RouterLink>
                    <p class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">
                      {{ conn.username ? `User: ${conn.username}` : (conn.driver === 'sqlite' ? 'SQLite Engine' : '—') }}
                    </p>
                  </div>
                </div>
              </td>

             

              <td class="px-6 py-3.5">
                <div v-if="dashboardStore.testingConnectionId === conn.id" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-500/20">
                  <span class="w-3 h-3 border border-amber-500 border-t-transparent rounded-full animate-spin" />
                  <span>Checking...</span>
                </div>
                <div v-else-if="conn.health?.healthy" class="space-y-0.5">
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
                    <span>Healthy</span>
                  </span>
                </div>
                <div v-else class="space-y-1">
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-rose-500/10 text-rose-700 dark:text-rose-400 border border-rose-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500" />
                    <span>Unreachable</span>
                  </span>
                  <p v-if="conn.health?.message" class="text-[10px] text-rose-600 dark:text-rose-400 font-mono truncate max-w-xs block" :title="conn.health.message">
                    {{ conn.health.message }}
                  </p>
                </div>
              </td>

              <td class="px-6 py-3.5">
                <div v-if="conn.health?.latency_ms !== null && conn.health?.latency_ms !== undefined" class="flex items-center gap-1.5">
                  <Zap class="w-3.5 h-3.5 text-amber-500 shrink-0" />
                  <span class="font-mono text-xs font-semibold" :class="latencyColorClass(conn.health.latency_ms)">
                    {{ conn.health.latency_ms }} ms
                  </span>
                </div>
                <span v-else class="text-slate-400 dark:text-slate-500">—</span>
              </td>

              <td class="px-6 py-3.5">
                <div v-if="conn.last_backup_run" class="flex items-center gap-2">
                  <StatusBadge :status="conn.last_backup_run.status" />
                  <span class="text-[11px] text-slate-500 dark:text-slate-400">
                    {{ formatDate(conn.last_backup_run.created_at) }}
                  </span>
                </div>
                <span v-else class="text-slate-400 dark:text-slate-500 text-xs">
                  No runs yet
                </span>
              </td>

              <td class="px-6 py-3.5 text-slate-500 dark:text-slate-400">
                {{ formatRelativeTime(conn.health?.checked_at) }}
              </td>

              <td class="px-6 py-3.5 text-right">
                <button
                  type="button"
                  :disabled="dashboardStore.testingConnectionId === conn.id"
                  class="px-2.5 py-1 rounded-md bg-slate-100 hover:bg-slate-200 text-teal-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-teal-400 text-xs font-medium transition-colors inline-flex items-center gap-1.5 disabled:opacity-50"
                  title="Test database connection"
                  @click="handleTestConnection(conn)"
                >
                  <span v-if="dashboardStore.testingConnectionId === conn.id" class="w-3 h-3 border border-teal-500 border-t-transparent rounded-full animate-spin" />
                  <Activity v-else class="w-3 h-3" />
                  <span>{{ dashboardStore.testingConnectionId === conn.id ? 'Testing...' : 'Test' }}</span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Two-column Section: Recent Runs + Upcoming Schedules -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Recent Backup Activity (2 cols) -->
      <div class="lg:col-span-2 bg-white border border-slate-200 dark:bg-slate-900 dark:border-slate-800 rounded-xl overflow-hidden flex flex-col shadow-xs transition-colors">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
          <h3 class="font-semibold text-sm text-slate-900 dark:text-slate-100 flex items-center gap-2">
            <History class="w-4 h-4 text-teal-500 dark:text-teal-400" />
            <span>Recent Backup Activity</span>
          </h3>
          <RouterLink to="/runs" class="text-xs text-teal-600 dark:text-teal-400 hover:underline font-medium">
            View All →
          </RouterLink>
        </div>

        <div class="overflow-x-auto flex-1">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 dark:bg-slate-950/40 dark:text-slate-400 dark:border-slate-800">
              <tr>
                <th class="px-6 py-3 font-medium">Database</th>
                <th class="px-6 py-3 font-medium">Storage</th>
                <th class="px-6 py-3 font-medium">Status</th>
                <th class="px-6 py-3 font-medium">Size</th>
                <th class="px-6 py-3 font-medium">Time</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
              <tr v-if="dashboardStore.recentRuns.length === 0">
                <td colspan="5" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">
                  No backup activity recorded yet.
                </td>
              </tr>
              <tr
                v-for="run in dashboardStore.recentRuns"
                :key="run.id"
                class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors cursor-pointer"
                @click="openRunLog(run)"
              >
                <td class="px-6 py-3.5 font-medium text-slate-900 dark:text-slate-200">
                  <div class="flex items-center gap-2">
                    <span class="px-1.5 py-0.5 rounded text-[10px] bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400 font-mono uppercase">
                      {{ run.database_connection?.driver || 'db' }}
                    </span>
                    <span>{{ run.database_connection?.name || 'Manual' }}</span>
                  </div>
                </td>
                <td class="px-6 py-3.5 text-slate-600 dark:text-slate-400">
                  {{ run.storage_destination?.name || '—' }}
                </td>
                <td class="px-6 py-3.5">
                  <StatusBadge :status="run.status" />
                </td>
                <td class="px-6 py-3.5 font-mono text-slate-700 dark:text-slate-300">
                  {{ run.size ? formatBytes(run.size) : '—' }}
                </td>
                <td class="px-6 py-3.5 text-slate-500 dark:text-slate-400">
                  {{ formatDate(run.created_at) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Upcoming Schedules (1 col) -->
      <div class="bg-white border border-slate-200 dark:bg-slate-900 dark:border-slate-800 rounded-xl overflow-hidden flex flex-col shadow-xs transition-colors">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
          <h3 class="font-semibold text-sm text-slate-900 dark:text-slate-100 flex items-center gap-2">
            <Clock class="w-4 h-4 text-sky-500 dark:text-sky-400" />
            <span>Next Scheduled Runs</span>
          </h3>
          <RouterLink to="/schedules" class="text-xs text-teal-600 dark:text-teal-400 hover:underline font-medium">
            Manage →
          </RouterLink>
        </div>

        <div class="p-4 flex-1 space-y-3 overflow-y-auto">
          <div v-if="dashboardStore.upcomingSchedules.length === 0" class="text-center py-8 text-slate-500 dark:text-slate-400 text-xs">
            No active schedules configured.
          </div>

          <div
            v-for="schedule in dashboardStore.upcomingSchedules"
            :key="schedule.id"
            class="p-3.5 rounded-lg bg-slate-50 border border-slate-200 dark:bg-slate-950/60 dark:border-slate-800 space-y-2 transition-colors"
          >
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate">{{ schedule.name }}</span>
              <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-slate-200/80 text-teal-700 dark:bg-slate-800 dark:text-teal-400 border border-slate-300 dark:border-slate-700">
                {{ schedule.cron_expression }}
              </span>
            </div>

            <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
              <span>{{ schedule.database_connection?.name }}</span>
              <span class="text-sky-600 dark:text-sky-400 font-mono">{{ formatDate(schedule.next_run_at) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Run Log Modal -->
    <Modal v-model="logModalOpen" :title="`Backup Run #${selectedRun?.id}`">
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
            <span class="text-slate-500 dark:text-slate-400">Target File:</span>
            <p class="mt-1 font-mono text-slate-800 dark:text-slate-200 truncate">{{ selectedRun.filename || '—' }}</p>
          </div>
        </div>

        <div v-if="selectedRun.error_message" class="p-3 rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-xs font-mono whitespace-pre-wrap">
          {{ selectedRun.error_message }}
        </div>

        <div v-if="selectedRun.output_log" class="space-y-1.5">
          <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Execution Log</label>
          <pre class="p-3 rounded-lg bg-slate-100 border border-slate-200 text-slate-800 dark:bg-slate-950 dark:border-slate-800 text-[11px] font-mono dark:text-slate-300 overflow-x-auto max-h-60 whitespace-pre-wrap">{{ selectedRun.output_log }}</pre>
        </div>
      </div>
    </Modal>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useDashboardStore } from '@/stores/dashboard'
import StatusBadge from '@/components/StatusBadge.vue'
import Modal from '@/components/Modal.vue'
import {
  Database,
  CalendarClock,
  HardDrive,
  CheckCircle2,
  History,
  Clock,
  Activity,
  RefreshCw,
  Zap,
  AlertCircle,
  Cpu,
} from 'lucide-vue-next'

const dashboardStore = useDashboardStore()
const logModalOpen = ref(false)
const selectedRun = ref(null)
const testFeedback = ref(null)

onMounted(() => {
  dashboardStore.fetchStats()
})

const totalDatabases = computed(() => {
  return dashboardStore.databaseConnections.length
})

const healthyCount = computed(() => {
  return dashboardStore.databaseConnections.filter((c) => c.health?.healthy).length
})

const allDatabasesHealthy = computed(() => {
  return totalDatabases.value > 0 && healthyCount.value === totalDatabases.value
})

const unhealthyCount = computed(() => {
  return totalDatabases.value - healthyCount.value
})

const openRunLog = (run) => {
  selectedRun.value = run
  logModalOpen.value = true
}

const handleTestConnection = async (conn) => {
  testFeedback.value = null
  const res = await dashboardStore.testConnection(conn.id)
  testFeedback.value = {
    connId: conn.id,
    connName: conn.name,
    success: res.success,
    message: res.message,
  }
}

const handleCheckAllHealth = async () => {
  testFeedback.value = null
  const res = await dashboardStore.checkAllHealth()
  if (!res.success) {
    testFeedback.value = {
      success: false,
      message: res.message,
    }
  }
}

const latencyColorClass = (ms) => {
  if (ms === null || ms === undefined) return 'text-slate-500'
  if (ms < 50) return 'text-emerald-600 dark:text-emerald-400'
  if (ms < 200) return 'text-sky-600 dark:text-sky-400'
  return 'text-amber-600 dark:text-amber-400'
}

const driverBadgeClass = (driver) => {
  switch (driver?.toLowerCase()) {
    case 'mysql':
    case 'mariadb':
      return 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20'
    case 'pgsql':
    case 'postgres':
    case 'postgresql':
      return 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20'
    case 'mongodb':
    case 'mongo':
      return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
    case 'sqlite':
      return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20'
    default:
      return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border border-slate-200 dark:border-slate-700'
  }
}

const formatRelativeTime = (dateStr) => {
  if (!dateStr) return '—'
  const date = new Date(dateStr)
  const now = new Date()
  const diffSec = Math.floor((now - date) / 1000)
  if (diffSec < 10) return 'Just now'
  if (diffSec < 60) return `${diffSec}s ago`
  const diffMin = Math.floor(diffSec / 60)
  if (diffMin < 60) return `${diffMin}m ago`
  const diffHr = Math.floor(diffMin / 60)
  if (diffHr < 24) return `${diffHr}h ago`
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
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
