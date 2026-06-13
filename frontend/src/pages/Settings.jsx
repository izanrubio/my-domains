import { useEffect, useState } from 'react'
import client from '../api/client'

export default function Settings() {
  const [settings, setSettings] = useState({ cloudflare_api_token: '', expiry_alert_days: 30, alert_email: '' })
  const [token, setToken] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [msg, setMsg] = useState('')
  const [isError, setIsError] = useState(false)

  useEffect(() => {
    client.get('/settings').then(({ data }) => {
      setSettings({
        ...data,
        // normalize null → default so the submit payload is always valid
        expiry_alert_days: data.expiry_alert_days ?? 30,
      })
      setToken('')
    }).finally(() => setLoading(false))
  }, [])

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setMsg('')
    setIsError(false)
    try {
      const payload = {}

      // Only include token when the user typed a new one
      if (token) payload.cloudflare_api_token = token

      // Only include email when it has a value (partial updates are fine)
      if (settings.alert_email) payload.alert_email = settings.alert_email

      // Always send threshold as a valid integer (default 30 if somehow empty)
      const days = parseInt(settings.expiry_alert_days, 10)
      payload.expiry_alert_days = days > 0 ? days : 30

      await client.put('/settings', payload)
      setToken('')
      setMsg('Settings saved.')
    } catch (err) {
      setIsError(true)
      const errs = err.response?.data?.errors
      setMsg(errs ? Object.values(errs).flat().join(' ') : 'Save failed.')
    } finally {
      setSaving(false)
    }
  }

  if (loading) return <div className="text-gray-500 text-sm">Loading…</div>

  return (
    <div className="space-y-6 max-w-xl">
      <h1 className="text-2xl font-bold text-gray-900">Settings</h1>

      {msg && (
        <div
          role={isError ? 'alert' : 'status'}
          className={`text-sm rounded-lg p-3 ${isError ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700'}`}
        >
          {msg}
        </div>
      )}

      <form onSubmit={handleSubmit} className="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">
        <div>
          <label htmlFor="cf_token" className="block text-sm font-medium text-gray-700 mb-1">
            Cloudflare API Token
          </label>
          {settings.cloudflare_api_token && (
            <p className="text-xs text-gray-500 mb-2">
              Current: <code className="bg-gray-100 px-1 rounded">{settings.cloudflare_api_token}</code>
            </p>
          )}
          <input
            id="cf_token"
            type="password"
            value={token}
            onChange={(e) => setToken(e.target.value)}
            placeholder={settings.cloudflare_api_token ? 'Enter new token to change' : 'Enter your Cloudflare API token'}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          <p className="text-xs text-gray-400 mt-1">
            Create an API token at cloudflare.com with Zone:Read and DNS:Edit permissions.
          </p>
        </div>

        <div>
          <label htmlFor="alert_email" className="block text-sm font-medium text-gray-700 mb-1">
            Alert email
          </label>
          <input
            id="alert_email"
            type="email"
            value={settings.alert_email ?? ''}
            onChange={(e) => setSettings({ ...settings, alert_email: e.target.value })}
            placeholder="alerts@example.com"
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label htmlFor="expiry_days" className="block text-sm font-medium text-gray-700 mb-1">
            Alert threshold (days before expiry)
          </label>
          <input
            id="expiry_days"
            type="number"
            min={1}
            max={365}
            value={settings.expiry_alert_days ?? 30}
            onChange={(e) => setSettings({ ...settings, expiry_alert_days: e.target.value })}
            className="border border-gray-300 rounded-lg px-3 py-2 text-sm w-28 focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <button
          type="submit"
          disabled={saving}
          className="bg-blue-600 text-white text-sm font-semibold px-5 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-60 transition-colors"
        >
          {saving ? 'Saving…' : 'Save settings'}
        </button>
      </form>
    </div>
  )
}
