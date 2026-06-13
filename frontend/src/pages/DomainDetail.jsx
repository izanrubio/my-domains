import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import client from '../api/client'
import { expiryBadgeClass, formatDays } from '../utils/expiry'

const DNS_TYPES = ['A', 'AAAA', 'CNAME', 'TXT', 'MX', 'NS', 'SRV', 'CAA', 'PTR']

function DnsRow({ record, zoneId, onUpdated, onDeleted }) {
  const [editing, setEditing] = useState(false)
  const [form, setForm] = useState({ type: record.type, name: record.name, content: record.content, ttl: record.ttl, proxied: record.proxied })
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')

  const handleSave = async () => {
    setSaving(true)
    setError('')
    try {
      const { data } = await client.put(`/domains/${record.domain_id}/dns/${record.cloudflare_record_id}`, form)
      onUpdated(data)
      setEditing(false)
    } catch (err) {
      const msgs = err.response?.data?.errors
      setError(msgs ? Object.values(msgs).flat().join(', ') : 'Save failed.')
    } finally {
      setSaving(false)
    }
  }

  const handleDelete = async () => {
    if (!confirm(`Delete ${record.type} record "${record.name}"?`)) return
    try {
      await client.delete(`/domains/${record.domain_id}/dns/${record.cloudflare_record_id}`)
      onDeleted(record.cloudflare_record_id)
    } catch {
      alert('Delete failed.')
    }
  }

  if (editing) {
    return (
      <tr className="bg-blue-50">
        <td className="px-4 py-2">
          <select
            value={form.type}
            onChange={(e) => setForm({ ...form, type: e.target.value })}
            className="border rounded px-2 py-1 text-xs w-full"
          >
            {DNS_TYPES.map((t) => <option key={t}>{t}</option>)}
          </select>
        </td>
        <td className="px-4 py-2">
          <input className="border rounded px-2 py-1 text-xs w-full" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
        </td>
        <td className="px-4 py-2">
          <input className="border rounded px-2 py-1 text-xs w-full" value={form.content} onChange={(e) => setForm({ ...form, content: e.target.value })} />
        </td>
        <td className="px-4 py-2">
          <input type="number" className="border rounded px-2 py-1 text-xs w-20" value={form.ttl} onChange={(e) => setForm({ ...form, ttl: Number(e.target.value) })} />
        </td>
        <td className="px-4 py-2">
          <input type="checkbox" checked={form.proxied} onChange={(e) => setForm({ ...form, proxied: e.target.checked })} />
        </td>
        <td className="px-4 py-2 space-x-2">
          <button onClick={handleSave} disabled={saving} className="text-xs text-blue-600 font-medium hover:underline disabled:opacity-50">
            {saving ? 'Saving…' : 'Save'}
          </button>
          <button onClick={() => setEditing(false)} className="text-xs text-gray-500 hover:underline">Cancel</button>
          {error && <span className="text-red-500 text-xs">{error}</span>}
        </td>
      </tr>
    )
  }

  return (
    <tr className="hover:bg-gray-50">
      <td className="px-4 py-3"><span className="font-mono text-xs bg-gray-100 px-1.5 py-0.5 rounded">{record.type}</span></td>
      <td className="px-4 py-3 text-sm text-gray-700 font-medium">{record.name}</td>
      <td className="px-4 py-3 text-sm text-gray-600 font-mono max-w-xs truncate">{record.content}</td>
      <td className="px-4 py-3 text-sm text-gray-500">{record.ttl === 1 ? 'Auto' : record.ttl}</td>
      <td className="px-4 py-3">
        {record.proxied ? (
          <span className="text-orange-500 text-xs font-medium">Proxied</span>
        ) : (
          <span className="text-gray-400 text-xs">DNS only</span>
        )}
      </td>
      <td className="px-4 py-3 space-x-3">
        <button onClick={() => setEditing(true)} className="text-xs text-blue-600 hover:underline">Edit</button>
        <button onClick={handleDelete} className="text-xs text-red-500 hover:underline">Delete</button>
      </td>
    </tr>
  )
}

function AddDnsRow({ domainId, onAdded }) {
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState({ type: 'A', name: '', content: '', ttl: 1, proxied: false })
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')

  const handleAdd = async (e) => {
    e.preventDefault()
    setSaving(true)
    setError('')
    try {
      const { data } = await client.post(`/domains/${domainId}/dns`, form)
      onAdded(data)
      setForm({ type: 'A', name: '', content: '', ttl: 1, proxied: false })
      setOpen(false)
    } catch (err) {
      const msgs = err.response?.data?.errors
      setError(msgs ? Object.values(msgs).flat().join(', ') : 'Add failed.')
    } finally {
      setSaving(false)
    }
  }

  if (!open) {
    return (
      <tr>
        <td colSpan={6} className="px-4 py-3">
          <button onClick={() => setOpen(true)} className="text-sm text-blue-600 font-medium hover:underline">
            + Add DNS record
          </button>
        </td>
      </tr>
    )
  }

  return (
    <tr className="bg-green-50">
      <td className="px-4 py-2">
        <select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })} className="border rounded px-2 py-1 text-xs w-full">
          {DNS_TYPES.map((t) => <option key={t}>{t}</option>)}
        </select>
      </td>
      <td className="px-4 py-2">
        <input placeholder="name" className="border rounded px-2 py-1 text-xs w-full" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
      </td>
      <td className="px-4 py-2">
        <input placeholder="content" className="border rounded px-2 py-1 text-xs w-full" value={form.content} onChange={(e) => setForm({ ...form, content: e.target.value })} required />
      </td>
      <td className="px-4 py-2">
        <input type="number" className="border rounded px-2 py-1 text-xs w-20" value={form.ttl} onChange={(e) => setForm({ ...form, ttl: Number(e.target.value) })} />
      </td>
      <td className="px-4 py-2">
        <input type="checkbox" checked={form.proxied} onChange={(e) => setForm({ ...form, proxied: e.target.checked })} />
      </td>
      <td className="px-4 py-2 space-x-2">
        <button onClick={handleAdd} disabled={saving} className="text-xs text-green-700 font-medium hover:underline disabled:opacity-50">
          {saving ? 'Adding…' : 'Add'}
        </button>
        <button onClick={() => setOpen(false)} className="text-xs text-gray-500 hover:underline">Cancel</button>
        {error && <span className="text-red-500 text-xs">{error}</span>}
      </td>
    </tr>
  )
}

export default function DomainDetail() {
  const { id } = useParams()
  const [domain, setDomain] = useState(null)
  const [dnsRecords, setDnsRecords] = useState([])
  const [loading, setLoading] = useState(true)
  const [dnsLoading, setDnsLoading] = useState(false)
  const [editing, setEditing] = useState(false)
  const [form, setForm] = useState({})
  const [saving, setSaving] = useState(false)
  const [whoisLoading, setWhoisLoading] = useState(false)
  const [msg, setMsg] = useState('')

  const fetchDomain = async () => {
    const { data } = await client.get(`/domains/${id}`)
    setDomain(data)
    setDnsRecords(data.dns_records ?? [])
    setForm({ expires_at: data.expires_at ?? '', auto_renew: data.auto_renew, notes: data.notes ?? '' })
  }

  useEffect(() => {
    fetchDomain().finally(() => setLoading(false))
  }, [id])

  const fetchFreshDns = async () => {
    setDnsLoading(true)
    try {
      const { data } = await client.get(`/domains/${id}/dns`)
      setDnsRecords(data)
    } finally {
      setDnsLoading(false)
    }
  }

  const handleSave = async (e) => {
    e.preventDefault()
    setSaving(true)
    setMsg('')
    try {
      const { data } = await client.put(`/domains/${id}`, {
        expires_at: form.expires_at || null,
        auto_renew: form.auto_renew,
        notes: form.notes,
        ...(form.expires_at ? { expiry_source: 'manual' } : {}),
      })
      setDomain(data)
      setEditing(false)
      setMsg('Saved.')
    } catch {
      setMsg('Save failed.')
    } finally {
      setSaving(false)
    }
  }

  const handleWhois = async () => {
    setWhoisLoading(true)
    setMsg('')
    try {
      const { data } = await client.post(`/domains/${id}/whois`)
      setDomain(data)
      setForm((f) => ({ ...f, expires_at: data.expires_at ?? '' }))
      setMsg('Expiry updated from WHOIS.')
    } catch {
      setMsg('WHOIS lookup failed — date not found.')
    } finally {
      setWhoisLoading(false)
    }
  }

  if (loading) return <div className="text-gray-500 text-sm">Loading…</div>
  if (!domain) return <div className="text-red-500 text-sm">Domain not found.</div>

  return (
    <div className="space-y-8">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900">{domain.name}</h1>
        <div className="flex items-center gap-3 mt-1">
          <span className="text-sm text-gray-500">{domain.status}</span>
          <span className={`text-xs font-semibold px-2 py-1 rounded-full ${expiryBadgeClass(domain.days_until_expiry)}`}>
            {formatDays(domain.days_until_expiry)}
          </span>
        </div>
      </div>

      {msg && <p className="text-sm text-gray-600">{msg}</p>}

      {/* Domain info / edit */}
      <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <div className="flex items-center justify-between mb-4">
          <h2 className="font-semibold text-gray-800">Domain info</h2>
          {!editing && (
            <button onClick={() => setEditing(true)} className="text-sm text-blue-600 hover:underline">Edit</button>
          )}
        </div>

        {editing ? (
          <form onSubmit={handleSave} className="space-y-4">
            <div>
              <label htmlFor="expires_at" className="block text-sm font-medium text-gray-700 mb-1">Expiry date</label>
              <input
                id="expires_at"
                type="date"
                value={form.expires_at}
                onChange={(e) => setForm({ ...form, expires_at: e.target.value })}
                className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div className="flex items-center gap-2">
              <input
                id="auto_renew"
                type="checkbox"
                checked={form.auto_renew}
                onChange={(e) => setForm({ ...form, auto_renew: e.target.checked })}
              />
              <label htmlFor="auto_renew" className="text-sm text-gray-700">Auto-renew</label>
            </div>
            <div>
              <label htmlFor="notes" className="block text-sm font-medium text-gray-700 mb-1">Notes</label>
              <textarea
                id="notes"
                rows={3}
                value={form.notes}
                onChange={(e) => setForm({ ...form, notes: e.target.value })}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div className="flex gap-3">
              <button type="submit" disabled={saving} className="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-60">
                {saving ? 'Saving…' : 'Save'}
              </button>
              <button type="button" onClick={() => setEditing(false)} className="text-sm text-gray-600 hover:underline">Cancel</button>
              <button
                type="button"
                onClick={handleWhois}
                disabled={whoisLoading}
                className="ml-auto text-sm text-purple-600 border border-purple-300 px-3 py-2 rounded-lg hover:bg-purple-50 disabled:opacity-60"
              >
                {whoisLoading ? 'Detecting…' : 'Detect expiry (WHOIS)'}
              </button>
            </div>
          </form>
        ) : (
          <dl className="grid grid-cols-2 gap-4 text-sm">
            <div>
              <dt className="text-gray-500">Expires</dt>
              <dd className="font-medium text-gray-800 mt-0.5">
                {domain.expires_at ? new Date(domain.expires_at).toLocaleDateString() : '—'}
                {domain.expiry_source && <span className="ml-2 text-xs text-gray-400">({domain.expiry_source})</span>}
              </dd>
            </div>
            <div>
              <dt className="text-gray-500">Auto-renew</dt>
              <dd className="font-medium text-gray-800 mt-0.5">{domain.auto_renew ? 'Yes' : 'No'}</dd>
            </div>
            <div>
              <dt className="text-gray-500">Last synced</dt>
              <dd className="font-medium text-gray-800 mt-0.5">
                {domain.last_synced_at ? new Date(domain.last_synced_at).toLocaleString() : '—'}
              </dd>
            </div>
            <div className="col-span-2">
              <dt className="text-gray-500">Notes</dt>
              <dd className="font-medium text-gray-800 mt-0.5 whitespace-pre-wrap">{domain.notes || '—'}</dd>
            </div>
          </dl>
        )}
      </div>

      {/* DNS records */}
      <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 className="font-semibold text-gray-800">DNS Records</h2>
          <button
            onClick={fetchFreshDns}
            disabled={dnsLoading}
            className="text-xs text-gray-600 border border-gray-300 rounded px-2 py-1 hover:bg-gray-50 disabled:opacity-50"
          >
            {dnsLoading ? 'Refreshing…' : 'Refresh from Cloudflare'}
          </button>
        </div>

        {domain.cloudflare_zone_id ? (
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-left">
              <tr>
                <th className="px-4 py-3 font-medium text-gray-600 w-20">Type</th>
                <th className="px-4 py-3 font-medium text-gray-600">Name</th>
                <th className="px-4 py-3 font-medium text-gray-600">Content</th>
                <th className="px-4 py-3 font-medium text-gray-600 w-24">TTL</th>
                <th className="px-4 py-3 font-medium text-gray-600 w-24">Proxy</th>
                <th className="px-4 py-3 font-medium text-gray-600 w-32">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {dnsRecords.map((rec) => (
                <DnsRow
                  key={rec.cloudflare_record_id}
                  record={rec}
                  zoneId={domain.cloudflare_zone_id}
                  onUpdated={(updated) =>
                    setDnsRecords((rs) => rs.map((r) => r.cloudflare_record_id === updated.cloudflare_record_id ? updated : r))
                  }
                  onDeleted={(cfId) =>
                    setDnsRecords((rs) => rs.filter((r) => r.cloudflare_record_id !== cfId))
                  }
                />
              ))}
              <AddDnsRow
                domainId={id}
                onAdded={(rec) => setDnsRecords((rs) => [...rs, rec])}
              />
            </tbody>
          </table>
        ) : (
          <p className="px-5 py-6 text-sm text-gray-500">No Cloudflare zone ID — sync this domain first.</p>
        )}
      </div>
    </div>
  )
}
