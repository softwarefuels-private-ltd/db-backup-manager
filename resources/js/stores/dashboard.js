import { defineStore } from 'pinia'
import client from '@/api/client'

export const useDashboardStore = defineStore('dashboard', {
  state: () => ({
    stats: {
      total_connections: 0,
      total_destinations: 0,
      total_schedules: 0,
      active_schedules: 0,
      total_runs: 0,
      completed_runs: 0,
      failed_runs: 0,
      running_runs: 0,
      total_storage_bytes: 0,
    },
    databaseConnections: [],
    recentRuns: [],
    upcomingSchedules: [],
    loading: false,
    checkingHealth: false,
    testingConnectionId: null,
    error: null,
  }),

  actions: {
    async fetchStats() {
      this.loading = true
      this.error = null
      try {
        const { data } = await client.get('/dashboard/stats')
        this.stats = data.stats
        this.databaseConnections = data.database_connections || []
        this.recentRuns = data.recent_runs
        this.upcomingSchedules = data.upcoming_schedules
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to load dashboard statistics'
      } finally {
        this.loading = false
      }
    },

    async checkAllHealth() {
      this.checkingHealth = true
      try {
        const { data } = await client.post('/connections/health-check')
        this.databaseConnections = data
        return { success: true }
      } catch (err) {
        return {
          success: false,
          message: err.response?.data?.message || 'Failed to refresh database health',
        }
      } finally {
        this.checkingHealth = false
      }
    },

    async testConnection(id) {
      this.testingConnectionId = id
      try {
        const { data } = await client.post(`/connections/${id}/test`)
        const idx = this.databaseConnections.findIndex((c) => c.id === id)
        if (idx !== -1 && data.health) {
          this.databaseConnections[idx].health = data.health
        }
        return { success: true, message: data.message }
      } catch (err) {
        const idx = this.databaseConnections.findIndex((c) => c.id === id)
        const health = err.response?.data?.health || {
          status: 'unhealthy',
          healthy: false,
          latency_ms: null,
          message: err.response?.data?.message || 'Connection test failed',
          checked_at: new Date().toISOString(),
        }
        if (idx !== -1) {
          this.databaseConnections[idx].health = health
        }
        return {
          success: false,
          message: err.response?.data?.message || 'Connection test failed',
        }
      } finally {
        this.testingConnectionId = null
      }
    },
  },
})
