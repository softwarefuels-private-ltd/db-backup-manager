import axios from 'axios'

const client = axios.create({
  baseURL: '/api/v1',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  withCredentials: true,
})

// Read CSRF token from the blade meta tag
const csrfMeta = document.head.querySelector('meta[name="csrf-token"]')
if (csrfMeta) {
  client.defaults.headers.common['X-CSRF-TOKEN'] = csrfMeta.content
}

// Attach Bearer token as fallback if available
client.interceptors.request.use((config) => {
  const token = localStorage.getItem('dbm_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Intercept 401 Unauthenticated responses
client.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      localStorage.removeItem('dbm_token')
      localStorage.removeItem('dbm_user')
      if (window.location.pathname !== '/login' && window.location.pathname !== '/register') {
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  }
)

export default client
