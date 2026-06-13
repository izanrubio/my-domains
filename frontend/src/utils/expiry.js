export function expiryColorClass(days) {
  if (days === null || days === undefined) return 'text-gray-400'
  if (days < 0) return 'text-red-700'
  if (days < 7) return 'text-red-500'
  if (days < 30) return 'text-orange-500'
  return 'text-green-600'
}

export function expiryBadgeClass(days) {
  if (days === null || days === undefined)
    return 'bg-gray-100 text-gray-500'
  if (days < 0) return 'bg-red-100 text-red-800'
  if (days < 7) return 'bg-red-100 text-red-700'
  if (days < 30) return 'bg-orange-100 text-orange-700'
  return 'bg-green-100 text-green-700'
}

export function formatDays(days) {
  if (days === null || days === undefined) return '—'
  if (days < 0) return `Expired ${Math.abs(days)}d ago`
  if (days === 0) return 'Expires today'
  return `${days}d`
}
