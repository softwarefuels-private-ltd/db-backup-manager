<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Storage Disks</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Configure Azure Blob Storage, S3-compatible buckets (AWS, MinIO, Wasabi), remote SFTP servers, or local folders.</p>
      </div>
      <button
        type="button"
        class="px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white dark:text-slate-950 rounded-lg text-sm font-semibold transition-colors flex items-center gap-2 shadow-sm"
        @click="openCreateModal"
      >
        <Plus class="w-4 h-4" />
        <span>Add Storage Disk</span>
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

    <!-- Destinations Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div v-if="destinationsStore.destinations.length === 0" class="col-span-full py-12 text-center text-slate-500 dark:text-slate-400 bg-white border border-slate-200 dark:bg-slate-900 dark:border-slate-800 rounded-xl text-xs">
        No storage disks configured. Click "Add Storage Disk" to configure Azure Blob, S3, SFTP, or Local storage.
      </div>

      <div
        v-for="dest in destinationsStore.destinations"
        :key="dest.id"
        class="bg-white border border-slate-200 dark:bg-slate-900 dark:border-slate-800 rounded-xl p-5 flex flex-col justify-between space-y-4 hover:border-slate-300 dark:hover:border-slate-700 transition-colors shadow-xs"
      >
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <span
              :class="[
                'px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase tracking-wider',
                dest.driver === 'azure'
                  ? 'bg-blue-600/10 text-blue-700 dark:text-blue-400 border border-blue-600/20'
                  : dest.driver === 's3'
                  ? 'bg-purple-500/10 text-purple-700 dark:text-purple-400 border border-purple-500/20'
                  : dest.driver === 'sftp'
                  ? 'bg-sky-500/10 text-sky-700 dark:text-sky-400 border border-sky-500/20'
                  : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20',
              ]"
            >
              {{ dest.driver === 'azure' ? 'azure blob' : dest.driver }}
            </span>
            <span class="text-xs text-slate-500 dark:text-slate-400">
              {{ dest.backup_schedules_count || 0 }} schedules attached
            </span>
          </div>

          <div>
            <h3 class="font-semibold text-slate-900 dark:text-slate-100 text-base">{{ dest.name }}</h3>
            <p v-if="dest.driver === 'azure'" class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5 truncate">
              Container: {{ dest.container || dest.bucket }} • Account: {{ dest.account_name || dest.username || 'Connection String' }}
            </p>
            <p v-else-if="dest.driver === 's3'" class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5 truncate">
              Bucket: {{ dest.bucket }} • Region: {{ dest.region || 'us-east-1' }}
            </p>
            <p v-else-if="dest.driver === 'sftp'" class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5 truncate">
              Host: {{ dest.host }}:{{ dest.port || 22 }} • User: {{ dest.username }}
            </p>
            <p v-else class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5 truncate">
              Path: {{ dest.root || 'storage/app/backups' }}
            </p>
          </div>
        </div>

        <div class="pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
          <button
            type="button"
            :disabled="testingId === dest.id"
            class="px-2.5 py-1 rounded-md bg-slate-100 hover:bg-slate-200 text-teal-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-teal-400 text-xs font-medium transition-colors flex items-center gap-1.5 disabled:opacity-50"
            @click="handleTest(dest.id)"
          >
            <span v-if="testingId === dest.id" class="w-3 h-3 border border-teal-500 border-t-transparent rounded-full animate-spin" />
            <Activity v-else class="w-3 h-3" />
            <span>{{ testingId === dest.id ? 'Verifying...' : 'Test Permissions' }}</span>
          </button>

          <div class="flex items-center gap-1">
            <button
              type="button"
              class="p-1.5 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors"
              title="Edit Disk"
              @click="openEditModal(dest)"
            >
              <Pencil class="w-3.5 h-3.5" />
            </button>
            <button
              type="button"
              class="p-1.5 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-rose-500 transition-colors"
              title="Delete Disk"
              @click="handleDelete(dest)"
            >
              <Trash2 class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create / Edit Modal -->
    <Modal v-model="modalOpen" :title="isEditing ? 'Edit Storage Destination' : 'Add Storage Destination'">
      <form @submit.prevent="handleSave" class="space-y-4">
        <div>
          <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Destination Name</label>
          <input
            v-model="form.name"
            type="text"
            required
            placeholder="Offsite Azure Blob Backups"
            class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500"
          />
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Storage Provider</label>
          <select
            v-model="form.driver"
            class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 focus:outline-none focus:border-teal-500"
          >
            <option value="azure">Microsoft Azure Blob Storage</option>
            <option value="s3">Amazon S3 / MinIO / DigitalOcean Spaces / Wasabi</option>
            <option value="sftp">Remote SFTP Server (SSH)</option>
            <option value="local">Local Filesystem Folder</option>
          </select>
        </div>

        <!-- Azure Blob Fields -->
        <template v-if="form.driver === 'azure'">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                Container Name <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="form.bucket"
                type="text"
                required
                placeholder="ps-container"
                class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500 font-mono"
              />
              <span class="text-[10px] text-slate-400">AZURE_STORAGE_CONTAINER</span>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                Account Name
              </label>
              <input
                v-model="form.username"
                type="text"
                placeholder="filesystemhelp"
                class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500 font-mono"
              />
              <span class="text-[10px] text-slate-400">AZURE_STORAGE_ACCOUNT_NAME</span>
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
              Account Key
            </label>
            <input
              v-model="form.password"
              type="password"
              :placeholder="isEditing ? 'Leave blank to retain current' : '••••••••••••••••••••••••'"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500 font-mono"
            />
            <span class="text-[10px] text-slate-400">AZURE_STORAGE_ACCOUNT_KEY</span>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
              Connection String (Alternative to Account Name & Key)
            </label>
            <textarea
              v-model="form.connection_string"
              rows="2"
              :placeholder="isEditing ? 'Leave blank to retain current' : 'DefaultEndpointsProtocol=https;AccountName=filesystemhelp;AccountKey=...;EndpointSuffix=core.windows.net'"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500 font-mono"
            ></textarea>
            <span class="text-[10px] text-slate-400">AZURE_STORAGE_CONNECTION_STRING</span>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
              Custom Blob Endpoint URL (Optional)
            </label>
            <input
              v-model="form.endpoint"
              type="text"
              placeholder="https://filesystemhelp.blob.core.windows.net"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500 font-mono"
            />
          </div>
        </template>

        <!-- S3 Fields -->
        <template v-else-if="form.driver === 's3'">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Bucket Name</label>
              <input
                v-model="form.bucket"
                type="text"
                required
                placeholder="my-company-backups"
                class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Region</label>
              <input
                v-model="form.region"
                type="text"
                placeholder="us-east-1"
                class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Endpoint URL (Optional for MinIO, DigitalOcean, Wasabi)</label>
            <input
              v-model="form.endpoint"
              type="text"
              placeholder="https://nyc3.digitaloceanspaces.com"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500 font-mono"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Access Key ID</label>
              <input
                v-model="form.access_key_id"
                type="text"
                required
                placeholder="AKIAIOSFODNN7EXAMPLE"
                class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500 font-mono"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Secret Access Key</label>
              <input
                v-model="form.secret_access_key"
                type="password"
                :placeholder="isEditing ? 'Leave blank to retain current' : '••••••••••••'"
                class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500 font-mono"
              />
            </div>
          </div>
        </template>

        <!-- SFTP Fields -->
        <template v-else-if="form.driver === 'sftp'">
          <div class="grid grid-cols-3 gap-3">
            <div class="col-span-2">
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Host / IP</label>
              <input
                v-model="form.host"
                type="text"
                required
                placeholder="backup.storage.internal"
                class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Port</label>
              <input
                v-model.number="form.port"
                type="number"
                placeholder="22"
                class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Username</label>
              <input
                v-model="form.username"
                type="text"
                required
                placeholder="backupuser"
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

          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Private Key (Optional)</label>
            <textarea
              v-model="form.private_key"
              rows="3"
              placeholder="-----BEGIN OPENSSH PRIVATE KEY-----..."
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500 font-mono"
            />
          </div>
        </template>

        <!-- Common / Root path -->
        <div>
          <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Storage Root / Directory Prefix (e.g. AZURE_STORAGE_PREFIX)</label>
          <input
            v-model="form.root"
            type="text"
            placeholder="backups/production"
            class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500 font-mono"
          />
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
          <span>{{ saving ? 'Saving...' : 'Save Storage Disk' }}</span>
        </button>
      </template>
    </Modal>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useDestinationsStore } from '@/stores/destinations'
import Modal from '@/components/Modal.vue'
import {
  Plus,
  Pencil,
  Trash2,
  Activity,
  CheckCircle2,
  AlertCircle,
} from 'lucide-vue-next'

const destinationsStore = useDestinationsStore()
const modalOpen = ref(false)
const isEditing = ref(false)
const editingId = ref(null)
const saving = ref(false)
const testingId = ref(null)
const testResult = ref(null)

const form = reactive({
  name: '',
  driver: 'azure',
  bucket: '',
  region: 'us-east-1',
  endpoint: '',
  access_key_id: '',
  secret_access_key: '',
  connection_string: '',
  host: '',
  port: 22,
  username: '',
  password: '',
  private_key: '',
  root: '',
})

onMounted(() => {
  destinationsStore.fetchDestinations()
})

const openCreateModal = () => {
  isEditing.value = false
  editingId.value = null
  Object.assign(form, {
    name: '',
    driver: 'azure',
    bucket: '',
    region: 'us-east-1',
    endpoint: '',
    access_key_id: '',
    secret_access_key: '',
    connection_string: '',
    host: '',
    port: 22,
    username: '',
    password: '',
    private_key: '',
    root: '',
  })
  modalOpen.value = true
}

const openEditModal = (dest) => {
  isEditing.value = true
  editingId.value = dest.id
  Object.assign(form, {
    name: dest.name,
    driver: dest.driver,
    bucket: dest.bucket || dest.container || '',
    region: dest.region || '',
    endpoint: dest.endpoint || '',
    access_key_id: dest.access_key_id || '',
    secret_access_key: '',
    connection_string: '',
    host: dest.host || '',
    port: dest.port || 22,
    username: dest.username || dest.account_name || '',
    password: '',
    private_key: '',
    root: dest.root || dest.prefix || '',
  })
  modalOpen.value = true
}

const handleSave = async () => {
  saving.value = true
  try {
    if (isEditing.value) {
      await destinationsStore.updateDestination(editingId.value, form)
    } else {
      await destinationsStore.createDestination(form)
    }
    modalOpen.value = false
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to save storage destination')
  } finally {
    saving.value = false
  }
}

const handleDelete = async (dest) => {
  if (confirm(`Are you sure you want to delete storage disk [${dest.name}]?`)) {
    try {
      await destinationsStore.deleteDestination(dest.id)
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to delete storage destination')
    }
  }
}

const handleTest = async (id) => {
  testingId.value = id
  testResult.value = null
  const res = await destinationsStore.testDestination(id)
  testResult.value = res
  testingId.value = null
}
</script>
