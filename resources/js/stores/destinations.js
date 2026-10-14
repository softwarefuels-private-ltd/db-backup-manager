import { defineStore } from 'pinia'
import client from '@/api/client'

export const useDestinationsStore = defineStore('destinations', {
  state: () => ({
    destinations: [],
    loading: false,
    testingId: null,
    testResult: null,
    error: null,
  }),

  actions: {
    async fetchDestinations() {
      this.loading = true
      this.error = null
      try {
        const { data } = await client.get('/destinations')
        this.destinations = data
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to fetch storage destinations'
      } finally {
        this.loading = false
      }
    },

    async createDestination(payload) {
      const { data } = await client.post('/destinations', payload)
      await this.fetchDestinations()
      return data.destination
    },

    async updateDestination(id, payload) {
      const { data } = await client.put(`/destinations/${id}`, payload)
      await this.fetchDestinations()
      return data.destination
    },

    async deleteDestination(id) {
      await client.delete(`/destinations/${id}`)
      this.destinations = this.destinations.filter((d) => d.id !== id)
    },

    async testDestination(id) {
      this.testingId = id
      this.testResult = null
      try {
        const { data } = await client.post(`/destinations/${id}/test`)
        this.testResult = { id, success: true, message: data.message }
        return this.testResult
      } catch (err) {
        this.testResult = {
          id,
          success: false,
          message: err.response?.data?.message || 'Storage verification failed',
        }
        return this.testResult
      } finally {
        this.testingId = null
      }
    },
  },
})
