<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Databases</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage database credentials for MySQL, MariaDB, PostgreSQL, SQLite, and MongoDB.</p>
      </div>
      <button
        type="button"
        class="px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white dark:text-slate-950 rounded-lg text-sm font-semibold transition-colors flex items-center gap-2 shadow-sm"
        @click="openCreateModal"
      >
        <Plus class="w-4 h-4" />
        <span>Add Database</span>
      </button>
    </div>

    <!-- Alert / Test Result Banner -->
    <div
      v-if="testResult"
      :class="[
        'p-4 rounded-xl text-sm flex items-start justify-between border transition-all',
        testResult.success
          ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-700 dark:text-emerald-300'
          : 'bg-rose-500/10 border-rose-500/20 text-rose-700 dark:text-rose-300',
      ]"
    >
      <div class="flex items-center gap-2">
        <CheckCircle2 v-if="testResult.success" class="w-5 h-5 shrink-0 text-emerald-500 dark:text-emerald-400" />
        <AlertCircle v-else class="w-5 h-5 shrink-0 text-rose-500 dark:text-rose-400" />
        <span>{{ testResult.message }}</span>
      </div>
      <button type="button" class="text-xs opacity-70 hover:opacity-100 p-1" @click="testResult = null">✕</button>
    </div>

    <!-- Connections Table -->
    <div class="bg-white border border-slate-200 dark:bg-slate-900 dark:border-slate-800 rounded-xl overflow-hidden shadow-xs transition-colors">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 dark:bg-slate-950/60 dark:text-slate-400 dark:border-slate-800">
            <tr>
              <th class="px-6 py-3.5 font-medium">Name & Driver</th>
              <th class="px-6 py-3.5 font-medium">Host / Path</th>
              <th class="px-6 py-3.5 font-medium">Database</th>
              <th class="px-6 py-3.5 font-medium">Schedules</th>
              <th class="px-6 py-3.5 font-medium text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
            <tr v-if="connectionsStore.connections.length === 0">
              <td colspan="5" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                No database connections configured yet. Click "Add Database" above.
              </td>
            </tr>
            <tr
              v-for="conn in connectionsStore.connections"
              :key="conn.id"
              class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors"
            >
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <span
                    :class="[
                      'px-2 py-1 rounded text-[10px] font-mono font-bold uppercase tracking-wider',
                      driverBadgeClass(conn.driver),
                    ]"
                  >
                    {{ conn.driver }}
                  </span>
                  <div>
                    <p class="font-semibold text-slate-900 dark:text-slate-100 text-sm">{{ conn.name }}</p>
                    <p class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">User: {{ conn.username || '—' }}</p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 font-mono text-slate-700 dark:text-slate-300">
                <span v-if="conn.driver === 'sqlite'" class="truncate max-w-xs block" :title="conn.database_path">
                  {{ conn.database_path }}
                </span>
                <span v-else>
                  {{ conn.host }}:{{ conn.port }}
                </span>
              </td>
              <td class="px-6 py-4 font-mono text-slate-700 dark:text-slate-300">
                {{ conn.database || '—' }}
              </td>
              <td class="px-6 py-4">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] bg-slate-100 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700">
                  {{ conn.backup_schedules_count || 0 }} schedules
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button
                    type="button"
                    :disabled="testingId === conn.id"
                    class="px-2.5 py-1 rounded-md bg-slate-100 hover:bg-slate-200 text-teal-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-teal-400 text-xs font-medium transition-colors flex items-center gap-1.5 disabled:opacity-50"
                    title="Test database connection"
                    @click="handleTest(conn.id)"
                  >
                    <span v-if="testingId === conn.id" class="w-3 h-3 border border-teal-500 border-t-transparent rounded-full animate-spin" />
                    <Activity v-else class="w-3 h-3" />
                    <span>{{ testingId === conn.id ? 'Testing...' : 'Test' }}</span>
                  </button>

                  <button
                    type="button"
                    class="p-1.5 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors"
                    title="Edit Connection"
                    @click="openEditModal(conn)"
                  >
                    <Pencil class="w-3.5 h-3.5" />
                  </button>

                  <button
                    type="button"
                    class="p-1.5 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-rose-500 transition-colors"
                    title="Delete Connection"
                    @click="handleDelete(conn)"
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
    <Modal v-model="modalOpen" :title="isEditing ? 'Edit Database Connection' : 'Add Database Connection'">
      <form @submit.prevent="handleSave" class="space-y-4">
        <div>
          <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Friendly Name</label>
          <input
            v-model="form.name"
            type="text"
            required
            placeholder="Production Cluster Primary"
            class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500"
          />
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Database Engine</label>
          <select
            v-model="form.driver"
            class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 focus:outline-none focus:border-teal-500"
          >
            <option value="mysql">MySQL</option>
            <option value="mariadb">MariaDB</option>
            <option value="pgsql">PostgreSQL</option>
            <option value="sqlite">SQLite</option>
            <option value="mongodb">MongoDB</option>
          </select>
        </div>

        <!-- Non-SQLite fields -->
        <template v-if="form.driver !== 'sqlite'">
          <div class="grid grid-cols-3 gap-3">
            <div class="col-span-2">
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Host</label>
              <input
                v-model="form.host"
                type="text"
                required
                placeholder="127.0.0.1 or db.internal.net"
                class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Port</label>
              <input
                v-model.number="form.port"
                type="number"
                placeholder="3306"
                class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Database Name</label>
            <input
              v-model="form.database"
              type="text"
              required
              placeholder="ecommerce_production"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Username</label>
              <input
                v-model="form.username"
                type="text"
                placeholder="db_admin"
                class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Password</label>
              <input
                v-model="form.password"
                type="password"
                :placeholder="isEditing ? 'Leave blank to retain current' : '••••••••'"
                class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500"
              />
            </div>
          </div>
        </template>

        <!-- SQLite fields -->
        <template v-else>
          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">SQLite Database Absolute Path</label>
            <input
              v-model="form.database_path"
              type="text"
              required
              placeholder="/var/www/app/database/database.sqlite"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500 font-mono"
            />
          </div>
        </template>
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
          <span>{{ saving ? 'Saving...' : 'Save Connection' }}</span>
        </button>
      </template>
    </Modal>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useConnectionsStore } from '@/stores/connections'
import Modal from '@/components/Modal.vue'
import {
  Plus,
  Pencil,
  Trash2,
  Activity,
  CheckCircle2,
  AlertCircle,
} from 'lucide-vue-next'

const connectionsStore = useConnectionsStore()
const modalOpen = ref(false)
const isEditing = ref(false)
const editingId = ref(null)
const saving = ref(false)
const testingId = ref(null)
const testResult = ref(null)

const form = reactive({
  name: '',
  driver: 'mysql',
  host: '127.0.0.1',
  port: 3306,
  database: '',
  username: '',
  password: '',
  database_path: '',
})

onMounted(() => {
  connectionsStore.fetchConnections()
})

const defaultPort = (driver) => {
  switch (driver) {
    case 'mysql':
    case 'mariadb':
      return 3306
    case 'pgsql':
      return 5432
    case 'mongodb':
      return 27017
    default:
      return null
  }
}

const driverBadgeClass = (driver) => {
  switch (driver?.toLowerCase()) {
    case 'mysql':
    case 'mariadb':
      return 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20'
    case 'pgsql':
      return 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20'
    case 'mongodb':
      return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
    case 'sqlite':
      return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20'
    default:
      return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border border-slate-200 dark:border-slate-700'
  }
}

const openCreateModal = () => {
  isEditing.value = false
  editingId.value = null
  Object.assign(form, {
    name: '',
    driver: 'mysql',
    host: '127.0.0.1',
    port: 3306,
    database: '',
    username: 'root',
    password: '',
    database_path: '',
  })
  modalOpen.value = true
}

const openEditModal = (conn) => {
  isEditing.value = true
  editingId.value = conn.id
  Object.assign(form, {
    name: conn.name,
    driver: conn.driver,
    host: conn.host || '',
    port: conn.port || defaultPort(conn.driver),
    database: conn.database || '',
    username: conn.username || '',
    password: '',
    database_path: conn.database_path || '',
  })
  modalOpen.value = true
}

const handleSave = async () => {
  saving.value = true
  try {
    if (isEditing.value) {
      await connectionsStore.updateConnection(editingId.value, form)
    } else {
      await connectionsStore.createConnection(form)
    }
    modalOpen.value = false
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to save connection')
  } finally {
    saving.value = false
  }
}

const handleDelete = async (conn) => {
  if (confirm(`Are you sure you want to delete database connection [${conn.name}]?`)) {
    try {
      await connectionsStore.deleteConnection(conn.id)
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to delete connection')
    }
  }
}

const handleTest = async (id) => {
  testingId.value = id
  testResult.value = null
  const res = await connectionsStore.testConnection(id)
  testResult.value = res
  testingId.value = null
}
</script>
