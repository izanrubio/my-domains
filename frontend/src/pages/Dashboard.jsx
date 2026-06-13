import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import client from '../api/client'
import EmptyState from '../components/EmptyState'
import { expiryBadgeClass, formatDays } from '../utils/expiry'

export default function Dashboard() {
  const [domains, setDomains] = useState([])
  const [loading, setLoading] = useState(true)
  const [syncing, setSyncing] = useState(false)
  const [syncMsg, setSyncMsg] = useState('')
  const [error, setError] = useState('')

  const fetchDomains = async () => {
    try {
      const { data } = await client.get('/domains')
      setDomains(data)
    } catch {
      setError('Failed to load domains.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { fetchDomains() }, [])

  const handleSync = async () => {
    setSyncing(true)
    setSyncMsg('')
    try {
      await client.post('/domains/sync')
      setSyncMsg('Sync complete!')
      await fetchDomains()
    } catch {
      setSyncMsg('Sync failed. Check your Cloudflare token in Settings.')
    } finally {
      setSyncing(false)
    }
  }

  const critical = domains.filter((d) => d.days_until_expiry !== null && d.days_until_expiry < 7 && d.days_until_expiry >= 0)
  const warning = domains.filter((d) => d.days_until_expiry !== null && d.days_until_expiry >= 7 && d.days_until_expiry < 30)
  const expiringSoon = domains.filter((d) => d.days_until_expiry !== null && d.days_until_expiry < 30 && d.days_until_expiry >= 0)

  if (loading) {
    return <div className="text-gray-500 text-sm">Loading…</div>
  }

  return (
    <div className="space-y-8">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
        <div className="flex items-center gap-3">
          {syncMsg && <span className="text-sm text-gray-600">{syncMsg}</span>}
          <button
            onClick={handleSync}
            disabled={syncing}
            className="bg-blue-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-60 transition-colors"
          >
            {syncing ? 'Syncing…' : 'Sync with Cloudflare'}
          </button>
        </div>
      </div>

      {error && <p className="text-red-600 text-sm">{error}</p>}

      {domains.length === 0 ? (
        <EmptyState
          title="No domains yet"
          description="Connect your Cloudflare account in Settings, then sync to import your domains."
          action={{ to: '/settings', label: 'Connect Cloudflare in Settings' }}
        />
      ) : (
        <>
          {/* Summary cards */}
          <div className="grid grid-cols-3 gap-4">
            <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
              <p className="text-sm text-gray-500">Total domains</p>
              <p className="text-3xl font-bold text-gray-900 mt-1">{domains.length}</p>
            </div>
            <div className={`rounded-xl border p-5 shadow-sm ${critical.length > 0 ? 'bg-red-50 border-red-200' : 'bg-white border-gray-200'}`}>
              <p className="text-sm text-gray-500">Expiring &lt; 7 days</p>
              <p className={`text-3xl font-bold mt-1 ${critical.length > 0 ? 'text-red-600' : 'text-gray-900'}`}>
                {critical.length}
              </p>
            </div>
            <div className={`rounded-xl border p-5 shadow-sm ${warning.length > 0 ? 'bg-orange-50 border-orange-200' : 'bg-white border-gray-200'}`}>
              <p className="text-sm text-gray-500">Expiring 7–30 days</p>
              <p className={`text-3xl font-bold mt-1 ${warning.length > 0 ? 'text-orange-600' : 'text-gray-900'}`}>
                {warning.length}
              </p>
            </div>
          </div>

          {/* Alerts panel */}
          {expiringSoon.length > 0 && (
            <div className="bg-white rounded-xl border border-gray-200 shadow-sm">
              <div className="px-5 py-4 border-b border-gray-100">
                <h2 className="text-sm font-semibold text-gray-700">Domains expiring soon</h2>
              </div>
              <ul className="divide-y divide-gray-100">
                {expiringSoon.map((d) => (
                  <li key={d.id} className="flex items-center justify-between px-5 py-3">
                    <Link to={`/domains/${d.id}`} className="text-sm font-medium text-blue-600 hover:underline">
                      {d.name}
                    </Link>
                    <span className={`text-xs font-semibold px-2 py-1 rounded-full ${expiryBadgeClass(d.days_until_expiry)}`}>
                      {formatDays(d.days_until_expiry)}
                    </span>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </>
      )}
    </div>
  )
}
