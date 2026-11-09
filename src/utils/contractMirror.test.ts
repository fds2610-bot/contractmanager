import { describe, expect, it } from 'vitest'
import { buildMirrorGroups, buildMirrorRow, textLines } from './contractMirror'
import { toWinAnsi } from './contractMirrorPdf'
import type { Contract } from '../store/contracts'

const contract = (overrides: Partial<Contract>): Contract => ({
	id: 1,
	name: 'Hausrat',
	vendor: 'Rhion',
	status: 'active',
	contractType: 'fixed',
	startDate: '2020-01-01',
	endDate: '2099-11-01',
	cost: '165',
	currency: 'EUR',
	costInterval: 'yearly',
	...overrides,
})

describe('textLines', () => {
	it('drops blank lines and trims', () => {
		expect(textLines('  a \n\n \r\nb\n')).toEqual(['a', 'b'])
		expect(textLines(null)).toEqual([])
	})
})

describe('buildMirrorRow', () => {
	it('puts the contract number below the name', () => {
		expect(buildMirrorRow(contract({ contractNumber: ' 068-68 ' })).contract).toEqual(['Hausrat', 'Nr. 068-68'])
		expect(buildMirrorRow(contract({ contractNumber: '' })).contract).toEqual(['Hausrat'])
	})

	it('builds the scope column in fixed order without empty lines', () => {
		const row = buildMirrorRow(contract({
			responsibleUser: 'max',
			insurancePersons: [
				{ type: 'policyholder', name: 'Max Muster', userId: 'max' },
				{ type: 'other', name: '  ', userId: null },
			],
			insuranceBenefits: ['Feuer', ''],
			notes: 'Zeile 1\n\n\nZeile 2',
		}), { userNames: { max: 'Max Muster' } })
		expect(row.scope).toEqual([
			'Zuständig: Max Muster',
			'Versicherungsnehmer: Max Muster',
			'Feuer',
			'Zeile 1',
			'Zeile 2',
		])
	})

	it('falls back to the user ID and leaves the scope empty when nothing is set', () => {
		expect(buildMirrorRow(contract({ responsibleUser: 'jdoe' })).scope).toEqual(['Zuständig: jdoe'])
		expect(buildMirrorRow(contract({})).scope).toEqual([])
	})

	it('adds the linked contact below the partner, without repeating it', () => {
		const row = buildMirrorRow(contract({ vendor: 'Rhion', vendorContactUid: 'c1' }), {
			contacts: {
				c1: {
					uid: 'c1',
					name: 'Anna Agent',
					organization: 'rhion',
					emails: ['anna@example.org'],
					phones: ['0711 1'],
					addresses: ['Weg 1\n12345 Ort'],
					contactsUrl: null,
				},
			},
		})
		expect(row.vendor).toEqual(['Rhion', 'Anna Agent', 'Weg 1', '12345 Ort', 'Tel. 0711 1', 'anna@example.org'])
	})

	it('shows only the partner when the contact cannot be resolved', () => {
		expect(buildMirrorRow(contract({ vendorContactUid: 'gone' }), { contacts: { gone: null } }).vendor).toEqual(['Rhion'])
	})

	it('keeps an ampersand in placeholders unescaped', () => {
		expect(buildMirrorRow(contract({ responsibleUser: 'a&b' })).scope).toEqual(['Zuständig: a&b'])
	})

	it('formats the other columns', () => {
		const row = buildMirrorRow(contract({ status: 'cancelled', cancelledTo: '2099-06-30' }))
		expect(row.status).toBe('Gekündigt')
		expect(row.interval).toBe('Jährlich')
		expect(row.amount).toMatch(/165[,.]00/)
		expect(row.endDate).toMatch(/2099/)
		expect(row.endDate).toMatch(/30/)
	})
})

describe('buildMirrorGroups', () => {
	it('groups by sidebar category order, keeps the list order, uncategorised last', () => {
		const categories = [{ id: 2, name: 'KFZ' }, { id: 1, name: 'Haftpflicht' }, { id: 3, name: 'Leer' }]
		const groups = buildMirrorGroups([
			contract({ id: 1, name: 'A', categoryId: 1 }),
			contract({ id: 2, name: 'B', categoryId: null }),
			contract({ id: 3, name: 'C', categoryId: 2 }),
			contract({ id: 4, name: 'D', categoryId: 1 }),
			contract({ id: 5, name: 'E', categoryId: 99 }),
		], categories)
		expect(groups.map(g => [g.title, g.rows.map(r => r.contract[0])])).toEqual([
			['KFZ', ['C']],
			['Haftpflicht', ['A', 'D']],
			['Ohne Kategorie', ['B', 'E']],
		])
	})
})

describe('toWinAnsi', () => {
	it('keeps German text and the euro sign, replaces what Helvetica cannot show', () => {
		expect(toWinAnsi('Größe „x“ – 64,20\u00A0€')).toBe('Größe „x“ – 64,20\u00A0€')
		expect(toWinAnsi('1\u202F000 Łódź ✓')).toBe('1 000 ?ódz ?')
	})
})
