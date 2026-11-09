import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const baseUrl = generateUrl('/apps/nc_vertragswerk/api/contacts')

export interface ContactOption {
	uid: string
	name: string
	organization: string
	email: string
}

export interface ResolvedContact {
	uid: string
	name: string
	organization: string
	emails: string[]
	phones: string[]
	addresses: string[]
	contactsUrl: string | null
}

export interface AddressBookOption {
	key: string
	name: string
	default: boolean
}

export interface TypedValue {
	type: string
	value: string
}

export interface NewAddress {
	type: string
	street: string
	postalCode: string
	city: string
	region: string
	country: string
}

/** Fields of the „Neuer Kontakt“ dialog (fds2610/contractmanager#3). */
export interface NewContact {
	firstName: string
	lastName: string
	organization: string
	department: string
	title: string
	phones: TypedValue[]
	emails: TypedValue[]
	addresses: NewAddress[]
	url: string
	birthday: string
	note: string
}

export default {
	async addressBooks(): Promise<AddressBookOption[]> {
		const response = await axios.get<AddressBookOption[]>(`${baseUrl}/addressbooks`)
		return response.data
	},

	async create(addressBookKey: string, contact: NewContact): Promise<ResolvedContact> {
		const response = await axios.post<{ contact: ResolvedContact }>(baseUrl, { addressBookKey, contact })
		return response.data.contact
	},

	async isAvailable(): Promise<boolean> {
		const response = await axios.get<{ available: boolean }>(`${baseUrl}/status`)
		return response.data.available === true
	},

	async search(query: string): Promise<ContactOption[]> {
		const response = await axios.get<ContactOption[]>(`${baseUrl}/search`, { params: { query } })
		return response.data
	},

	async resolve(uid: string): Promise<ResolvedContact | null> {
		const response = await axios.get<{ contact: ResolvedContact | null }>(`${baseUrl}/resolve`, { params: { uid } })
		return response.data.contact
	},
}
