import { defineStore } from 'pinia'
import client from '@/api/client'

export const useSchedulesStore = defineStore('schedules', {
  state: () => ({
    schedules: [],
    loading: false,
    triggeringId: null,
    error: null,
  }),

  actions: {
    async fetchSchedules() {
      this.loading = true
      this.error = null
      try {
        const { data } = await client.get('/schedules')
        this.schedules = data
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to fetch backup schedules'
      } finally {
        this.loading = false
      }
    },

    async createSchedule(payload) {
      const { data } = await client.post('/schedules', payload)
      await this.fetchSchedules()
      return data.schedule
    },

    async updateSchedule(id, payload) {
      const { data } = await client.put(`/schedules/${id}`, payload)
      await this.fetchSchedules()
      return data.schedule
    },

    async deleteSchedule(id) {
      await client.delete(`/schedules/${id}`)
      this.schedules = this.schedules.filter((s) => s.id !== id)
    },

    async triggerSchedule(id) {
      this.triggeringId = id
      try {
        const { data } = await client.post(`/schedules/${id}/trigger`)
        await this.fetchSchedules()
        return data.run
      } finally {
        this.triggeringId = null
      }
    },
  },
})
