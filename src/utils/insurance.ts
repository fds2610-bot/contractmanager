import { translate as t } from '@nextcloud/l10n'

export type InsurancePersonType = 'policyholder' | 'insured_person' | 'beneficiary' | 'co_insured' | 'payer' | 'other'

/** One person on an insurance contract: a Nextcloud user (userId set) or a free-text name. */
export interface InsurancePerson {
	type: InsurancePersonType
	name: string
	userId: string | null
}

/** Must match InsuranceData::PERSON_TYPES in lib/Service/InsuranceData.php. */
export function personTypeOptions(): Array<{ value: InsurancePersonType, label: string }> {
	return [
		{ value: 'policyholder', label: t('contractmanager', 'Versicherungsnehmer') },
		{ value: 'insured_person', label: t('contractmanager', 'Abgesicherte Person') },
		{ value: 'beneficiary', label: t('contractmanager', 'Begünstigte Person') },
		{ value: 'co_insured', label: t('contractmanager', 'Mitversicherte Person') },
		{ value: 'payer', label: t('contractmanager', 'Beitragszahler') },
		{ value: 'other', label: t('contractmanager', 'Sonstige') },
	]
}

export function emptyPerson(type: InsurancePersonType = 'policyholder'): InsurancePerson {
	return { type, name: '', userId: null }
}

/**
 * The insurance section is tied to the category by name, so it also works for
 * the default category "Versicherung" created on install and for variants
 * like "Versicherungen" or an English "Insurance".
 */
export function isInsuranceCategoryName(name: string | null | undefined): boolean {
	return /versicherung|insurance/i.test(name ?? '')
}

/** Form rows: always at least one (empty) row so there is something to type in. */
export function personsForForm(persons: unknown): InsurancePerson[] {
	const rows = Array.isArray(persons)
		? persons
			.filter((p): p is Record<string, unknown> => !!p && typeof p === 'object')
			.map(p => ({
				type: (personTypeOptions().some(o => o.value === p.type) ? p.type : 'other') as InsurancePersonType,
				name: typeof p.name === 'string' ? p.name : '',
				userId: typeof p.userId === 'string' && p.userId !== '' ? p.userId : null,
			}))
		: []
	return rows.length ? rows : [emptyPerson()]
}

export function benefitsForForm(benefits: unknown): string[] {
	const rows = Array.isArray(benefits) ? benefits.filter((b): b is string => typeof b === 'string') : []
	return rows.length ? rows : ['']
}

/** Drop empty rows; an empty list is sent as null. */
export function personsForApi(persons: InsurancePerson[]): InsurancePerson[] | null {
	const rows = persons
		.map(p => ({ ...p, name: p.name.trim() }))
		.filter(p => p.name !== '')
	return rows.length ? rows : null
}

export function benefitsForApi(benefits: string[]): string[] | null {
	const rows = benefits.map(b => b.trim()).filter(b => b !== '')
	return rows.length ? rows : null
}
