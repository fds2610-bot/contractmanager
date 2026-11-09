import { ref } from 'vue'
import { defineStore } from 'pinia'
import type { Contract } from './contracts'
import { DEFAULT_REMINDER_DAYS_1 } from '../utils/contractStatus'

export type FormKind = 'create' | 'edit' | 'duplicate' | 'view'

export interface FormRequest {
	kind: FormKind
	contract: Contract | null
	/** Increases with every request so the same request twice still triggers. */
	seq: number
}

/**
 * State shared between the navigation, the contract list and the details
 * sidebar (2.0.1). The sidebar sits next to the list in App.vue, the
 * „Neuer Vertrag" button in the navigation, while the contract form lives in
 * ContractList; they meet here.
 */
export const useUiStore = defineStore('ui', () => {
	const selectedContractId = ref<number | null>(null)
	const formRequest = ref<FormRequest | null>(null)
	// First-reminder window in days (admin setting); ContractList loads it.
	const reminderDays = ref<number>(DEFAULT_REMINDER_DAYS_1)
	let seq = 0

	function selectContract(id: number | null) {
		selectedContractId.value = id
	}

	function requestForm(kind: FormKind, contract: Contract | null = null) {
		seq += 1
		formRequest.value = { kind, contract, seq }
	}

	function setReminderDays(days: number) {
		reminderDays.value = days
	}

	return { selectedContractId, formRequest, reminderDays, selectContract, requestForm, setReminderDays }
})
