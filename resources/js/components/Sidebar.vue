<template>
  <aside class="w-64 bg-white border-r border-slate-200 dark:bg-slate-900 dark:border-slate-800 flex flex-col shrink-0 transition-colors duration-150">
    <!-- Brand Header -->
    <div class="h-16 flex items-center justify-between px-6 border-b border-slate-200 dark:border-slate-800">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-teal-500/10 border border-teal-500/20 flex items-center justify-center text-teal-600 dark:text-teal-400">
          <Database class="w-5 h-5" />
        </div>
        <div>
          <h1 class="font-bold text-sm text-slate-900 dark:text-slate-100 tracking-tight leading-none">DB Backup Hub</h1>
          <span class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">v1.0 • Internal</span>
        </div>
      </div>

      <!-- Quick Theme Toggle in Header -->
      <button
        type="button"
        :title="themeStore.isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
        class="p-1.5 rounded-lg text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-slate-100 dark:hover:bg-slate-800 transition-colors"
        @click="themeStore.toggleTheme"
      >
        <Sun v-if="themeStore.isDark" class="w-4 h-4 text-amber-400" />
        <Moon v-else class="w-4 h-4 text-slate-600" />
      </button>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
      <RouterLink
        v-for="item in navItems"
        :key="item.to"
        :to="item.to"
        :class="[
          'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
          $route.path === item.to
            ? 'bg-teal-500/10 text-teal-600 dark:text-teal-400 border border-teal-500/20 font-semibold'
            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800/60',
        ]"
      >
        <component :is="item.icon" class="w-4 h-4 shrink-0" />
        <span>{{ item.label }}</span>
      </RouterLink>
    </nav>

    <!-- User Profile Footer -->
    <div class="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
      <div class="flex items-center gap-2.5 min-w-0">
        <div class="w-8 h-8 rounded-full bg-slate-100 border border-slate-200 text-teal-600 dark:bg-slate-800 dark:border-slate-700 dark:text-teal-400 flex items-center justify-center text-xs font-semibold">
          {{ userInitials }}
        </div>
        <div class="min-w-0">
          <p class="text-xs font-medium text-slate-900 dark:text-slate-200 truncate">{{ authStore.user?.name || 'Administrator' }}</p>
          <p class="text-[10px] text-slate-500 truncate">{{ authStore.user?.email }}</p>
        </div>
      </div>

      <div class="flex items-center gap-1">
        <button
          type="button"
          class="text-slate-400 hover:text-rose-500 transition-colors p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800"
          title="Sign Out"
          @click="handleLogout"
        >
          <LogOut class="w-4 h-4" />
        </button>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from '@/stores/theme'
import {
  LayoutDashboard,
  Database,
  HardDrive,
  CalendarClock,
  History,
  Cpu,
  LogOut,
  Sun,
  Moon,
} from 'lucide-vue-next'

const router = useRouter()
const authStore = useAuthStore()
const themeStore = useThemeStore()

const navItems = [
  { label: 'Overview', to: '/', icon: LayoutDashboard },
  { label: 'Databases', to: '/connections', icon: Database },
  { label: 'Storage Disks', to: '/destinations', icon: HardDrive },
  { label: 'Backup Schedules', to: '/schedules', icon: CalendarClock },
  { label: 'Activity & Logs', to: '/runs', icon: History },
  { label: 'System Drivers', to: '/system', icon: Cpu },
]

const userInitials = computed(() => {
  const name = authStore.user?.name || 'Admin'
  return name.slice(0, 2).toUpperCase()
})

const handleLogout = async () => {
  await authStore.logout()
  router.push({ name: 'login' })
}
</script>
