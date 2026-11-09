/**
 * Display helpers for the contract list and the details sidebar (2.0.1):
 * status chip, remaining time to the deadline, cost interval and the colour
 * dot of a „Vertragsart". Kept free of Vue so the rules can be unit-tested.
 */
import { translate as t, getCanonicalLocale } from '@nextcloud/l10n'
import type { Contract } from '../store/contracts'
import { isEndingSoon, isEndingSoonFixed, isExpiredFixed, isEndedCancelled, isPlanned } from './contractStatus'

export type ChipClass = 'planned' | 'active' | 'active-fixed' | 'soon' | 'cancelled' | 'ended' | 'expired'

export interface StatusChip {
	cls: ChipClass
	label: string
	title: string
}

/**
 * One derived status per contract, in the words of the list. Order matters:
 * „Geplant" and „Abgelaufen" beat the reminder hint, which beats the stored
 * status.
 */
export function statusChip(contract: Contract, reminderDays?: number): StatusChip {
	if (isPlanned(contract)) {
		return { cls: 'planned', label: t('nc_vertragswerk', 'Geplant'), title: t('nc_vertragswerk', 'Der Vertrag beginnt erst zum Startdatum.') }
	}
	if (isExpiredFixed(contract)) {
		return { cls: 'expired', label: t('nc_vertragswerk', 'Abgelaufen'), title: t('nc_vertragswerk', 'Das Enddatum ist überschritten.') }
	}
	if (isEndingSoon(contract, reminderDays)) {
		return { cls: 'soon', label: t('nc_vertragswerk', 'Kündigungsfrist endet'), title: t('nc_vertragswerk', 'Die Kündigungsfrist läuft in Kürze ab.') }
	}
	if (isEndingSoonFixed(contract, reminderDays)) {
		return { cls: 'soon', label: t('nc_vertragswerk', 'endet'), title: t('nc_vertragswerk', 'Der befristete Vertrag läuft in Kürze aus.') }
	}
	if (isEndedCancelled(contract) || contract.status === 'ended') {
		return { cls: 'ended', label: t('nc_vertragswerk', 'Beendet'), title: '' }
	}
	if (contract.status === 'cancelled') {
		return { cls: 'cancelled', label: t('nc_vertragswerk', 'Gekündigt'), title: '' }
	}
	if (contract.contractType === 'fixed') {
		return { cls: 'active-fixed', label: t('nc_vertragswerk', 'Laufend'), title: t('nc_vertragswerk', 'Befristeter Vertrag, endet automatisch zum Enddatum.') }
	}
	return { cls: 'active', label: t('nc_vertragswerk', 'Laufend'), title: '' }
}

/** Whole calendar days from today to `date` (negative = in the past). */
export function daysUntil(date: Date, today: Date = new Date()): number {
	const a = new Date(today.getFullYear(), today.getMonth(), today.getDate())
	const b = new Date(date.getFullYear(), date.getMonth(), date.getDate())
	return Math.round((b.getTime() - a.getTime()) / 86400000)
}

/**
 * „in 10 Tagen", „morgen", „vor 3 Monaten" in the user's language. Days up to
 * 45, then months up to two years, then years.
 */
export function relativeDays(days: number, locale: string = getCanonicalLocale()): string {
	const rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' })
	const abs = Math.abs(days)
	if (abs <= 45) {
		return rtf.format(days, 'day')
	}
	if (abs < 730) {
		return rtf.format(Math.round(days / 30.44), 'month')
	}
	return rtf.format(Math.round(days / 365.25), 'year')
}

export type Urgency = 'critical' | 'warning' | 'normal'

/**
 * How pressing a date in the „Frist / Ende" column is. A date the contract was
 * cancelled to is information, not a task, and stays neutral.
 */
export function deadlineUrgency(days: number, labelKey: string | null, reminderDays: number): Urgency {
	if (labelKey === 'cancelledTo') return 'normal'
	if (days <= 14) return 'critical'
	if (days <= reminderDays) return 'warning'
	return 'normal'
}

/** „pro Monat", „pro Jahr" … below the amount; empty for unknown intervals. */
export function intervalPerLabel(interval: string | null | undefined): string {
	const labels: Record<string, string> = {
		weekly: t('nc_vertragswerk', 'pro Woche'),
		monthly: t('nc_vertragswerk', 'pro Monat'),
		quarterly: t('nc_vertragswerk', 'pro Quartal'),
		semi_annual: t('nc_vertragswerk', 'pro Halbjahr'),
		yearly: t('nc_vertragswerk', 'pro Jahr'),
		one_time: t('nc_vertragswerk', 'einmalig'),
	}
	return interval ? (labels[interval] ?? '') : ''
}

/**
 * Colour dot of a „Vertragsart" in the navigation. Categories carry no colour
 * of their own, so it follows from the ID and stays the same across sessions.
 */
const CATEGORY_COLORS = ['#0082c9', '#8e44ad', '#e67e22', '#16a085', '#c0392b', '#2980b9', '#d35400', '#27ae60', '#7f8c8d', '#b0479a']

export function categoryColor(id: number | null | undefined): string {
	if (id === null || id === undefined) return '#949494'
	return CATEGORY_COLORS[Math.abs(id) % CATEGORY_COLORS.length]
}
