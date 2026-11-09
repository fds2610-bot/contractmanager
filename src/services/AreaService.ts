import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import type { Area } from '../store/areas'

const baseUrl = generateUrl('/apps/nc_vertragswerk/api/areas')

/** „Kategorien" (fds2610/contractmanager#7) */
export default {
	async getAll(): Promise<Area[]> {
		const response = await axios.get<Area[]>(baseUrl)
		return response.data
	},

	async create(name: string): Promise<Area> {
		const response = await axios.post<Area>(baseUrl, { name })
		return response.data
	},

	async update(id: number, name: string): Promise<Area> {
		const response = await axios.put<Area>(`${baseUrl}/${id}`, { name })
		return response.data
	},

	async delete(id: number): Promise<void> {
		await axios.delete(`${baseUrl}/${id}`)
	},
}
