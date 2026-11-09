/**
 * „Vertragsspiegel" (#489): the contracts currently shown in the list,
 * grouped by category, as plain text rows for the PDF.
 *
 * Kept free of jsPDF so the content rules (which lines appear, in which order,
 * no empty lines) can be tested without rendering.
 */
import { translate as t, getCanonicalLocale } from '@nextcloud/l10n'
import type { Contract } from '../store/contracts'
import type { Category } from '../store/categories'
import type { ResolvedContact } from '../services/ContactService'
import { personTypeOptions } from './insurance'
import { isEndedCancelled, isExpiredFixed, isPlanned } from './contractStatus'
import { getEffectiveEndDate } from './periodUtils.js'
import { formatDate } from './dateUtils.js'

/**
 * Options for translations that go into the PDF: placeholders are inserted
 * verbatim. The default escaping is meant for HTML and would print
 * „A &amp; B" for a partner „A & B".
 */
export const PLAIN = { escape: false, sanitize: false }

export interface MirrorRow {
	/** Contract name, plus the contract number below it when there is one. */
	contract: string[]
	/** Contract partner, then the linked address book contact. */
	vendor: string[]
	/** Responsible user → persons → insurance benefits → notes. */
	scope: string[]
	endDate: string
	status: string
	interval: string
	amount: string
}

export interface MirrorGroup {
	title: string
	rows: MirrorRow[]
}

export interface MirrorLookups {
	/** Address book contacts by UID; missing or null = not resolvable. */
	contacts?: Record<string, ResolvedContact | null>
	/** Display names by Nextcloud user ID; missing = fall back to the ID. */
	userNames?: Record<string, string>
}

/** Non-empty, trimmed lines of a free text; blank lines are dropped. */
export function textLines(text: string | null | undefined): string[] {
	return (text ?? '')
		.split(/\r?\n/)
		.map(line => line.trim())
		.filter(line => line !== '')
}

export function intervalLabel(interval: string | null | undefined): string {
	const labels: Record<string, string> = {
		weekly: t('nc_vertragswerk', 'Wöchentlich'),
		monthly: t('nc_vertragswerk', 'Monatlich'),
		quarterly: t('nc_vertragswerk', 'Quartalsweise'),
		semi_annual: t('nc_vertragswerk', 'Halbjährlich'),
		yearly: t('nc_vertragswerk', 'Jährlich'),
		one_time: t('nc_vertragswerk', 'Einmalig'),
	}
	return interval ? (labels[interval] ?? interval) : ''
}

/**
 * Status as of today, in the words of the list: a contract that has not started
 * yet is „Geplant", an overdue fixed contract „Abgelaufen", a cancelled one past
 * its end „Beendet". The short-lived hint „Kündigungsfrist endet" is left out —
 * it is a reminder, not a status, and would be stale on paper.
 */
export function statusLabel(contract: Contract): string {
	if (isPlanned(contract)) return t('nc_vertragswerk', 'Geplant')
	if (isExpiredFixed(contract)) return t('nc_vertragswerk', 'Abgelaufen')
	if (isEndedCancelled(contract) || contract.status === 'ended') return t('nc_vertragswerk', 'Beendet')
	if (contract.status === 'cancelled') return t('nc_vertragswerk', 'Gekündigt')
	return t('nc_vertragswerk', 'Laufend')
}

export function formatAmount(cost: string | null | undefined, currency: string | null | undefined): string {
	const amount = parseFloat(String(cost ?? ''))
	if (!Number.isFinite(amount)) return ''
	return new Intl.NumberFormat(getCanonicalLocale(), { style: 'currency', currency: currency || 'EUR' }).format(amount)
}

/** "Läuft bis": the effective end, i.e. renewals rolled forward and „gekündigt zum" respected. */
export function endDateLabel(contract: Contract): string {
	if (!contract.endDate) return ''
	const end = getEffectiveEndDate(contract.endDate, contract.contractType ?? 'fixed', contract.renewalPeriod ?? '', {
		status: contract.status,
		cancelledTo: contract.cancelledTo,
	})
	return end ? formatDate(end) : ''
}

function vendorLines(contract: Contract, contact: ResolvedContact | null | undefined): string[] {
	const lines: string[] = []
	const seen = new Set<string>()
	const add = (value: string | null | undefined) => {
		for (const line of textLines(value)) {
			const key = line.toLowerCase()
			if (!seen.has(key)) {
				seen.add(key)
				lines.push(line)
			}
		}
	}
	add(contract.vendor)
	if (contact) {
		add(contact.organization)
		add(contact.name)
		contact.addresses.forEach(add)
		contact.phones.forEach(phone => add(t('nc_vertragswerk', 'Tel. {phone}', { phone }, undefined, PLAIN)))
		contact.emails.forEach(add)
	}
	return lines
}

function scopeLines(contract: Contract, userNames: Record<string, string>): string[] {
	const lines: string[] = []
	const responsible = (contract.responsibleUser ?? '').trim()
	if (responsible !== '') {
		lines.push(t('nc_vertragswerk', 'Zuständig: {name}', { name: userNames[responsible] || responsible }, undefined, PLAIN))
	}
	const typeLabels = new Map<string, string>(personTypeOptions().map(o => [o.value, o.label]))
	for (const person of contract.insurancePersons ?? []) {
		const name = (person?.name ?? '').trim()
		if (name === '') continue
		const label = typeLabels.get(person.type) ?? typeLabels.get('other')
		lines.push(`${label}: ${name}`)
	}
	for (const benefit of contract.insuranceBenefits ?? []) {
		lines.push(...textLines(benefit))
	}
	lines.push(...textLines(contract.notes))
	return lines
}

export function buildMirrorRow(contract: Contract, lookups: MirrorLookups = {}): MirrorRow {
	const number = (contract.contractNumber ?? '').trim()
	const contact = contract.vendorContactUid ? lookups.contacts?.[contract.vendorContactUid] : null
	return {
		contract: [
			...textLines(contract.name),
			...(number !== '' ? [t('nc_vertragswerk', 'Nr. {number}', { number }, undefined, PLAIN)] : []),
		],
		vendor: vendorLines(contract, contact),
		scope: scopeLines(contract, lookups.userNames ?? {}),
		endDate: endDateLabel(contract),
		status: statusLabel(contract),
		interval: intervalLabel(contract.costInterval),
		amount: formatAmount(contract.cost, contract.currency),
	}
}

/**
 * Group the contracts by category, in the order of the categories in the
 * sidebar; contracts without (or with an unknown) category come last. Within a
 * group the incoming order is kept, so the PDF follows the sort of the list.
 */
export function buildMirrorGroups(contracts: Contract[], categories: Category[], lookups: MirrorLookups = {}): MirrorGroup[] {
	const known = new Set(categories.map(c => c.id))
	const groups: MirrorGroup[] = []
	for (const category of categories) {
		const members = contracts.filter(c => c.categoryId === category.id)
		if (members.length) {
			groups.push({ title: category.name, rows: members.map(c => buildMirrorRow(c, lookups)) })
		}
	}
	const rest = contracts.filter(c => c.categoryId === null || c.categoryId === undefined || !known.has(c.categoryId))
	if (rest.length) {
		groups.push({ title: t('nc_vertragswerk', 'Ohne Kategorie'), rows: rest.map(c => buildMirrorRow(c, lookups)) })
	}
	return groups
}
