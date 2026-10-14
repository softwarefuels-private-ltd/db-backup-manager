import { defineStore } from 'pinia'
import client from '@/api/client'

export const useRunsStore = defineStore('runs', {
  state: () => ({
    runs: [],
    pagination: {
      currentPage: 1,
      lastPage: 1,
      total: 0,
      perPage: 15,
    },
    loading: false,
    selectedRun: null,
    error: null,
  }),

  actions: {
    async fetchRuns(page = 1, filters = {}) {
      this.loading = true
      this.error = null
      try {
        const params = { page, ...filters }
        const { data } = await client.get('/runs', { params })
        this.runs = data.data
        this.pagination = {
          currentPage: data.current_page,
          lastPage: data.last_page,
          total: data.total,
          perPage: data.per_page,
        }
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to fetch backup activity runs'
      } finally {
        this.loading = false
      }
    },

    async fetchRun(id) {
      try {
        const { data } = await client.get(`/runs/${id}`)
        this.selectedRun = data
        return data
      } catch (err) {
        console.error('Failed to fetch run details', err)
        throw err
      }
    },

    async deleteRun(id) {
      await client.delete(`/runs/${id}`)
      this.runs = this.runs.filter((r) => r.id !== id)
      this.pagination.total = Math.max(0, this.pagination.total - 1)
    },

    getDownloadUrl(id) {
      const token = localStorage.getItem('dbm_token')
      return `/api/v1/runs/${id}/download?token=${token}`
    },
  },
})
