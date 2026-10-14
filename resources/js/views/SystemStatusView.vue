<template>
  <div class="space-y-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <div class="flex items-center gap-3">
          <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">System Drivers & Requirements</h2>
          <span
            v-if="status"
            :class="[
              'px-2.5 py-0.5 rounded-full text-xs font-semibold border flex items-center gap-1.5',
              status.summary.all_ready
                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20'
                : 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/20',
            ]"
          >
            <span
              class="w-1.5 h-1.5 rounded-full"
              :class="status.summary.all_ready ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'"
            />
            {{ status.summary.ready_engines }} of {{ status.summary.total_engines }} Engines Ready
          </span>
        </div>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Verify host server readiness, PHP extensions, and command-line dump utilities for MySQL, MariaDB, PostgreSQL, MongoDB, and SQLite.
        </p>
      </div>

      <div class="flex items-center gap-3">
        <button
          type="button"
          :disabled="loading"
          class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 rounded-lg text-sm font-semibold transition-colors flex items-center gap-2 shadow-xs disabled:opacity-50"
          @click="fetchStatus"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
          <span>{{ loading ? 'Re-checking...' : 'Re-check Status' }}</span>
        </button>
      </div>
    </div>

    <!-- Alert if any engine is missing tools -->
    <div
      v-if="status && !status.summary.all_ready"
      class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-800 dark:text-amber-200 flex items-start gap-3 text-sm shadow-xs"
    >
      <AlertTriangle class="w-5 h-5 shrink-0 text-amber-500 mt-0.5" />
      <div class="space-y-1">
        <p class="font-semibold">Some database backup dump tools are missing from the server.</p>
        <p class="text-xs text-amber-700 dark:text-amber-300">
          The database connections can still be tested and queried, but scheduled or manual backup runs require the respective CLI utilities below (e.g. <code class="font-mono bg-amber-500/20 px-1 py-0.5 rounded">pg_dump</code> or <code class="font-mono bg-amber-500/20 px-1 py-0.5 rounded">mongodump</code>) installed on the host machine.
        </p>
      </div>
    </div>

    <!-- Engine Readiness Grid (4 Cards) -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
      <div
        v-for="engine in enginesList"
        :key="engine.id"
        class="bg-white border border-slate-200 dark:bg-slate-900 dark:border-slate-800 rounded-xl p-5 flex flex-col justify-between space-y-4 shadow-xs transition-colors"
      >
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <span
              :class="[
                'px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase tracking-wider',
                engineBadgeClass(engine.id),
              ]"
            >
              {{ engine.name }}
            </span>
            <span
              :class="[
                'px-2 py-0.5 rounded-full text-[11px] font-medium border flex items-center gap-1',
                engine.ready
                  ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20'
                  : 'bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-500/20',
              ]"
            >
              <CheckCircle2 v-if="engine.ready" class="w-3 h-3 text-emerald-500" />
              <AlertCircle v-else class="w-3 h-3 text-rose-500" />
              <span>{{ engine.ready ? 'Ready to Dump' : 'Missing Tools' }}</span>
            </span>
          </div>

          <div class="space-y-2 pt-1 text-xs">
            <!-- PHP Driver -->
            <div class="flex items-center justify-between p-2 rounded-lg bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800/60">
              <span class="text-slate-600 dark:text-slate-400 font-mono">{{ engine.php_driver.name }}</span>
              <span
                :class="[
                  'px-1.5 py-0.5 rounded text-[10px] font-semibold flex items-center gap-1',
                  engine.php_driver.installed
                    ? 'text-emerald-600 dark:text-emerald-400'
                    : 'text-rose-600 dark:text-rose-400',
                ]"
              >
                {{ engine.php_driver.installed ? '✓ Extension Loaded' : '✗ Missing Extension' }}
              </span>
            </div>

            <!-- CLI Dump Utility -->
            <div class="flex items-center justify-between p-2 rounded-lg bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800/60">
              <div class="min-w-0 pr-2">
                <span class="text-slate-600 dark:text-slate-400 font-mono block truncate">{{ engine.cli_tool.name }}</span>
                <span v-if="engine.cli_tool.path" class="text-[10px] text-slate-400 font-mono block truncate" :title="engine.cli_tool.path">
                  {{ engine.cli_tool.path }}
                </span>
              </div>
              <span
                :class="[
                  'px-1.5 py-0.5 rounded text-[10px] font-semibold shrink-0',
                  engine.cli_tool.installed
                    ? 'text-emerald-600 dark:text-emerald-400'
                    : 'text-rose-600 dark:text-rose-400',
                ]"
              >
                {{ engine.cli_tool.installed ? '✓ Binary Installed' : '✗ Missing Binary' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Installation helper if not ready -->
        <div v-if="!engine.ready && engine.install_hints" class="pt-2 border-t border-slate-100 dark:border-slate-800/80 space-y-1.5">
          <span class="text-[10px] font-medium text-slate-500 uppercase tracking-wider block">How to install:</span>
          <div class="relative group">
            <pre class="p-2 rounded bg-slate-100 text-slate-800 dark:bg-slate-950 dark:text-slate-300 text-[11px] font-mono overflow-x-auto whitespace-pre-wrap break-all">{{ getInstallCommand(engine) }}</pre>
            <button
              type="button"
              class="absolute top-1.5 right-1.5 p-1 rounded bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-slate-900 dark:hover:text-slate-100 transition-colors opacity-80 group-hover:opacity-100"
              title="Copy install command"
              @click="copyToClipboard(getInstallCommand(engine))"
            >
              <Check v-if="copiedCommand === getInstallCommand(engine)" class="w-3 h-3 text-emerald-500" />
              <Copy v-else class="w-3 h-3" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Two Columns: CLI Dump Tools Detailed List & PHP Extensions -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- CLI Dump Tools -->
      <div class="bg-white border border-slate-200 dark:bg-slate-900 dark:border-slate-800 rounded-xl overflow-hidden shadow-xs transition-colors flex flex-col">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
          <h3 class="font-semibold text-sm text-slate-900 dark:text-slate-100 flex items-center gap-2">
            <Terminal class="w-4 h-4 text-teal-500 dark:text-teal-400" />
            <span>Command-Line Dump Utilities</span>
          </h3>
          <span class="text-xs text-slate-500 font-mono">ExecutableFinder</span>
        </div>

        <div class="overflow-x-auto flex-1">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 dark:bg-slate-950/40 dark:text-slate-400 dark:border-slate-800">
              <tr>
                <th class="px-6 py-3 font-medium">Binary</th>
                <th class="px-6 py-3 font-medium">Engine</th>
                <th class="px-6 py-3 font-medium">Status</th>
                <th class="px-6 py-3 font-medium">Detected Path</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
              <tr v-for="bin in binariesList" :key="bin.name" class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                <td class="px-6 py-3.5 font-mono font-semibold text-slate-900 dark:text-slate-200">
                  {{ bin.name }}
                </td>
                <td class="px-6 py-3.5 text-slate-600 dark:text-slate-400">
                  {{ bin.engine }}
                </td>
                <td class="px-6 py-3.5">
                  <span
                    :class="[
                      'inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium border',
                      bin.installed
                        ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20'
                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border-slate-200 dark:border-slate-700',
                    ]"
                  >
                    <span class="w-1.5 h-1.5 rounded-full" :class="bin.installed ? 'bg-emerald-500' : 'bg-slate-400'" />
                    {{ bin.installed ? 'Installed' : 'Not Found' }}
                  </span>
                </td>
                <td class="px-6 py-3.5 font-mono text-[11px] text-slate-500 dark:text-slate-400">
                  <span v-if="bin.path" class="truncate max-w-xs block" :title="bin.version || bin.path">
                    {{ bin.path }}
                  </span>
                  <span v-else class="text-slate-400 dark:text-slate-500 italic">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- PHP Drivers & Extensions -->
      <div class="bg-white border border-slate-200 dark:bg-slate-900 dark:border-slate-800 rounded-xl overflow-hidden shadow-xs transition-colors flex flex-col">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
          <h3 class="font-semibold text-sm text-slate-900 dark:text-slate-100 flex items-center gap-2">
            <Cpu class="w-4 h-4 text-sky-500 dark:text-sky-400" />
            <span>PHP Extensions & Drivers</span>
          </h3>
          <span class="text-xs text-slate-500 font-mono">PHP {{ status?.system_info?.php_version }}</span>
        </div>

        <div class="overflow-x-auto flex-1">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 dark:bg-slate-950/40 dark:text-slate-400 dark:border-slate-800">
              <tr>
                <th class="px-6 py-3 font-medium">Extension</th>
                <th class="px-6 py-3 font-medium">Target Service</th>
                <th class="px-6 py-3 font-medium">Status</th>
                <th class="px-6 py-3 font-medium">Description</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
              <tr v-for="ext in extensionsList" :key="ext.name" class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                <td class="px-6 py-3.5 font-mono font-semibold text-slate-900 dark:text-slate-200">
                  {{ ext.name }}
                </td>
                <td class="px-6 py-3.5 text-slate-600 dark:text-slate-400">
                  {{ ext.engine }}
                </td>
                <td class="px-6 py-3.5">
                  <span
                    :class="[
                      'inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium border',
                      ext.installed
                        ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20'
                        : 'bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-500/20',
                    ]"
                  >
                    <span class="w-1.5 h-1.5 rounded-full" :class="ext.installed ? 'bg-emerald-500' : 'bg-rose-500'" />
                    {{ ext.installed ? 'Loaded' : 'Missing' }}
                  </span>
                </td>
                <td class="px-6 py-3.5 text-slate-500 dark:text-slate-400">
                  {{ ext.description }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Server & Environment Specs -->
    <div class="bg-white border border-slate-200 dark:bg-slate-900 dark:border-slate-800 rounded-xl p-6 shadow-xs transition-colors space-y-4">
      <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800/80 pb-3">
        <h3 class="font-semibold text-sm text-slate-900 dark:text-slate-100 flex items-center gap-2">
          <Server class="w-4 h-4 text-purple-500 dark:text-purple-400" />
          <span>Server Execution Environment</span>
        </h3>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
        <div class="p-3.5 rounded-lg bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800/60 space-y-1">
          <span class="text-slate-500 dark:text-slate-400">PHP Version</span>
          <p class="font-semibold font-mono text-slate-800 dark:text-slate-200">{{ status?.system_info?.php_version || '—' }}</p>
        </div>

        <div class="p-3.5 rounded-lg bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800/60 space-y-1">
          <span class="text-slate-500 dark:text-slate-400">Operating System</span>
          <p class="font-semibold text-slate-800 dark:text-slate-200 truncate" :title="status?.system_info?.os">
            {{ status?.system_info?.os || '—' }}
          </p>
        </div>

        <div class="p-3.5 rounded-lg bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800/60 space-y-1">
          <span class="text-slate-500 dark:text-slate-400">Memory Limit</span>
          <p class="font-semibold font-mono text-slate-800 dark:text-slate-200">{{ status?.system_info?.memory_limit || '—' }}</p>
        </div>

        <div class="p-3.5 rounded-lg bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800/60 space-y-1">
          <span class="text-slate-500 dark:text-slate-400">Max Execution Time</span>
          <p class="font-semibold font-mono text-slate-800 dark:text-slate-200">{{ status?.system_info?.max_execution_time || '—' }}</p>
        </div>

        <div class="p-3.5 rounded-lg bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800/60 space-y-1 sm:col-span-2">
          <span class="text-slate-500 dark:text-slate-400">Temporary Dump Directory</span>
          <div class="flex items-center justify-between gap-2 mt-0.5">
            <span class="font-mono text-slate-800 dark:text-slate-200 truncate" :title="status?.system_info?.temp_directory">
              {{ status?.system_info?.temp_directory }}
            </span>
            <span
              v-if="status?.system_info?.temp_directory_writable"
              class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20 shrink-0"
            >
              ✓ Writable
            </span>
          </div>
        </div>

        <div class="p-3.5 rounded-lg bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800/60 space-y-1 sm:col-span-2">
          <span class="text-slate-500 dark:text-slate-400">Free Storage Space</span>
          <p class="font-semibold font-mono text-slate-800 dark:text-slate-200">
            {{ formatBytes(status?.system_info?.free_disk_space_bytes) }} available
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import client from '@/api/client'
import {
  Server,
  Cpu,
  Terminal,
  CheckCircle2,
  AlertCircle,
  AlertTriangle,
  RefreshCw,
  Copy,
  Check,
} from 'lucide-vue-next'

const status = ref(null)
const loading = ref(false)
const copiedCommand = ref(null)

const fetchStatus = async () => {
  loading.value = true
  try {
    const { data } = await client.get('/system/status')
    status.value = data
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to fetch system status')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchStatus()
})

const enginesList = computed(() => {
  if (!status.value?.engines) return []
  return Object.values(status.value.engines)
})

const binariesList = computed(() => {
  if (!status.value?.binaries) return []
  return Object.values(status.value.binaries)
})

const extensionsList = computed(() => {
  if (!status.value?.php_extensions) return []
  return Object.values(status.value.php_extensions)
})

const engineBadgeClass = (engineId) => {
  switch (engineId) {
    case 'mysql':
      return 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20'
    case 'pgsql':
      return 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20'
    case 'mongodb':
      return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
    case 'sqlite':
      return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20'
    default:
      return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
  }
}

const getInstallCommand = (engine) => {
  const hints = engine.install_hints || {}
  const os = status.value?.system_info?.os || ''
  if (os.toLowerCase().includes('darwin') || os.toLowerCase().includes('mac')) {
    return hints.macos || hints.ubuntu
  }
  return hints.ubuntu || hints.macos
}

const copyToClipboard = async (text) => {
  if (!text) return
  try {
    await navigator.clipboard.writeText(text)
    copiedCommand.value = text
    setTimeout(() => {
      if (copiedCommand.value === text) {
        copiedCommand.value = null
      }
    }, 2500)
  } catch {
    // Clipboard fallback
  }
}

const formatBytes = (bytes) => {
  if (!bytes || bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}
</script>
