import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { MemoryRouter } from 'react-router-dom'
import Login from '../pages/Login'

const mockNavigate = vi.fn()
vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal()
  return { ...actual, useNavigate: () => mockNavigate }
})

vi.mock('../contexts/AuthContext', () => ({
  useAuth: vi.fn(),
}))

import { useAuth } from '../contexts/AuthContext'

describe('Login page', () => {
  const mockLogin = vi.fn()

  beforeEach(() => {
    vi.clearAllMocks()
    useAuth.mockReturnValue({ login: mockLogin, isAuthenticated: false })
  })

  const renderLogin = () => render(<MemoryRouter><Login /></MemoryRouter>)

  it('renders email and password inputs and submit button', () => {
    renderLogin()
    expect(screen.getByLabelText(/email/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/password/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /sign in/i })).toBeInTheDocument()
  })

  it('calls login with credentials and navigates on success', async () => {
    const user = userEvent.setup()
    mockLogin.mockResolvedValueOnce({ token: 'tok' })
    renderLogin()

    await user.type(screen.getByLabelText(/email/i), 'test@example.com')
    await user.type(screen.getByLabelText(/password/i), 'secret')
    await user.click(screen.getByRole('button', { name: /sign in/i }))

    await waitFor(() => {
      expect(mockLogin).toHaveBeenCalledWith('test@example.com', 'secret')
      expect(mockNavigate).toHaveBeenCalledWith('/dashboard')
    })
  })

  it('shows error from response on failure', async () => {
    const user = userEvent.setup()
    mockLogin.mockRejectedValueOnce({
      response: { data: { message: 'The provided credentials are incorrect.' } },
    })
    renderLogin()

    await user.type(screen.getByLabelText(/email/i), 'bad@example.com')
    await user.type(screen.getByLabelText(/password/i), 'wrong')
    await user.click(screen.getByRole('button', { name: /sign in/i }))

    await waitFor(() =>
      expect(screen.getByRole('alert')).toHaveTextContent('The provided credentials are incorrect.')
    )
  })

  it('shows fallback error when response has no message', async () => {
    const user = userEvent.setup()
    mockLogin.mockRejectedValueOnce({})
    renderLogin()

    await user.type(screen.getByLabelText(/email/i), 'a@b.com')
    await user.type(screen.getByLabelText(/password/i), 'pass')
    await user.click(screen.getByRole('button', { name: /sign in/i }))

    await waitFor(() =>
      expect(screen.getByRole('alert')).toHaveTextContent('Login failed')
    )
  })

  it('disables submit button while loading', async () => {
    const user = userEvent.setup()
    let resolve
    mockLogin.mockReturnValueOnce(new Promise((r) => { resolve = r }))
    renderLogin()

    await user.type(screen.getByLabelText(/email/i), 'a@b.com')
    await user.type(screen.getByLabelText(/password/i), 'pass')
    await user.click(screen.getByRole('button', { name: /sign in/i }))

    await waitFor(() =>
      expect(screen.getByRole('button', { name: /signing in/i })).toBeDisabled()
    )
    resolve({ token: 't' })
  })
})
