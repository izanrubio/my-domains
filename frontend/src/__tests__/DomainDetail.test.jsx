import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { MemoryRouter } from 'react-router-dom'
import DomainDetail from '../pages/DomainDetail'

vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal()
  return { ...actual, useParams: () => ({ id: '1' }) }
})

vi.mock('../api/client', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}))

import client from '../api/client'

const mockDomain = {
  id: 1,
  name: 'example.com',
  status: 'active',
  expires_at: '2026-12-31',
  days_until_expiry: 200,
  auto_renew: false,
  notes: '',
  expiry_source: 'whois',
  dns_records: [],
  cloudflare_zone_id: 'zone-abc',
  last_synced_at: null,
}

const renderDetail = () => render(<MemoryRouter><DomainDetail /></MemoryRouter>)

describe('DomainDetail — WHOIS', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    client.get.mockResolvedValue({ data: mockDomain })
  })

  it('renders the Detect expiry (WHOIS) button in view mode', async () => {
    renderDetail()
    expect(await screen.findByRole('button', { name: /detect expiry/i })).toBeInTheDocument()
  })

  it('shows loading state while WHOIS request is in flight', async () => {
    const user = userEvent.setup()
    let resolveWhois
    client.post.mockReturnValueOnce(new Promise((r) => { resolveWhois = r }))

    renderDetail()
    await screen.findByRole('button', { name: /detect expiry/i })

    await user.click(screen.getByRole('button', { name: /detect expiry/i }))

    await waitFor(() =>
      expect(screen.getByRole('button', { name: /detecting/i })).toBeDisabled()
    )

    resolveWhois({ data: { ...mockDomain, expires_at: '2027-06-01', days_until_expiry: 353 } })
  })

  it('shows success message and updates expiry when WHOIS finds a date', async () => {
    const user = userEvent.setup()
    const updated = { ...mockDomain, expires_at: '2027-06-01', days_until_expiry: 353, expiry_source: 'whois' }
    client.post.mockResolvedValueOnce({ data: updated })

    renderDetail()
    await screen.findByRole('button', { name: /detect expiry/i })

    await user.click(screen.getByRole('button', { name: /detect expiry/i }))

    await waitFor(() =>
      expect(screen.getByRole('status')).toHaveTextContent(/expiry updated from whois/i)
    )
    // Button returns to idle
    expect(screen.getByRole('button', { name: /detect expiry/i })).not.toBeDisabled()
  })

  it('shows non-alarming warning and opens edit form when WHOIS returns 422 (no date found)', async () => {
    const user = userEvent.setup()
    client.post.mockRejectedValueOnce({ response: { status: 422 } })

    renderDetail()
    await screen.findByRole('button', { name: /detect expiry/i })

    await user.click(screen.getByRole('button', { name: /detect expiry/i }))

    await waitFor(() =>
      expect(screen.getByRole('status')).toHaveTextContent(/couldn't read the expiry date/i)
    )
    // Edit form opens so user can enter date manually
    expect(screen.getByLabelText(/expiry date/i)).toBeInTheDocument()
  })

  it('shows error message when WHOIS lookup itself fails (network / server error)', async () => {
    const user = userEvent.setup()
    client.post.mockRejectedValueOnce({ response: { status: 500 } })

    renderDetail()
    await screen.findByRole('button', { name: /detect expiry/i })

    await user.click(screen.getByRole('button', { name: /detect expiry/i }))

    await waitFor(() =>
      expect(screen.getByRole('alert')).toHaveTextContent(/whois lookup failed/i)
    )
  })
})
