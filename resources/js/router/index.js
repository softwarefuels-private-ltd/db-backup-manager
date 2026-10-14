import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

import DashboardView from '@/views/DashboardView.vue'
import ConnectionsView from '@/views/ConnectionsView.vue'
import DestinationsView from '@/views/DestinationsView.vue'
import SchedulesView from '@/views/SchedulesView.vue'
import RunsView from '@/views/RunsView.vue'
import SystemStatusView from '@/views/SystemStatusView.vue'
import LoginView from '@/views/LoginView.vue'
import RegisterView from '@/views/RegisterView.vue'

const routes = [
  {
    path: '/',
    name: 'dashboard',
    component: DashboardView,
    meta: { requiresAuth: true },
  },
  {
    path: '/system',
    name: 'system-status',
    component: SystemStatusView,
    meta: { requiresAuth: true },
  },
  {
    path: '/connections',
    name: 'connections',
    component: ConnectionsView,
    meta: { requiresAuth: true },
  },
  {
    path: '/destinations',
    name: 'destinations',
    component: DestinationsView,
    meta: { requiresAuth: true },
  },
  {
    path: '/schedules',
    name: 'schedules',
    component: SchedulesView,
    meta: { requiresAuth: true },
  },
  {
    path: '/runs',
    name: 'runs',
    component: RunsView,
    meta: { requiresAuth: true },
  },
  {
    path: '/login',
    name: 'login',
    component: LoginView,
  },
  {
    path: '/register',
    name: 'register',
    component: RegisterView,
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/',
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    const needsSetup = await authStore.checkSetup()
    if (needsSetup) {
      return next({ name: 'register' })
    }
    return next({ name: 'login' })
  }

  if ((to.name === 'login' || to.name === 'register') && authStore.isAuthenticated) {
    return next({ name: 'dashboard' })
  }

  next()
})

export default router
