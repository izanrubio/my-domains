import { describe, it, expect } from 'vitest'
import { expiryColorClass, expiryBadgeClass, formatDays } from '../utils/expiry'

describe('expiry utilities', () => {
  describe('expiryColorClass', () => {
    it('returns gray for null', () => expect(expiryColorClass(null)).toBe('text-gray-400'))
    it('returns red for < 7 days', () => expect(expiryColorClass(3)).toBe('text-red-500'))
    it('returns red-700 for negative (expired)', () => expect(expiryColorClass(-1)).toBe('text-red-700'))
    it('returns orange for 7–29 days', () => expect(expiryColorClass(15)).toBe('text-orange-500'))
    it('returns green for 30+ days', () => expect(expiryColorClass(45)).toBe('text-green-600'))
    it('boundary: 7 days is orange', () => expect(expiryColorClass(7)).toBe('text-orange-500'))
    it('boundary: 30 days is green', () => expect(expiryColorClass(30)).toBe('text-green-600'))
  })

  describe('formatDays', () => {
    it('returns em-dash for null', () => expect(formatDays(null)).toBe('—'))
    it('formats positive days', () => expect(formatDays(10)).toBe('10d'))
    it('formats zero', () => expect(formatDays(0)).toBe('Expires today'))
    it('formats negative as expired', () => expect(formatDays(-5)).toBe('Expired 5d ago'))
  })
})
