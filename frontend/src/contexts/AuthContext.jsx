import { createContext, useCallback, useContext, useState } from 'react'
import client from '../api/client'

export const AuthContext = createContext(null)

const TOKEN_KEY = 'auth_token'

export function AuthProvider({ children }) {
  const [token, setToken] = useState(() => localStorage.getItem(TOKEN_KEY))

  const login = useCallback(async (email, password) => {
    const { data } = await client.post('/login', { email, password })
    localStorage.setItem(TOKEN_KEY, data.token)
    setToken(data.token)
    return data
  }, [])

  const logout = useCallback(async () => {
    try {
      await client.post('/logout')
    } catch (_) {
      // ignore — token may already be invalid
    }
    localStorage.removeItem(TOKEN_KEY)
    setToken(null)
  }, [])

  return (
    <AuthContext.Provider value={{ token, isAuthenticated: !!token, login, logout }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within AuthProvider')
  return ctx
}
