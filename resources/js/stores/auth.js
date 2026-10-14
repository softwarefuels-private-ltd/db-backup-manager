import { defineStore } from 'pinia'
import client from '@/api/client'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: JSON.parse(localStorage.getItem('dbm_user') || 'null'),
    token: localStorage.getItem('dbm_token') || null,
    needsSetup: false,
    loading: false,
    error: null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
  },

  actions: {
    async checkSetup() {
      try {
        const { data } = await client.get('/auth/setup-status')
        this.needsSetup = data.needs_setup
        return data.needs_setup
      } catch (err) {
        console.error('Failed to check setup status', err)
        return false
      }
    },

    async login(credentials) {
      this.loading = true
      this.error = null
      try {
        const { data } = await client.post('/auth/login', credentials)
        this.token = data.token
        this.user = data.user
        localStorage.setItem('dbm_token', data.token)
        localStorage.setItem('dbm_user', JSON.stringify(data.user))
        return true
      } catch (err) {
        this.error = err.response?.data?.message || 'Login failed'
        throw err
      } finally {
        this.loading = false
      }
    },

    async register(userData) {
      this.loading = true
      this.error = null
      try {
        const { data } = await client.post('/auth/register', userData)
        this.token = data.token
        this.user = data.user
        this.needsSetup = false
        localStorage.setItem('dbm_token', data.token)
        localStorage.setItem('dbm_user', JSON.stringify(data.user))
        return true
      } catch (err) {
        this.error = err.response?.data?.message || 'Registration failed'
        throw err
      } finally {
        this.loading = false
      }
    },

    async logout() {
      try {
        await client.post('/auth/logout')
      } catch (err) {
        // Ignore logout errors
      } finally {
        this.token = null
        this.user = null
        localStorage.removeItem('dbm_token')
        localStorage.removeItem('dbm_user')
      }
    },

    async fetchMe() {
      if (!this.token) return null
      try {
        const { data } = await client.get('/auth/me')
        this.user = data.user
        localStorage.setItem('dbm_user', JSON.stringify(data.user))
        return data.user
      } catch (err) {
        this.logout()
        return null
      }
    },
  },
})
