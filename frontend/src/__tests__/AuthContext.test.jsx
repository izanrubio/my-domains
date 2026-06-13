import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { AuthProvider, useAuth } from '../contexts/AuthContext'

vi.mock('../api/client', () => ({
  default: {
    post: vi.fn(),
  },
}))

import client from '../api/client'

function AuthTester() {
  const { isAuthenticated, login, logout } = useAuth()
  return (
    <div>
      <div data-testid="status">{isAuthenticated ? 'authenticated' : 'unauthenticated'}</div>
      <button onClick={() => login('a@b.com', 'pass')}>login</button>
      <button onClick={() => logout()}>logout</button>
    </div>
  )
}

describe('AuthContext', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.clearAllMocks()
  })

  it('starts unauthenticated when localStorage is empty', () => {
    render(<AuthProvider><AuthTester /></AuthProvider>)
    expect(screen.getByTestId('status')).toHaveTextContent('unauthenticated')
  })

  it('hydrates from localStorage token', () => {
    localStorage.setItem('auth_token', 'saved-token')
    render(<AuthProvider><AuthTester /></AuthProvider>)
    expect(screen.getByTestId('status')).toHaveTextContent('authenticated')
  })

  it('login stores token and marks authenticated', async () => {
    const user = userEvent.setup()
    client.post.mockResolvedValueOnce({ data: { token: 'fresh-token' } })

    render(<AuthProvider><AuthTester /></AuthProvider>)
    await user.click(screen.getByRole('button', { name: 'login' }))

    await waitFor(() => expect(screen.getByTestId('status')).toHaveTextContent('authenticated'))
    expect(localStorage.getItem('auth_token')).toBe('fresh-token')
  })

  it('logout clears token', async () => {
    const user = userEvent.setup()
    localStorage.setItem('auth_token', 'existing-token')
    client.post.mockResolvedValueOnce({})

    render(<AuthProvider><AuthTester /></AuthProvider>)
    expect(screen.getByTestId('status')).toHaveTextContent('authenticated')

    await user.click(screen.getByRole('button', { name: 'logout' }))

    await waitFor(() => expect(screen.getByTestId('status')).toHaveTextContent('unauthenticated'))
    expect(localStorage.getItem('auth_token')).toBeNull()
  })

  it('logout does not throw when API call fails', async () => {
    const user = userEvent.setup()
    localStorage.setItem('auth_token', 'existing-token')
    client.post.mockRejectedValueOnce(new Error('network'))

    render(<AuthProvider><AuthTester /></AuthProvider>)
    await user.click(screen.getByRole('button', { name: 'logout' }))

    await waitFor(() => expect(screen.getByTestId('status')).toHaveTextContent('unauthenticated'))
  })
})
