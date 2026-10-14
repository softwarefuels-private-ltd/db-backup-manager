<template>
  <div class="min-h-full flex items-center justify-center p-6 bg-slate-50 dark:bg-slate-950 transition-colors duration-150 relative">
    <!-- Top-right theme toggle -->
    <div class="absolute top-6 right-6">
      <button
        type="button"
        :title="themeStore.isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
        class="p-2 rounded-lg text-slate-500 hover:text-slate-900 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 dark:text-slate-400 dark:hover:text-slate-100 shadow-xs transition-colors"
        @click="themeStore.toggleTheme"
      >
        <Sun v-if="themeStore.isDark" class="w-4 h-4 text-amber-400" />
        <Moon v-else class="w-4 h-4 text-slate-600" />
      </button>
    </div>

    <div class="w-full max-w-md bg-white border border-slate-200 dark:bg-slate-900 dark:border-slate-800 rounded-2xl p-8 shadow-xl dark:shadow-2xl space-y-6 transition-colors">
      <div class="text-center space-y-2">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-teal-500/10 border border-teal-500/20 text-teal-600 dark:text-teal-400 mb-2">
          <Database class="w-6 h-6" />
        </div>
        <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Setup Admin Account</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">Initialize your internal database backup dashboard</p>
      </div>

      <div v-if="error" class="p-4 rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-700 dark:text-rose-400 text-sm">
        {{ error }}
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">Full Name</label>
          <input
            v-model="form.name"
            type="text"
            required
            placeholder="John Doe"
            class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500 transition-colors"
          />
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">Email Address</label>
          <input
            v-model="form.email"
            type="email"
            required
            placeholder="admin@internal.local"
            class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500 transition-colors"
          />
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">Password</label>
          <input
            v-model="form.password"
            type="password"
            required
            minlength="8"
            placeholder="Minimum 8 characters"
            class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500 transition-colors"
          />
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">Confirm Password</label>
          <input
            v-model="form.password_confirmation"
            type="password"
            required
            minlength="8"
            placeholder="Re-enter password"
            class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-100 dark:placeholder-slate-500 focus:outline-none focus:border-teal-500 transition-colors"
          />
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="w-full py-2.5 px-4 rounded-lg bg-teal-500 hover:bg-teal-600 disabled:opacity-50 text-white dark:text-slate-950 font-semibold text-sm transition-colors flex items-center justify-center gap-2 mt-2"
        >
          <span v-if="loading" class="w-4 h-4 border-2 border-white dark:border-slate-950 border-t-transparent rounded-full animate-spin" />
          <span>{{ loading ? 'Creating Administrator...' : 'Create Account' }}</span>
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from '@/stores/theme'
import { Database, Sun, Moon } from 'lucide-vue-next'

const router = useRouter()
const authStore = useAuthStore()
const themeStore = useThemeStore()

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const loading = ref(false)
const error = ref('')

const handleSubmit = async () => {
  loading.value = true
  error.value = ''
  try {
    await authStore.register(form)
    router.push({ name: 'dashboard' })
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to create account'
  } finally {
    loading.value = false
  }
}
</script>
