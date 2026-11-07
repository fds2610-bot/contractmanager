import { describe, expect, it } from 'vitest'
import {
	benefitsForApi,
	benefitsForForm,
	isInsuranceCategoryName,
	personsForApi,
	personsForForm,
} from './insurance'

describe('isInsuranceCategoryName', () => {
	it('matches the default category and variants', () => {
		expect(isInsuranceCategoryName('Versicherung')).toBe(true)
		expect(isInsuranceCategoryName('Versicherungen')).toBe(true)
		expect(isInsuranceCategoryName('KFZ-Versicherung')).toBe(true)
		expect(isInsuranceCategoryName('Insurance')).toBe(true)
	})

	it('rejects other categories and empty values', () => {
		expect(isInsuranceCategoryName('Software')).toBe(false)
		expect(isInsuranceCategoryName(null)).toBe(false)
		expect(isInsuranceCategoryName(undefined)).toBe(false)
	})
})

describe('personsForForm', () => {
	it('gives one empty row when there is nothing stored', () => {
		expect(personsForForm(null)).toEqual([{ type: 'policyholder', name: '', userId: null }])
		expect(personsForForm([])).toHaveLength(1)
	})

	it('keeps users and free-text names, maps unknown types to other', () => {
		expect(personsForForm([
			{ type: 'beneficiary', name: 'Anna', userId: 'anna' },
			{ type: 'bogus', name: 'Max', userId: '' },
			'garbage',
		])).toEqual([
			{ type: 'beneficiary', name: 'Anna', userId: 'anna' },
			{ type: 'other', name: 'Max', userId: null },
		])
	})
})

describe('personsForApi', () => {
	it('drops empty rows and trims names', () => {
		expect(personsForApi([
			{ type: 'policyholder', name: '  Max  ', userId: null },
			{ type: 'beneficiary', name: ' ', userId: null },
		])).toEqual([{ type: 'policyholder', name: 'Max', userId: null }])
	})

	it('sends null when nothing is left', () => {
		expect(personsForApi([{ type: 'policyholder', name: '', userId: null }])).toBeNull()
	})
})

describe('benefits', () => {
	it('round-trips through form and API', () => {
		expect(benefitsForForm(undefined)).toEqual([''])
		expect(benefitsForForm(['Glas', 3])).toEqual(['Glas'])
		expect(benefitsForApi([' Glas ', ''])).toEqual(['Glas'])
		expect(benefitsForApi([''])).toBeNull()
	})
})
