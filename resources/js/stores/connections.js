import { defineStore } from 'pinia'
import client from '@/api/client'

export const useConnectionsStore = defineStore('connections', {
  state: () => ({
    connections: [],
    loading: false,
    testingId: null,
    testResult: null,
    error: null,
  }),

  actions: {
    async fetchConnections() {
      this.loading = true
      this.error = null
      try {
        const { data } = await client.get('/connections')
        this.connections = data
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to fetch database connections'
      } finally {
        this.loading = false
      }
    },

    async createConnection(payload) {
      const { data } = await client.post('/connections', payload)
      await this.fetchConnections()
      return data.connection
    },

    async updateConnection(id, payload) {
      const { data } = await client.put(`/connections/${id}`, payload)
      await this.fetchConnections()
      return data.connection
    },

    async deleteConnection(id) {
      await client.delete(`/connections/${id}`)
      this.connections = this.connections.filter((c) => c.id !== id)
    },

    async testConnection(id) {
      this.testingId = id
      this.testResult = null
      try {
        const { data } = await client.post(`/connections/${id}/test`)
        this.testResult = { id, success: true, message: data.message }
        return this.testResult
      } catch (err) {
        this.testResult = {
          id,
          success: false,
          message: err.response?.data?.message || 'Connection test failed',
        }
        return this.testResult
      } finally {
        this.testingId = null
      }
    },
  },
})
