import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import client from '../api/client'
import EmptyState from '../components/EmptyState'
import { expiryBadgeClass, formatDays } from '../utils/expiry'

const STATUS_BADGE = {
  active: 'bg-green-100 text-green-700',
  paused: 'bg-yellow-100 text-yellow-700',
  pending: 'bg-blue-100 text-blue-700',
  moved: 'bg-gray-100 text-gray-600',
}

export default function Domains() {
  const [domains, setDomains] = useState([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [sortAsc, setSortAsc] = useState(true)

  useEffect(() => {
    client.get('/domains').then(({ data }) => setDomains(data)).finally(() => setLoading(false))
  }, [])

  const filtered = domains
    .filter((d) => d.name.toLowerCase().includes(search.toLowerCase()))
    .sort((a, b) => {
      const da = a.expires_at ?? ''
      const db = b.expires_at ?? ''
      if (!da && !db) return 0
      if (!da) return 1
      if (!db) return -1
      return sortAsc ? da.localeCompare(db) : db.localeCompare(da)
    })

  if (loading) return <div className="text-gray-500 text-sm">Loading…</div>

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Domains</h1>
        <span className="text-sm text-gray-500">{domains.length} total</span>
      </div>

      {domains.length === 0 ? (
        <EmptyState
          title="No domains found"
          description="Connect your Cloudflare account in Settings and sync to import your domains."
          action={{ to: '/settings', label: 'Connect Cloudflare in Settings' }}
        />
      ) : (
        <>
          <div className="flex items-center gap-3">
            <input
              type="search"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search domains…"
              className="border border-gray-300 rounded-lg px-3 py-2 text-sm w-64 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <button
              onClick={() => setSortAsc((v) => !v)}
              className="text-xs text-gray-600 border border-gray-300 rounded-lg px-3 py-2 hover:bg-gray-50 transition-colors"
            >
              Expiry {sortAsc ? '↑' : '↓'}
            </button>
          </div>

          <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 text-left">
                <tr>
                  <th className="px-4 py-3 font-medium text-gray-600">Domain</th>
                  <th className="px-4 py-3 font-medium text-gray-600">Status</th>
                  <th className="px-4 py-3 font-medium text-gray-600">Expires</th>
                  <th className="px-4 py-3 font-medium text-gray-600">Days left</th>
                  <th className="px-4 py-3 font-medium text-gray-600">Auto-renew</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {filtered.map((d) => (
                  <tr key={d.id} className="hover:bg-gray-50 transition-colors">
                    <td className="px-4 py-3 font-medium">
                      <Link to={`/domains/${d.id}`} className="text-blue-600 hover:underline">
                        {d.name}
                      </Link>
                    </td>
                    <td className="px-4 py-3">
                      <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${STATUS_BADGE[d.status] ?? 'bg-gray-100 text-gray-600'}`}>
                        {d.status}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-gray-600">
                      {d.expires_at ? new Date(d.expires_at).toLocaleDateString() : '—'}
                    </td>
                    <td className="px-4 py-3">
                      <span className={`text-xs font-semibold px-2 py-1 rounded-full ${expiryBadgeClass(d.days_until_expiry)}`}>
                        {formatDays(d.days_until_expiry)}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-gray-600">
                      {d.auto_renew ? (
                        <span className="text-green-600 text-xs font-medium">Yes</span>
                      ) : (
                        <span className="text-gray-400 text-xs">No</span>
                      )}
                    </td>
                  </tr>
                ))}
                {filtered.length === 0 && (
                  <tr>
                    <td colSpan={5} className="px-4 py-8 text-center text-gray-400 text-sm">
                      No domains match "{search}"
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  )
}
