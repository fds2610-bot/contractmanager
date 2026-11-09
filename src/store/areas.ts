import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import AreaService from '../services/AreaService'

/**
 * „Kategorie" of a contract (fds2610/contractmanager#7), e.g. „Wohnen".
 * Not the same as Category, which the UI calls „Vertragsart".
 */
export interface Area {
	id: number
	name: string
	sortOrder?: number
	/** Assigned contracts, only from GET /api/areas */
	contractCount?: number
}

/** Preselected for new contracts, see AreaService::DEFAULT_AREA. */
export const DEFAULT_AREA_NAME = 'persönlich'

export const useAreasStore = defineStore('areas', () => {
	const areas = ref<Area[]>([])
	const loaded = ref(false)

	const allAreas = computed(() => areas.value)
	const defaultAreaId = computed(() => areas.value.find(a => a.name.toLowerCase() === DEFAULT_AREA_NAME)?.id ?? null)

	async function fetchAreas(): Promise<void> {
		areas.value = await AreaService.getAll()
		loaded.value = true
	}

	async function createArea(name: string): Promise<Area> {
		const area = await AreaService.create(name)
		areas.value.push(area)
		return area
	}

	async function updateArea(id: number, name: string): Promise<Area> {
		const area = await AreaService.update(id, name)
		areas.value = areas.value.map(a => (a.id === id ? area : a))
		return area
	}

	async function deleteArea(id: number): Promise<void> {
		await AreaService.delete(id)
		areas.value = areas.value.filter(a => a.id !== id)
	}

	return { areas, loaded, allAreas, defaultAreaId, fetchAreas, createArea, updateArea, deleteArea }
})
