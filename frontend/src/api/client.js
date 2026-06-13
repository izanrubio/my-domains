import axios from 'axios'

const API_BASE = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api'

const client = axios.create({
  baseURL: API_BASE,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

client.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

client.interceptors.response.use(
  (res) => res,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token')
      window.location.replace('/login')
    }
    return Promise.reject(error)
  }
)

export default client
