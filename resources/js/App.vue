<template>
  <div class="h-screen w-screen flex bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 overflow-hidden font-sans">
    <template v-if="authStore.isAuthenticated && !isAuthPage">
      <Sidebar />
      <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 p-8 transition-colors duration-150">
        <RouterView />
      </main>
    </template>
    <template v-else>
      <div class="flex-1 h-full overflow-y-auto">
        <RouterView />
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from '@/stores/theme'
import Sidebar from '@/components/Sidebar.vue'

const route = useRoute()
const authStore = useAuthStore()
const themeStore = useThemeStore()

const isAuthPage = computed(() => {
  return route.name === 'login' || route.name === 'register'
})

onMounted(() => {
  themeStore.initTheme()
  if (authStore.isAuthenticated) {
    authStore.fetchMe()
  }
})
</script>
