<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Backup Schedules</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Configure cron automation, database targets, remote disks, and retention rotation rules.</p>
      </div>
      <button
        type="button"
        class="px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white dark:text-slate-950 rounded-lg text-sm font-semibold transition-colors flex items-center gap-2 shadow-sm"
        @click="openCreateModal"
      >
        <Plus class="w-4 h-4" />
        <span>Create Schedule</span>
      </button>
    </div>

    <!-- Schedules Table -->
    <div class="bg-white border border-slate-200 dark:bg-slate-900 dark:border-slate-800 rounded-xl overflow-hidden shadow-xs transition-colors">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 dark:bg-slate-950/60 dark:text-slate-400 dark:border-slate-800">
            <tr>
              <th class="px-6 py-3.5 font-medium">Schedule Name</th>
              <th class="px-6 py-3.5 font-medium">Database Target</th>
              <th class="px-6 py-3.5 font-medium">Storage Destination</th>
              <th class="px-6 py-3.5 font-medium">Cron & Timing</th>
              <th class="px-6 py-3.5 font-medium">Retention Policy</th>
              <th class="px-6 py-3.5 font-medium">Next Run</th>
              <th class="px-6 py-3.5 font-medium text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
            <tr v-if="schedulesStore.schedules.length === 0">
              <td colspan="7" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                No backup schedules created yet. Click "Create Schedule" above.
              </td>
            </tr>
            <tr
              v-for="schedule in schedulesStore.schedules"
              :key="schedule.id"
              class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors"
            >
              <td class="px-6 py-4 font-semibold text-slate-900 dark:text-slate-200">
                <div class="flex items-center gap-2">
                  <span
                    :class="[
                      'w-2 h-2 rounded-full',
                      schedule.enabled ? 'bg-emerald-500' : 'bg-slate-400 dark:bg-slate-600',
                    ]"
                  />
                  <span>{{ schedule.name }}</span>
                </div>
              </td>
              <td class="px-6 py-4 text-slate-700 dark:text-slate-300">
                <span class="px-1.5 py-0.5 rounded text-[10px] bg-slate-100 text-teal-700 dark:bg-slate-800 dark:text-teal-400 font-mono uppercase mr-1.5">
                  {{ schedule.database_connection?.driver || 'db' }}
                </span>
                <span>{{ schedule.database_connection?.name || '—' }}</span>
              </td>
              <td class="px-6 py-4 text-slate-700 dark:text-slate-300">
                <span class="px-1.5 py-0.5 rounded text-[10px] bg-slate-100 text-purple-700 dark:bg-slate-800 dark:text-purple-400 font-mono uppercase mr-1.5">
                  {{ schedule.storage_destination?.driver || 'disk' }}
                </span>
                <span>{{ schedule.storage_destination?.name || '—' }}</span>
              </td>
              <td class="px-6 py-4 font-mono text-slate-700 dark:text-slate-300">
                <span class="px-2 py-0.5 rounded bg-slate-100 border border-slate-200 text-teal-700 dark:bg-slate-950 dark:border-slate-800 dark:text-teal-400">
                  {{ schedule.cron_expression }}
                </span>
                <span class="text-[10px] text-slate-500 dark:text-slate-400 block mt-1 font-sans">
                  Timezone: {{ schedule.timezone || 'UTC' }}
                </span>
              </td>
              <td class="px-6 py-4 text-slate-700 dark:text-slate-300">
                <div class="space-y-0.5">
                  <p v-if="schedule.keep_last_count" class="text-xs">
                    Keep latest <span class="text-teal-600 dark:text-teal-400 font-semibold">{{ schedule.keep_last_count }}</span> backups
                  </p>
                  <p v-if="schedule.retention_days" class="text-xs text-slate-500 dark:text-slate-400">
                    Keep for {{ schedule.retention_days }} days
                  </p>
                  <p v-if="!schedule.keep_last_count && !schedule.retention_days" class="text-slate-400">
                    No rotation set
                  </p>
                </div>
              </td>
              <td class="px-6 py-4 font-mono text-xs text-sky-600 dark:text-sky-400">
                {{ formatDate(schedule.next_run_at) }}
              </td>
              <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button
                    type="button"
                    :disabled="triggeringId === schedule.id"
                    class="px-2.5 py-1 rounded-md bg-teal-500/10 hover:bg-teal-500/20 text-teal-700 dark:text-teal-400 border border-teal-500/30 text-xs font-semibold transition-colors flex items-center gap-1.5 disabled:opacity-50"
                    title="Trigger backup immediately"
                    @click="handleTrigger(schedule.id)"
                  >
                    <span v-if="triggeringId === schedule.id" class="w-3 h-3 border border-teal-500 border-t-transparent rounded-full animate-spin" />
                    <Play v-else class="w-3 h-3 fill-current" />
                    <span>{{ triggeringId === schedule.id ? 'Starting...' : 'Backup Now' }}</span>
                  </button>

                  <button
                    type="button"
                    class="p-1.5 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors"
                    title="Edit Schedule"
                    @click="openEditModal(schedule)"
                  >
                    <Pencil class="w-3.5 h-3.5" />
                  </button>

                  <button
                    type="button"
                    class="p-1.5 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-rose-500 transition-colors"
                    title="Delete Schedule"
                    @click="handleDelete(schedule)"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create / Edit Modal -->
    <Modal v-model="modalOpen" :title="isEditing ? 'Edit Backup Schedule' : 'Create Backup Schedule'">
      <form @submit.prevent="handleSave" class="space-y-4">
        <div>
          <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Schedule Name</label>
          <input
            v-model="form.name"
            type="text"
            required
            placeholder="Nightly Production Backup"
            class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500"
          />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Database Target</label>
            <select
              v-model="form.database_connection_id"
              required
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 focus:outline-none focus:border-teal-500"
            >
              <option :value="null" disabled>Select a database</option>
              <option v-for="c in connectionsStore.connections" :key="c.id" :value="c.id">
                {{ c.name }} ({{ c.driver }})
              </option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Storage Destination</label>
            <select
              v-model="form.storage_destination_id"
              required
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 focus:outline-none focus:border-teal-500"
            >
              <option :value="null" disabled>Select a storage disk</option>
              <option v-for="d in destinationsStore.destinations" :key="d.id" :value="d.id">
                {{ d.name }} ({{ d.driver }})
              </option>
            </select>
          </div>
        </div>

        <!-- Cron Preset selector -->
        <div>
          <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Schedule Frequency Preset</label>
          <div class="grid grid-cols-3 gap-2">
            <button
              v-for="preset in cronPresets"
              :key="preset.cron"
              type="button"
              :class="[
                'p-2 rounded-lg text-xs font-medium border text-left transition-colors',
                form.cron_expression === preset.cron
                  ? 'bg-teal-500/10 border-teal-500 text-teal-600 dark:text-teal-400'
                  : 'bg-slate-50 border-slate-200 text-slate-600 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200',
              ]"
              @click="form.cron_expression = preset.cron"
            >
              <p class="font-semibold">{{ preset.label }}</p>
              <p class="font-mono text-[10px] text-slate-500 dark:text-slate-400">{{ preset.cron }}</p>
            </button>
          </div>
        </div>

        <div class="grid grid-cols-3 gap-3">
          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Custom Cron Expression</label>
            <input
              v-model="form.cron_expression"
              type="text"
              required
              placeholder="0 2 * * *"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 font-mono focus:outline-none focus:border-teal-500"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Timezone</label>
            <input
              v-model="form.timezone"
              type="text"
              placeholder="UTC"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 font-mono focus:outline-none focus:border-teal-500"
            />
          </div>
        </div>

        <!-- Retention Rules -->
        <div class="p-3.5 rounded-lg bg-slate-50 border border-slate-200 dark:bg-slate-950/60 dark:border-slate-800 space-y-3 transition-colors">
          <h4 class="text-xs font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
            <RotateCcw class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400" />
            <span>Retention & Rotation Rules</span>
          </h4>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-[11px] text-slate-600 dark:text-slate-400 mb-1">Keep Last N Backups</label>
              <input
                v-model.number="form.keep_last_count"
                type="number"
                min="1"
                placeholder="7"
                class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 dark:bg-slate-900 dark:border-slate-800 dark:text-slate-100 focus:outline-none focus:border-teal-500"
              />
              <span class="text-[10px] text-slate-500 dark:text-slate-400">Automatically prunes older backups</span>
            </div>

            <div>
              <label class="block text-[11px] text-slate-600 dark:text-slate-400 mb-1">Keep Max Age (Days)</label>
              <input
                v-model.number="form.retention_days"
                type="number"
                min="1"
                placeholder="30"
                class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 dark:bg-slate-900 dark:border-slate-800 dark:text-slate-100 focus:outline-none focus:border-teal-500"
              />
              <span class="text-[10px] text-slate-500 dark:text-slate-400">Purges files older than N days</span>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2 pt-1">
          <input
            id="schedule-enabled"
            v-model="form.enabled"
            type="checkbox"
            class="rounded border-slate-300 dark:border-slate-800 bg-white dark:bg-slate-950 text-teal-500 focus:ring-teal-500"
          />
          <label for="schedule-enabled" class="text-xs font-medium text-slate-700 dark:text-slate-300">
            Active / Enabled for automated background execution
          </label>
        </div>
      </form>

      <template #footer>
        <button
          type="button"
          class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 text-sm font-medium transition-colors"
          @click="modalOpen = false"
        >
          Cancel
        </button>
        <button
          type="button"
          :disabled="saving"
          class="px-4 py-2 rounded-lg bg-teal-500 hover:bg-teal-600 disabled:opacity-50 text-white dark:text-slate-950 text-sm font-semibold transition-colors flex items-center gap-2"
          @click="handleSave"
        >
          <span>{{ saving ? 'Saving...' : 'Save Schedule' }}</span>
        </button>
      </template>
    </Modal>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useSchedulesStore } from '@/stores/schedules'
import { useConnectionsStore } from '@/stores/connections'
import { useDestinationsStore } from '@/stores/destinations'
import Modal from '@/components/Modal.vue'
import {
  Plus,
  Pencil,
  Trash2,
  Play,
  RotateCcw,
} from 'lucide-vue-next'

const schedulesStore = useSchedulesStore()
const connectionsStore = useConnectionsStore()
const destinationsStore = useDestinationsStore()

const modalOpen = ref(false)
const isEditing = ref(false)
const editingId = ref(null)
const saving = ref(false)
const triggeringId = ref(null)

const cronPresets = [
  { label: 'Daily (2 AM)', cron: '0 2 * * *' },
  { label: 'Twice Daily', cron: '0 2,14 * * *' },
  { label: 'Hourly', cron: '0 * * * *' },
]

const form = reactive({
  name: '',
  database_connection_id: null,
  storage_destination_id: null,
  cron_expression: '0 2 * * *',
  timezone: 'UTC',
  enabled: true,
  keep_last_count: 7,
  retention_days: 30,
})

onMounted(() => {
  schedulesStore.fetchSchedules()
  connectionsStore.fetchConnections()
  destinationsStore.fetchDestinations()
})

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

const openCreateModal = () => {
  isEditing.value = false
  editingId.value = null
  Object.assign(form, {
    name: '',
    database_connection_id: connectionsStore.connections[0]?.id || null,
    storage_destination_id: destinationsStore.destinations[0]?.id || null,
    cron_expression: '0 2 * * *',
    timezone: 'UTC',
    enabled: true,
    keep_last_count: 7,
    retention_days: 30,
  })
  modalOpen.value = true
}

const openEditModal = (schedule) => {
  isEditing.value = true
  editingId.value = schedule.id
  Object.assign(form, {
    name: schedule.name,
    database_connection_id: schedule.database_connection_id,
    storage_destination_id: schedule.storage_destination_id,
    cron_expression: schedule.cron_expression,
    timezone: schedule.timezone || 'UTC',
    enabled: !!schedule.enabled,
    keep_last_count: schedule.keep_last_count || 7,
    retention_days: schedule.retention_days || 30,
  })
  modalOpen.value = true
}

const handleSave = async () => {
  saving.value = true
  try {
    if (isEditing.value) {
      await schedulesStore.updateSchedule(editingId.value, form)
    } else {
      await schedulesStore.createSchedule(form)
    }
    modalOpen.value = false
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to save schedule')
  } finally {
    saving.value = false
  }
}

const handleDelete = async (schedule) => {
  if (confirm(`Are you sure you want to delete backup schedule [${schedule.name}]?`)) {
    try {
      await schedulesStore.deleteSchedule(schedule.id)
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to delete schedule')
    }
  }
}

const handleTrigger = async (id) => {
  triggeringId.value = id
  try {
    await schedulesStore.triggerSchedule(id)
    alert('Backup run triggered in the background! Check the Activity & Logs tab.')
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to trigger backup')
  } finally {
    triggeringId.value = null
  }
}
</script>
