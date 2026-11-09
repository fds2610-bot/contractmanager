import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { categoryColor, daysUntil, deadlineUrgency, intervalPerLabel, relativeDays, statusChip } from './listDisplay'
import type { Contract } from '../store/contracts'

const FAKE_NOW = new Date('2026-11-09T08:00:00')

function inDays(days: number): string {
	const d = new Date(FAKE_NOW)
	d.setDate(d.getDate() + days)
	const pad = (n: number) => String(n).padStart(2, '0')
	return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

describe('statusChip', () => {
	beforeEach(() => {
		vi.useFakeTimers()
		vi.setSystemTime(FAKE_NOW)
	})
	afterEach(() => vi.useRealTimers())

	it('marks a running auto-renewal contract as active', () => {
		const c: Contract = { id: 1, status: 'active', contractType: 'auto_renewal', endDate: inDays(300), cancellationPeriod: '1 months', renewalPeriod: '12 months' }
		expect(statusChip(c, 14).cls).toBe('active')
	})

	it('flags an auto-renewal contract whose deadline is near', () => {
		const c: Contract = { id: 1, status: 'active', contractType: 'auto_renewal', endDate: inDays(40), cancellationPeriod: '30 days', renewalPeriod: '12 months' }
		expect(statusChip(c, 14).cls).toBe('soon')
	})

	it('shows a planned contract as planned', () => {
		const c: Contract = { id: 1, status: 'active', contractType: 'fixed', startDate: inDays(10), endDate: inDays(400) }
		expect(statusChip(c, 14).cls).toBe('planned')
	})

	it('distinguishes cancelled and ended', () => {
		expect(statusChip({ id: 1, status: 'cancelled', contractType: 'auto_renewal', endDate: inDays(100), cancelledTo: inDays(100) }, 14).cls).toBe('cancelled')
		expect(statusChip({ id: 1, status: 'ended', contractType: 'fixed', endDate: inDays(-5) }, 14).cls).toBe('ended')
	})

	it('uses active-fixed for a running fixed-term contract', () => {
		const c: Contract = { id: 1, status: 'active', contractType: 'fixed', endDate: inDays(300) }
		expect(statusChip(c, 14).cls).toBe('active-fixed')
	})
})

describe('daysUntil', () => {
	it('counts calendar days regardless of the time of day', () => {
		const today = new Date('2026-11-09T23:30:00')
		expect(daysUntil(new Date('2026-11-19T00:10:00'), today)).toBe(10)
		expect(daysUntil(new Date('2026-11-09T01:00:00'), today)).toBe(0)
		expect(daysUntil(new Date('2026-11-06T12:00:00'), today)).toBe(-3)
	})
})

describe('relativeDays', () => {
	it('uses days up to 45, then months, then years', () => {
		expect(relativeDays(10, 'de-DE')).toBe('in 10 Tagen')
		expect(relativeDays(1, 'de-DE')).toBe('morgen')
		expect(relativeDays(0, 'de-DE')).toBe('heute')
		expect(relativeDays(-3, 'de-DE')).toBe('vor 3 Tagen')
		expect(relativeDays(61, 'de-DE')).toBe('in 2 Monaten')
		expect(relativeDays(800, 'de-DE')).toBe('in 2 Jahren')
	})
})

describe('deadlineUrgency', () => {
	it('is critical within 14 days and a warning within the reminder window', () => {
		expect(deadlineUrgency(10, 'cancelBy', 60)).toBe('critical')
		expect(deadlineUrgency(30, 'cancelBy', 60)).toBe('warning')
		expect(deadlineUrgency(90, 'cancelBy', 60)).toBe('normal')
		expect(deadlineUrgency(-2, 'runsUntil', 60)).toBe('critical')
	})

	it('keeps a „gekündigt zum" date neutral', () => {
		expect(deadlineUrgency(5, 'cancelledTo', 60)).toBe('normal')
	})
})

describe('intervalPerLabel', () => {
	it('names the known intervals and ignores unknown ones', () => {
		expect(intervalPerLabel('monthly')).toBe('pro Monat')
		expect(intervalPerLabel('yearly')).toBe('pro Jahr')
		expect(intervalPerLabel('one_time')).toBe('einmalig')
		expect(intervalPerLabel(null)).toBe('')
		expect(intervalPerLabel('fortnightly')).toBe('')
	})
})

describe('categoryColor', () => {
	it('is stable per ID and grey without a category', () => {
		expect(categoryColor(3)).toBe(categoryColor(3))
		expect(categoryColor(3)).not.toBe(categoryColor(4))
		expect(categoryColor(null)).toBe('#949494')
		expect(categoryColor(3)).toMatch(/^#[0-9a-f]{6}$/i)
	})
})
