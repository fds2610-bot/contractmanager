<template>
	<div class="contract-list">
		<div class="contract-list__header">
			<h2>{{ listTitle }}</h2>
			<span v-if="!loading" class="contract-list__count">{{ n('nc_vertragswerk', '%n Vertrag', '%n Verträge', contracts.length) }}</span>
			<div class="contract-list__header-actions">
				<NcActions :force-menu="true" variant="tertiary" :menu-name="activeSortLabel">
					<template #icon>
						<SortAscendingIcon v-if="sortDirection === 'asc'" :size="20" />
						<SortDescendingIcon v-else :size="20" />
					</template>
					<NcActionButton v-for="option in sortOptions"
						:key="option.key"
						:close-after-click="true"
						@click="handleSortClick(option)">
						<template #icon>
							<component :is="getSortIcon(option)" :size="20" />
						</template>
						{{ option.label }}
					</NcActionButton>
				</NcActions>
				<NcButton variant="tertiary"
					:disabled="loading || contracts.length === 0 || pdfGenerating"
					:title="t('nc_vertragswerk', 'Vertragsspiegel der angezeigten Verträge als PDF')"
					@click="openContractMirror">
					<template #icon>
						<NcLoadingIcon v-if="pdfGenerating" :size="20" />
						<FilePdfBoxIcon v-else :size="20" />
					</template>
					{{ t('nc_vertragswerk', 'Vertragsspiegel (PDF)') }}
				</NcButton>
			</div>
		</div>

		<!-- Filter als Chips (2.0.1): jeder Filter ist ein Knopf, der seine
		     Auswahl in einem Popover öffnet; aktive Filter zeigen ihren Wert und
		     lassen sich mit × einzeln entfernen. -->
		<div class="contract-list__filters" role="group" :aria-label="t('nc_vertragswerk', 'Filter')">
			<span v-for="chip in filterChips"
				:key="chip.key"
				class="filter-chip"
				:class="{ 'filter-chip--active': chip.active }">
				<NcPopover popup-role="dialog" :no-focus-trap="true">
					<template #trigger>
						<button type="button" class="filter-chip__button" :data-test="'filter-chip-' + chip.key">
							{{ chip.label }}
							<ChevronDownIcon :size="18" />
						</button>
					</template>
					<div class="filter-popover">
						<NcSelect v-if="chip.key === 'status'"
							v-model="filterStatuses"
							:multiple="true"
							:options="statusOptions"
							:placeholder="t('nc_vertragswerk', 'Status')"
							:clearable="false"
							:append-to-body="false"
							label="label"
							track-by="id"
							:reduce="option => option.id"
							input-id="filter-status"
							@update:model-value="persistFilters" />
						<NcSelect v-else-if="chip.key === 'vendor'"
							v-model="filterVendor"
							:options="vendorOptions"
							:placeholder="t('nc_vertragswerk', 'Vertragspartner')"
							:clearable="true"
							:append-to-body="false"
							input-id="filter-vendor"
							@update:model-value="persistFilters" />
						<NcSelect v-else-if="chip.key === 'responsible'"
							v-model="filterResponsible"
							:options="responsibleOptions"
							:placeholder="t('nc_vertragswerk', 'Zuständig')"
							:clearable="true"
							:append-to-body="false"
							input-id="filter-responsible"
							@update:model-value="persistFilters" />
						<NcSelect v-else-if="chip.key === 'area'"
							v-model="filterAreaId"
							:options="areaFilterOptions"
							:placeholder="t('nc_vertragswerk', 'Kategorie')"
							:clearable="true"
							:append-to-body="false"
							label="label"
							track-by="id"
							:reduce="option => option.id"
							input-id="filter-area"
							@update:model-value="persistFilters" />
						<NcSelect v-else-if="chip.key === 'type'"
							v-model="filterContractType"
							:options="contractTypeOptions"
							:placeholder="t('nc_vertragswerk', 'Befristung')"
							:clearable="true"
							:append-to-body="false"
							label="label"
							track-by="id"
							:reduce="option => option.id"
							input-id="filter-type"
							@update:model-value="persistFilters" />
						<NcSelect v-else-if="chip.key === 'deadline'"
							v-model="filterDeadlineWithin"
							:options="deadlineWithinOptions"
							:placeholder="t('nc_vertragswerk', 'Frist / Ende in')"
							:clearable="true"
							:append-to-body="false"
							label="label"
							track-by="id"
							:reduce="option => option.id"
							input-id="filter-deadline-within"
							@update:model-value="persistFilters" />
						<NcTextField v-else-if="chip.key === 'cost'"
							v-model="filterMinMonthlyCostText"
							class="filter-min-cost"
							:label="t('nc_vertragswerk', 'Monatskosten ab')"
							inputmode="decimal"
							:error="minMonthlyCostInvalid"
							:helper-text="minMonthlyCostInvalid ? t('nc_vertragswerk', 'Ungültiger Betrag') : ''"
							:show-trailing-button="filterMinMonthlyCostText !== ''"
							trailing-button-icon="close"
							@trailing-button-click="filterMinMonthlyCostText = ''" />
					</div>
				</NcPopover>
				<button v-if="chip.active"
					type="button"
					class="filter-chip__clear"
					:aria-label="t('nc_vertragswerk', 'Filter „{name}“ entfernen', { name: chip.name })"
					:title="t('nc_vertragswerk', 'Filter „{name}“ entfernen', { name: chip.name })"
					@click="clearFilter(chip.key)">
					<CloseIcon :size="16" />
				</button>
			</span>
			<!-- Admin-Diagnose (#299): ein Umschalter, keine Auswahl. -->
			<span v-if="isAdmin" class="filter-chip" :class="{ 'filter-chip--active': filterOwnerMissing }">
				<button type="button"
					class="filter-chip__button"
					:aria-pressed="filterOwnerMissing ? 'true' : 'false'"
					@click="onOwnerMissingChange(!filterOwnerMissing)">
					<CheckIcon v-if="filterOwnerMissing" :size="16" />
					{{ t('nc_vertragswerk', 'Ohne aktiven Eigentümer') }}
				</button>
			</span>
			<NcButton v-if="hasActiveFilters"
				variant="tertiary"
				@click="resetFilters">
				<template #icon>
					<FilterOffIcon :size="20" />
				</template>
				{{ t('nc_vertragswerk', 'Zurücksetzen') }}
			</NcButton>
		</div>

		<div v-if="!loading && allContracts.length && kpiScopeActive" class="kpi-scope">
			<FilterIcon :size="14" /> {{ kpiScopeLabel }}
		</div>
		<div v-if="!loading && allContracts.length" class="contract-kpis">
			<div class="kpi">
				<div class="kpi__lab">
					<FileDocumentIcon :size="16" /> {{ t('nc_vertragswerk', 'Aktive Verträge') }}
				</div>
				<div class="kpi__num">
					{{ kpiActiveCount }}
				</div>
				<div class="kpi__sub">
					{{ kpiTypeSub }}
				</div>
			</div>
			<div class="kpi">
				<div class="kpi__lab">
					<CashMultipleIcon :size="16" /> {{ t('nc_vertragswerk', 'Monatliche Kosten') }}
				</div>
				<div class="kpi__num">
					{{ kpiMonthlyLabel }}
				</div>
				<div class="kpi__sub">
					{{ kpiMonthlySub }}
				</div>
			</div>
			<button type="button"
				class="kpi kpi--button"
				:class="{ 'kpi--warn': kpiEndingSoon > 0, 'kpi--pressed': endingSoonOnly }"
				:aria-pressed="endingSoonOnly ? 'true' : 'false'"
				data-test="kpi-ending-soon"
				@click="toggleEndingSoonOnly">
				<span class="kpi__lab">
					<BellRingIcon :size="16" /> {{ t('nc_vertragswerk', 'Kündigungsfrist endet') }}
				</span>
				<span class="kpi__num">
					{{ kpiEndingSoon }}
				</span>
				<span class="kpi__sub">
					{{ endingSoonOnly
						? t('nc_vertragswerk', 'Filter aktiv · erneut klicken zum Aufheben')
						: t('nc_vertragswerk', 'Klicken zeigt nur diese Verträge') }}
				</span>
			</button>
			<component :is="kpiNextDeadline ? 'button' : 'div'"
				:type="kpiNextDeadline ? 'button' : undefined"
				class="kpi"
				:class="{ 'kpi--button': kpiNextDeadline }"
				data-test="kpi-next-deadline"
				@click="kpiNextDeadline && selectContract(kpiNextDeadline.contract.id)">
				<span class="kpi__lab">
					<CalendarIcon :size="16" /> {{ t('nc_vertragswerk', 'Nächste Frist') }}
				</span>
				<span class="kpi__num">
					{{ kpiNextDeadline ? kpiNextDeadline.label : '–' }}
				</span>
				<span class="kpi__sub">
					{{ kpiNextDeadline ? kpiNextDeadline.contract.name : t('nc_vertragswerk', 'keine offene Frist') }}
				</span>
			</component>
		</div>

		<div v-if="loading" class="contract-list__loading">
			<NcLoadingIcon :size="44" />
		</div>

		<!-- Leerzustand: solange Filter, Kategorie oder Suche greifen, darf hier
		     nicht "noch keinen Vertrag angelegt" stehen (#332). -->
		<NcEmptyContent v-else-if="contracts.length === 0"
			:name="viewIsNarrowed
				? t('nc_vertragswerk', 'Kein Vertrag passt zur aktuellen Auswahl')
				: t('nc_vertragswerk', 'Keine Verträge')"
			:description="viewIsNarrowed
				? t('nc_vertragswerk', 'Filter, Vertragsart, Kategorie oder Suche schränken die Liste ein. Heben Sie die Einschränkung auf, um alle Verträge zu sehen.')
				: t('nc_vertragswerk', 'Erstellen Sie Ihren ersten Vertrag, um zu beginnen.')">
			<template #icon>
				<FilterOffIcon v-if="viewIsNarrowed" :size="64" />
				<FileDocumentIcon v-else :size="64" />
			</template>
			<template #action>
				<NcButton v-if="hasActiveFilters" variant="primary" @click="resetFilters">
					<template #icon>
						<FilterOffIcon :size="20" />
					</template>
					{{ t('nc_vertragswerk', 'Filter zurücksetzen') }}
				</NcButton>
				<NcButton v-else-if="!viewIsNarrowed && canEdit" variant="primary" @click="showCreateForm = true">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
					{{ t('nc_vertragswerk', 'Neuer Vertrag') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<div v-else class="contract-list__table">
			<div class="contract-list__thead">
				<span class="col-avatar" aria-hidden="true" />
				<span class="col-name">{{ t('nc_vertragswerk', 'Vertrag') }}</span>
				<span class="col-status">{{ t('nc_vertragswerk', 'Status') }}</span>
				<span class="col-cost">{{ t('nc_vertragswerk', 'Kosten') }}</span>
				<span class="col-deadline">{{ t('nc_vertragswerk', 'Frist / Ende') }}</span>
				<span class="col-responsible" :title="t('nc_vertragswerk', 'Zuständig')">
					<AccountIcon :size="18" />
				</span>
				<span class="col-actions" aria-hidden="true" />
			</div>
			<div class="contract-list__items">
				<ContractListItem v-for="contract in contracts"
					:key="contract.id"
					:contract="contract"
					:default-reminder-days="defaultReminderDays"
					:selected="contract.id === selectedContractId"
					@select="selectContract(contract.id)"
					@edit="handleEdit"
					@duplicate="handleDuplicate"
					@view="handleView"
					@archive="handleArchive" />
			</div>
		</div>

		<ContractForm :show="showCreateForm || showEditForm"
			:contract="editingContract"
			:loading="formLoading"
			@close="closeForm"
			@submit="handleFormSubmit" />

		<ContractForm :show="showViewForm"
			:contract="viewingContract"
			:read-only="true"
			@close="closeForm" />

		<NcDialog v-if="showArchiveDialog"
			:name="t('nc_vertragswerk', 'Vertrag archivieren')"
			@close="showArchiveDialog = false">
			<p>{{ t('nc_vertragswerk', 'Vertrag "{name}" wirklich archivieren?', { name: archivingContract ? archivingContract.name : '' }) }}</p>
			<template #actions>
				<NcButton @click="showArchiveDialog = false">
					{{ t('nc_vertragswerk', 'Abbrechen') }}
				</NcButton>
				<NcButton variant="warning" @click="confirmArchive">
					{{ t('nc_vertragswerk', 'Archivieren') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>
import { mapState, mapActions } from 'pinia'
import { useContractsStore } from '../store/contracts'
import { useCategoriesStore } from '../store/categories'
import { useAreasStore } from '../store/areas'
import { loadState } from '@nextcloud/initial-state'
import { getCanonicalLocale } from '@nextcloud/l10n'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import FileDocumentIcon from 'vue-material-design-icons/FileDocumentOutline.vue'
import SortAscendingIcon from 'vue-material-design-icons/SortAscending.vue'
import SortDescendingIcon from 'vue-material-design-icons/SortDescending.vue'
import CircleSmallIcon from 'vue-material-design-icons/CircleSmall.vue'
import FilterIcon from 'vue-material-design-icons/FilterOutline.vue'
import FilterOffIcon from 'vue-material-design-icons/FilterOffOutline.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CalendarIcon from 'vue-material-design-icons/CalendarOutline.vue'
import AccountIcon from 'vue-material-design-icons/AccountOutline.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import CashMultipleIcon from 'vue-material-design-icons/CashMultiple.vue'
import BellRingIcon from 'vue-material-design-icons/BellRingOutline.vue'
import FilePdfBoxIcon from 'vue-material-design-icons/FilePdfBox.vue'
import ContractListItem from '../components/ContractListItem.vue'
import { getDeadlineInfo, getEffectiveEndDate } from '../utils/periodUtils.js'
import { DEFAULT_REMINDER_DAYS_1, isEndingSoon, isPlanned } from '../utils/contractStatus'
import { contractForDuplicate } from '../utils/contractDuplicate'
import { hasMonthlyEquivalent, monthlyCost } from '../utils/costInterval'
import { normalizeCostInput } from '../utils/costFormat'
import { DEADLINE_WITHIN_OPTIONS, hasMonthlyCostAtLeast, isDeadlineWithin } from '../utils/listFilters'
import ContractForm from '../components/ContractForm.vue'
import SettingsService from '../services/SettingsService'
import ContactService from '../services/ContactService'
import ContractService from '../services/ContractService'
import { getCurrentUser } from '@nextcloud/auth'
import { buildMirrorSections } from '../utils/contractMirror'
import { formatDate } from '../utils/dateUtils.js'
import { showInfo, showError } from '@nextcloud/dialogs'
import { useUiStore } from '../store/ui'
import { daysUntil } from '../utils/listDisplay'

export default {
	name: 'ContractList',
	components: {
		NcActions,
		NcActionButton,
		NcButton,
		NcDialog,
		NcLoadingIcon,
		NcEmptyContent,
		NcSelect,
		NcTextField,
		NcPopover,
		ChevronDownIcon,
		CheckIcon,
		CalendarIcon,
		AccountIcon,
		PlusIcon,
		FileDocumentIcon,
		SortAscendingIcon,
		SortDescendingIcon,
		CircleSmallIcon,
		FilterIcon,
		FilterOffIcon,
		CloseIcon,
		CashMultipleIcon,
		BellRingIcon,
		FilePdfBoxIcon,
		ContractListItem,
		ContractForm,
	},
	props: {
		categoryFilter: {
			type: [Number, String],
			default: null,
		},
		searchQuery: {
			type: String,
			default: '',
		},
	},
	data() {
		const defaultPrefs = {
			sortBy: 'endDate',
			sortDirection: 'asc',
			filters: { vendor: '', statuses: ['active', 'cancelled', 'ended'], contractType: '' },
		}
		const prefs = loadState('nc_vertragswerk', 'userPreferences', defaultPrefs)
		const filters = prefs.filters || defaultPrefs.filters
		return {
			showCreateForm: false,
			showEditForm: false,
			showViewForm: false,
			showArchiveDialog: false,
			editingContract: null,
			viewingContract: null,
			archivingContract: null,
			formLoading: false,
			pdfGenerating: false,
			sortBy: prefs.sortBy,
			sortDirection: prefs.sortDirection,
			sortOptions: [
				{ key: 'endDate', label: t('nc_vertragswerk', 'Enddatum'), defaultDirection: 'asc' },
				{ key: 'name', label: t('nc_vertragswerk', 'Name'), defaultDirection: 'asc' },
				{ key: 'updatedAt', label: t('nc_vertragswerk', 'Zuletzt geändert'), defaultDirection: 'desc' },
				{ key: 'cost', label: t('nc_vertragswerk', 'Kosten'), defaultDirection: 'desc' },
				{ key: 'cancellationDeadline', label: t('nc_vertragswerk', 'Frist / Ende'), defaultDirection: 'asc' },
			],
			filterVendor: filters.vendor || null,
			filterStatuses: filters.statuses || [],
			filterContractType: filters.contractType || null,
			filterResponsible: filters.responsible || null,
			filterAreaId: filters.areaId || null,
			filterDeadlineWithin: filters.deadlineWithin || null,
			filterMinMonthlyCostText: typeof filters.minMonthlyCost === 'number' ? String(filters.minMonthlyCost) : '',
			minMonthlyCostSaveTimer: null,
			// Admin-only (#299): contracts whose effective owner no longer has
			// an account. Bewusst nicht aus den gespeicherten Filtern gelesen
			// und nie mitgespeichert (#332): ein Diagnosewerkzeug, das im
			// Normalbetrieb null Treffer liefert, darf nicht monatelang stumm
			// weiterfiltern. Gilt nur fuer die laufende Ansicht.
			filterOwnerMissing: false,
			// Status selection before the „Kündigungsfrist endet" tile narrowed it
			// to ending_soon (2.0.1); null while that tile filter is off.
			statusesBeforeEndingSoon: null,
			// Window the badge / filter uses for "Kündigungsfrist endet". Defaults to the
			// constant in utils/contractStatus; gets overridden once the admin
			// setting comes back from the user-settings endpoint.
			defaultReminderDays: DEFAULT_REMINDER_DAYS_1,
			statusOptions: [
				{ id: 'planned', label: t('nc_vertragswerk', 'Geplant') },
				{ id: 'active', label: t('nc_vertragswerk', 'Aktiv') },
				{ id: 'ending_soon', label: t('nc_vertragswerk', 'Kündigungsfrist endet') },
				{ id: 'cancelled', label: t('nc_vertragswerk', 'Gekündigt') },
				{ id: 'ended', label: t('nc_vertragswerk', 'Abgelaufen') },
			],
			contractTypeOptions: [
				{ id: 'fixed', label: t('nc_vertragswerk', 'Festlaufzeit') },
				{ id: 'auto_renewal', label: t('nc_vertragswerk', 'Automatische Verlängerung') },
			],
		}
	},
	computed: {
		...mapState(useContractsStore, {
			allContracts: 'allContracts',
			loading: 'isLoading',
			canEdit: 'canEdit',
			isAdmin: 'isAdmin',
		}),
		...mapState(useCategoriesStore, ['allCategories']),
		...mapState(useAreasStore, ['allAreas']),
		...mapState(useUiStore, ['selectedContractId', 'formRequest']),
		areaFilterOptions() {
			return this.allAreas.map(a => ({ id: a.id, label: a.name }))
		},
		vendorOptions() {
			const vendors = this.allContracts
				.map(c => c.vendor)
				.filter(v => v && v.trim() !== '')
			return [...new Set(vendors)].sort((a, b) => a.localeCompare(b))
		},
		responsibleOptions() {
			const users = this.allContracts
				.map(c => c.responsibleUser)
				.filter(u => u && u.trim() !== '')
			return [...new Set(users)].sort((a, b) => a.localeCompare(b))
		},
		activeSortLabel() {
			const option = this.sortOptions.find(o => o.key === this.sortBy)
			return option ? option.label : ''
		},
		deadlineWithinOptions() {
			return DEADLINE_WITHIN_OPTIONS.map(days => ({
				id: days,
				label: t('nc_vertragswerk', 'Frist / Ende in {days} Tagen', { days }),
			}))
		},
		filterMinMonthlyCost() {
			const normalized = normalizeCostInput(this.filterMinMonthlyCostText)
			if (normalized === '') return null
			const amount = Number(normalized)
			return Number.isFinite(amount) && amount >= 0 ? amount : null
		},
		minMonthlyCostInvalid() {
			return this.filterMinMonthlyCostText.trim() !== '' && this.filterMinMonthlyCost === null
		},
		hasActiveFilters() {
			if (this.filterVendor) return true
			if (this.filterStatuses.length > 0) return true
			if (this.filterContractType) return true
			if (this.filterResponsible) return true
			if (this.filterAreaId) return true
			if (this.filterDeadlineWithin) return true
			if (this.filterMinMonthlyCost !== null) return true
			if (this.filterOwnerMissing) return true
			return false
		},
		// --- KPI-Kennzahlen: beziehen sich auf die aktuell ANGEZEIGTE (gefilterte)
		// Menge, damit Kategorie-Auswahl, Filter und Suche durchschlagen. ---
		kpiBaseContracts() {
			return this.contracts
		},
		// Ob die Ansicht ueberhaupt eingeschraenkt ist: Filterleiste, Kategorie
		// in der Seitenleiste oder Suche (#332). Entscheidet zusaetzlich, was
		// der Leerzustand sagt - "noch keinen Vertrag angelegt" waere sonst
		// gelogen, sobald eine dieser drei Einschraenkungen greift.
		viewIsNarrowed() {
			return this.hasActiveFilters
				|| this.categoryFilter !== null
				|| this.searchQuery.trim() !== ''
		},
		kpiScopeActive() {
			return this.viewIsNarrowed
		},
		kpiScopeLabel() {
			if (this.categoryFilter === 'uncategorized') {
				return t('nc_vertragswerk', 'Kennzahlen für Verträge ohne Vertragsart')
			}
			if (this.categoryFilter !== null) {
				const cat = this.allCategories.find(c => c.id === this.categoryFilter)
				if (cat) {
					return t('nc_vertragswerk', 'Kennzahlen für Vertragsart „{name}“', { name: cat.name })
				}
			}
			return t('nc_vertragswerk', 'Kennzahlen für die gefilterte Ansicht')
		},
		kpiActiveCount() {
			// Planned contracts keep the stored status "active" but haven't started
			// yet (#109) — they show as „Geplant", so they must not be counted as
			// active here or the KPI would contradict the list.
			return this.kpiBaseContracts.filter(c => c.status === 'active' && !isPlanned(c)).length
		},
		kpiTypeSub() {
			const auto = this.kpiBaseContracts.filter(c => c.status === 'active' && !isPlanned(c) && c.contractType === 'auto_renewal').length
			const rest = this.kpiActiveCount - auto
			return t('nc_vertragswerk', '{auto} mit autom. Verlängerung · {rest} weitere', { auto, rest })
		},
		kpiEndingSoon() {
			return this.kpiBaseContracts.filter(c => isEndingSoon(c, this.defaultReminderDays)).length
		},
		// Verträge der Ansicht, die noch laufende Kosten verursachen: aktive UND
		// gekündigte, deren Laufzeit noch nicht abgelaufen ist. Einmalzahlungen,
		// beendete und archivierte zählen nicht.
		kpiCostContracts() {
			const today = new Date()
			today.setHours(0, 0, 0, 0)
			return this.kpiBaseContracts.filter(c => {
				if (c.status === 'ended' || c.status === 'archived') return false
				// Planned contracts (#109) have not started yet — they show as
				// „Geplant" and are excluded from the active count, so they must
				// not inflate the „laufende Kosten" sum either.
				if (isPlanned(c)) return false
				if (!hasMonthlyEquivalent(c.costInterval)) return false
				if (!Number.isFinite(parseFloat(c.cost))) return false
				// Use the EFFECTIVE end date: getEffectiveEndDate rolls auto_renewal
				// contracts forward and respects cancelledTo. The old code used the
				// raw endDate, which silently dropped renewing contracts entered with
				// a past original end date from the cost sum (#215).
				const end = getEffectiveEndDate(c.endDate, c.contractType, c.renewalPeriod, { status: c.status, cancelledTo: c.cancelledTo })
				if (end) {
					end.setHours(0, 0, 0, 0)
					if (end < today) return false
				}
				return true
			})
		},
		kpiLeadCurrency() {
			return 'EUR'
		},
		kpiMonthlyTotal() {
			let sum = 0
			this.kpiCostContracts.forEach(c => {
				if ((c.currency || 'EUR') !== this.kpiLeadCurrency) return
				sum += monthlyCost(parseFloat(c.cost), c.costInterval)
			})
			return sum
		},
		kpiForeignCurrencyCount() {
			return this.kpiCostContracts.filter(c => (c.currency || 'EUR') !== this.kpiLeadCurrency).length
		},
		kpiAmountTypeMixed() {
			const inLeadCurrency = this.kpiCostContracts.filter(c => (c.currency || 'EUR') === this.kpiLeadCurrency)
			const hasNetto = inLeadCurrency.some(c => (c.amountType || 'netto') === 'netto')
			const hasBrutto = inLeadCurrency.some(c => (c.amountType || 'netto') === 'brutto')
			return hasNetto && hasBrutto
		},
		kpiMonthlyLabel() {
			// "ca." signals deliberately that this is a rough monthly estimate, not
			// a cent-exact figure (netto/brutto mixing, part-month expiry, etc. — #215).
			const formatted = new Intl.NumberFormat(getCanonicalLocale(), { style: 'currency', currency: this.kpiLeadCurrency }).format(this.kpiMonthlyTotal)
			return t('nc_vertragswerk', 'ca. {amount}', { amount: formatted })
		},
		kpiMonthlySub() {
			const parts = [t('nc_vertragswerk', 'Grobe Orientierung · laufende Verträge auf den Monat')]
			if (this.kpiAmountTypeMixed) {
				parts.push(t('nc_vertragswerk', 'netto und brutto gemischt'))
			}
			if (this.kpiForeignCurrencyCount > 0) {
				parts.push(t('nc_vertragswerk', '+ {n} in anderer Währung', { n: this.kpiForeignCurrencyCount }))
			}
			return parts.join(' · ')
		},
		listTitle() {
			if (this.categoryFilter === null) {
				return t('nc_vertragswerk', 'Alle Verträge')
			}
			if (this.categoryFilter === 'uncategorized') {
				return t('nc_vertragswerk', 'Ohne Vertragsart')
			}
			return this.allCategories.find(c => c.id === this.categoryFilter)?.name ?? t('nc_vertragswerk', 'Verträge')
		},
		endingSoonOnly() {
			return this.filterStatuses.length === 1 && this.filterStatuses[0] === 'ending_soon'
		},
		statusFilterLabel() {
			if (this.endingSoonOnly) {
				return t('nc_vertragswerk', 'Kündigungsfrist endet')
			}
			return this.filterStatuses
				.map(id => this.statusOptions.find(o => o.id === id)?.label)
				.filter(Boolean)
				.join(', ')
		},
		// One chip per filter (2.0.1). `name` is the filter, `label` what the
		// chip shows: the name alone, or name and chosen value.
		filterChips() {
			const chip = (key, name, value) => ({
				key,
				name,
				active: value !== null && value !== '' && value !== undefined,
				label: value !== null && value !== '' && value !== undefined ? name + ': ' + value : name,
			})
			const optionLabel = (options, id) => options.find(o => o.id === id)?.label ?? null
			const cost = this.filterMinMonthlyCost === null
				? null
				: new Intl.NumberFormat(getCanonicalLocale(), { style: 'currency', currency: 'EUR' }).format(this.filterMinMonthlyCost)
			return [
				chip('status', t('nc_vertragswerk', 'Status'), this.filterStatuses.length ? this.statusFilterLabel : null),
				chip('vendor', t('nc_vertragswerk', 'Vertragspartner'), this.filterVendor),
				chip('responsible', t('nc_vertragswerk', 'Zuständig'), this.filterResponsible),
				chip('area', t('nc_vertragswerk', 'Kategorie'), this.filterAreaId ? optionLabel(this.areaFilterOptions, this.filterAreaId) : null),
				chip('type', t('nc_vertragswerk', 'Befristung'), this.filterContractType ? optionLabel(this.contractTypeOptions, this.filterContractType) : null),
				chip('deadline', t('nc_vertragswerk', 'Frist / Ende in'), this.filterDeadlineWithin ? t('nc_vertragswerk', '{days} Tagen', { days: this.filterDeadlineWithin }) : null),
				chip('cost', t('nc_vertragswerk', 'Monatskosten ab'), cost),
			]
		},
		// Earliest upcoming date in the „Frist / Ende" column among the shown
		// contracts; the tile opens that contract in the sidebar.
		kpiNextDeadline() {
			let best = null
			for (const c of this.contracts) {
				const info = getDeadlineInfo(c)
				if (!info.date || info.labelKey === 'cancelledTo') continue
				const days = daysUntil(info.date)
				if (days < 0) continue
				if (!best || days < best.days) {
					best = { days, date: info.date, contract: c }
				}
			}
			if (!best) return null
			return {
				contract: best.contract,
				label: best.date.toLocaleDateString(getCanonicalLocale(), { day: '2-digit', month: '2-digit' }),
			}
		},
		contracts() {
			let filtered = this.allContracts.filter(c => c.status !== 'archived')

			// Volltextsuche (Sidebar-Suchfeld)
			if (this.searchQuery.trim()) {
				const query = this.searchQuery.trim().toLowerCase()
				filtered = filtered.filter(c => {
					return (c.name || '').toLowerCase().includes(query)
						|| (c.vendor || '').toLowerCase().includes(query)
						|| (c.notes || '').toLowerCase().includes(query)
						|| (c.customField1 || '').toLowerCase().includes(query)
						|| (c.customField2 || '').toLowerCase().includes(query)
						|| (c.customField3 || '').toLowerCase().includes(query)
						|| (c.contractNumber || '').toLowerCase().includes(query)
						|| (c.customerNumber || '').toLowerCase().includes(query)
						|| (c.responsibleUser || '').toLowerCase().includes(query)
				})
			}

			// Kategorie-Filter (Sidebar)
			if (this.categoryFilter === 'uncategorized') {
				filtered = filtered.filter(c => !c.categoryId)
			} else if (this.categoryFilter !== null) {
				filtered = filtered.filter(c => c.categoryId === this.categoryFilter)
			}

			// Status-Filter (leer = kein Filter = alle anzeigen).
			// "ending_soon" and "planned" are virtual options: they don't map to a
			// stored status but to a derived predicate over active contracts —
			// see utils/contractStatus.isEndingSoon / isPlanned. Planned contracts
			// keep the stored status "active", so they also match the "active"
			// option, mirroring how ending_soon behaves.
			if (this.filterStatuses.length > 0) {
				const wantsEndingSoon = this.filterStatuses.includes('ending_soon')
				const wantsPlanned = this.filterStatuses.includes('planned')
				const realStatuses = this.filterStatuses.filter(id => id !== 'ending_soon' && id !== 'planned')
				const reminderDays = this.defaultReminderDays
				filtered = filtered.filter(c => {
					if (wantsEndingSoon && isEndingSoon(c, reminderDays)) return true
					if (wantsPlanned && isPlanned(c)) return true
					return realStatuses.includes(c.status)
				})
			}

			// Vertragspartner-Filter
			if (this.filterVendor) {
				filtered = filtered.filter(c => c.vendor === this.filterVendor)
			}

			// Vertragstyp-Filter
			if (this.filterContractType) {
				filtered = filtered.filter(c => c.contractType === this.filterContractType)
			}

			// Zuständig-Filter
			if (this.filterResponsible) {
				filtered = filtered.filter(c => c.responsibleUser === this.filterResponsible)
			}

			// Kategorie (#7)
			if (this.filterAreaId) {
				filtered = filtered.filter(c => c.areaId === this.filterAreaId)
			}

			if (this.filterDeadlineWithin) {
				filtered = filtered.filter(c => isDeadlineWithin(c, this.filterDeadlineWithin))
			}

			if (this.filterMinMonthlyCost !== null) {
				filtered = filtered.filter(c => hasMonthlyCostAtLeast(c, this.filterMinMonthlyCost))
			}

			// Verwaiste Verträge (nur Admins, #299). isAdmin wird zusätzlich
			// geprüft, damit ein noch gespeicherter Filterwert nach dem Entzug
			// der Admin-Rolle nicht zu einer leeren, unerklärten Liste führt -
			// die Checkbox ist dann ja ausgeblendet und nicht mehr abwählbar.
			if (this.filterOwnerMissing && this.isAdmin) {
				filtered = filtered.filter(c => c.ownerMissing)
			}

			return this.sortContracts(filtered)
		},
	},
	watch: {
		// „Neuer Vertrag" in the navigation and „Bearbeiten" in the sidebar
		// ask for the form through the UI store (2.0.1).
		formRequest(request) {
			if (!request) return
			if (request.kind === 'create') {
				this.editingContract = null
				this.showCreateForm = true
			} else if (request.kind === 'edit' && request.contract) {
				this.handleEdit(request.contract)
			} else if (request.kind === 'duplicate' && request.contract) {
				this.handleDuplicate(request.contract)
			} else if (request.kind === 'view' && request.contract) {
				this.handleView(request.contract)
			}
		},
		// A contract that drops out of the list (filter, archive, trash) must
		// not stay open in the sidebar.
		contracts(list) {
			if (this.selectedContractId !== null && !list.some(c => c.id === this.selectedContractId)) {
				this.selectContract(null)
			}
		},
		// Typing must not save on every keystroke.
		filterMinMonthlyCost() {
			clearTimeout(this.minMonthlyCostSaveTimer)
			this.minMonthlyCostSaveTimer = setTimeout(() => this.persistFilters(), 600)
		},
	},
	beforeUnmount() {
		clearTimeout(this.minMonthlyCostSaveTimer)
	},
	created() {
		// Wait for contracts AND permissions before resolving a deep link, so the
		// edit-vs-view decision sees the real canEdit value (not the default false).
		Promise.all([this.fetchContracts(), this.fetchPermissions()]).then(() => this.handleDeepLink())
		this.loadReminderWindow()
		this.fetchCategories()
		this.fetchAreas().catch(e => console.debug('Failed to load areas:', e))
	},
	methods: {
		...mapActions(useContractsStore, ['fetchContracts', 'fetchPermissions', 'createContract', 'updateContract', 'archiveContract']),
		...mapActions(useCategoriesStore, ['fetchCategories']),
		...mapActions(useAreasStore, ['fetchAreas']),
		...mapActions(useUiStore, ['selectContract', 'setReminderDays']),

		async loadReminderWindow() {
			try {
				const settings = await SettingsService.getUserSettings()
				const days = Number(settings.reminderDays1)
				if (Number.isFinite(days) && days > 0) {
					this.defaultReminderDays = days
					this.setReminderDays(days)
				}
			} catch (e) {
				// Falls die Settings nicht erreichbar sind, bleibt der Default
				// (DEFAULT_REMINDER_DAYS_1) bestehen — kein Blocker für die Liste.
				console.debug('Failed to load reminder window setting:', e)
			}
		},

		handleEdit(contract) {
			this.editingContract = contract
			this.showEditForm = true
		},

		handleDuplicate(contract) {
			// Laufzeit- und Kuendigungsdaten sowie der Archiv-Zustand gehoeren
			// zum Original, nicht zur Kopie (#307).
			this.editingContract = contractForDuplicate(contract, t('nc_vertragswerk', 'Kopie'))
			this.showCreateForm = true
		},

		handleView(contract) {
			this.viewingContract = contract
			this.showViewForm = true
		},

		// Open a specific contract when arriving from a reminder email
		// (…/apps/nc_vertragswerk/?contract=ID). Read once on load; the param is
		// stripped afterwards so a reload doesn't reopen the contract.
		handleDeepLink() {
			const params = new URLSearchParams(window.location.search)
			const idParam = params.get('contract')
			if (!idParam) {
				return
			}
			params.delete('contract')
			const query = params.toString()
			window.history.replaceState({}, '', window.location.pathname + (query ? '?' + query : ''))

			const id = parseInt(idParam, 10)
			const contract = Number.isNaN(id) ? null : this.allContracts.find(c => c.id === id)
			if (!contract) {
				showInfo(t('nc_vertragswerk', 'Der Vertrag aus der Erinnerung ist nicht mehr vorhanden.'))
				return
			}
			// Mirror the normal list-click behaviour: editors land in the editable
			// form (so they can act on the reminder right away), viewers get the
			// read-only details.
			this.selectContract(contract.id)
			if (this.canEdit) {
				this.handleEdit(contract)
			} else {
				this.handleView(contract)
			}
		},

		handleArchive(contract) {
			this.archivingContract = contract
			this.showArchiveDialog = true
		},

		async confirmArchive() {
			if (!this.archivingContract) return
			try {
				await this.archiveContract(this.archivingContract.id)
			} catch (error) {
				console.error('Failed to archive contract:', error)
				showError(t('nc_vertragswerk', 'Fehler beim Archivieren'))
			} finally {
				this.showArchiveDialog = false
				this.archivingContract = null
			}
		},

		closeForm() {
			this.showCreateForm = false
			this.showEditForm = false
			this.showViewForm = false
			this.editingContract = null
			this.viewingContract = null
		},

		async handleFormSubmit(data) {
			this.formLoading = true
			try {
				if (this.editingContract && this.editingContract.id) {
					await this.updateContract({
						id: this.editingContract.id,
						data,
					})
				} else {
					await this.createContract(data)
				}
				this.closeForm()
			} catch (error) {
				console.error('Failed to save contract:', error)
				showError(t('nc_vertragswerk', 'Fehler beim Speichern'))
			} finally {
				this.formLoading = false
			}
		},

		// Date used to sort the "Frist / Ende" column. Uses the SAME helper the
		// column display uses (ContractListItem.deadlineInfo) so the sort order
		// always matches what the column shows.
		cancellationSortValue(c) {
			return getDeadlineInfo(c).date
		},

		sortContracts(contracts) {
			const sorted = [...contracts]
			const dir = this.sortDirection === 'asc' ? 1 : -1

			sorted.sort((a, b) => {
				let cmp = 0
				switch (this.sortBy) {
				case 'endDate': {
					if (!a.endDate && !b.endDate) { cmp = 0; break }
					if (!a.endDate) { cmp = 1; break }
					if (!b.endDate) { cmp = -1; break }
					cmp = new Date(a.endDate).getTime() - new Date(b.endDate).getTime()
					break
				}
				case 'name':
					cmp = (a.name || '').localeCompare(b.name || '')
					break
				case 'updatedAt': {
					const updA = a.updatedAt ? new Date(a.updatedAt).getTime() : 0
					const updB = b.updatedAt ? new Date(b.updatedAt).getTime() : 0
					cmp = updA - updB
					break
				}
				case 'cost':
					cmp = (parseFloat(a.cost) || 0) - (parseFloat(b.cost) || 0)
					break
				case 'cancellationDeadline': {
					// Sort by the SAME value the "Frist / Ende" column displays:
					// the cancellation deadline for auto_renewal, otherwise the
					// effective end date. Using calculateCancellationDeadline alone
					// dropped fixed contracts (no cancellationPeriod → null) to the
					// bottom even though their end date is shown in the column (#217).
					const deadlineA = this.cancellationSortValue(a)
					const deadlineB = this.cancellationSortValue(b)
					if (!deadlineA && !deadlineB) { cmp = 0; break }
					if (!deadlineA) { cmp = 1; break }
					if (!deadlineB) { cmp = -1; break }
					cmp = deadlineA.getTime() - deadlineB.getTime()
					break
				}
				default:
					cmp = 0
				}
				return cmp * dir
			})
			return sorted
		},

		handleSortClick(option) {
			if (this.sortBy === option.key) {
				this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc'
			} else {
				this.sortBy = option.key
				this.sortDirection = option.defaultDirection
			}
			this.persistSortPreference()
		},

		getSortIcon(option) {
			if (this.sortBy !== option.key) {
				return 'CircleSmallIcon'
			}
			return this.sortDirection === 'asc' ? 'SortAscendingIcon' : 'SortDescendingIcon'
		},

		async persistSortPreference() {
			try {
				await SettingsService.updateUserSettings({
					sortBy: this.sortBy,
					sortDirection: this.sortDirection,
				})
			} catch (error) {
				console.error('Failed to persist sort preference:', error)
			}
		},

		/**
		 * „Vertragsspiegel" (#489): exactly the contracts the list shows,
		 * in its order, as a PDF in a new tab. The tab is opened right away,
		 * inside the click, because browsers block window.open after an await.
		 */
		async openContractMirror() {
			if (this.pdfGenerating) return
			const contracts = this.contracts
			if (!contracts.length) return
			const tab = window.open('', '_blank')
			this.pdfGenerating = true
			try {
				const [lookups, { renderContractMirrorPdf }] = await Promise.all([
					this.loadMirrorLookups(contracts),
					import('../utils/contractMirrorPdf'),
				])
				const user = getCurrentUser()
				const blob = await renderContractMirrorPdf(
					buildMirrorSections(contracts, this.allAreas, this.allCategories, lookups),
					{ userName: user?.displayName || user?.uid || '', date: formatDate(new Date()) },
				)
				const url = URL.createObjectURL(blob)
				if (tab && !tab.closed) {
					tab.location.href = url
				} else {
					// Pop-up blocked: download instead.
					const link = document.createElement('a')
					link.href = url
					link.download = t('nc_vertragswerk', 'Vertragsspiegel') + '.pdf'
					link.click()
				}
				setTimeout(() => URL.revokeObjectURL(url), 60000)
			} catch (error) {
				console.error('Failed to create contract mirror PDF:', error)
				if (tab && !tab.closed) tab.close()
				showError(t('nc_vertragswerk', 'PDF konnte nicht erstellt werden'))
			} finally {
				this.pdfGenerating = false
			}
		},

		// Linked address book contacts and the display names of the responsible
		// users. Both are optional: whatever cannot be resolved falls back to the
		// stored value (partner name, user ID).
		async loadMirrorLookups(contracts) {
			const contactUids = [...new Set(contracts.map(c => c.vendorContactUid).filter(Boolean))]
			const userIds = [...new Set(contracts.map(c => (c.responsibleUser || '').trim()).filter(Boolean))]
			const contacts = {}
			const userNames = {}
			await Promise.all([
				...contactUids.map(async uid => {
					try {
						contacts[uid] = await ContactService.resolve(uid)
					} catch (e) {
						contacts[uid] = null
					}
				}),
				// The user search is only open to editors; viewers see the user ID.
				...(this.canEdit
					? userIds.map(async uid => {
						try {
							const match = (await ContractService.searchUsers(uid)).find(u => u.uid === uid)
							if (match) userNames[uid] = match.displayName
						} catch (e) {
							// keep the user ID
						}
					})
					: []),
			])
			return { contacts, userNames }
		},

		clearFilter(key) {
			switch (key) {
			case 'status':
				this.filterStatuses = []
				this.statusesBeforeEndingSoon = null
				break
			case 'vendor': this.filterVendor = null; break
			case 'responsible': this.filterResponsible = null; break
			case 'area': this.filterAreaId = null; break
			case 'type': this.filterContractType = null; break
			case 'deadline': this.filterDeadlineWithin = null; break
			case 'cost': this.filterMinMonthlyCostText = ''; return
			}
			this.persistFilters()
		},

		// The „Kündigungsfrist endet" tile narrows the status filter to exactly
		// those contracts; a second click restores the previous selection.
		toggleEndingSoonOnly() {
			if (this.endingSoonOnly) {
				this.filterStatuses = this.statusesBeforeEndingSoon ?? []
				this.statusesBeforeEndingSoon = null
			} else {
				this.statusesBeforeEndingSoon = [...this.filterStatuses]
				this.filterStatuses = ['ending_soon']
			}
			this.persistFilters()
		},

		resetFilters() {
			this.filterVendor = null
			this.filterStatuses = []
			this.filterContractType = null
			this.filterResponsible = null
			this.filterAreaId = null
			this.filterDeadlineWithin = null
			this.filterMinMonthlyCostText = ''
			this.filterOwnerMissing = false
			this.statusesBeforeEndingSoon = null
			this.persistFilters()
		},

		// Wirkt nur auf die laufende Ansicht, kein persistFilters (#332).
		onOwnerMissingChange(value) {
			this.filterOwnerMissing = value
		},

		async persistFilters() {
			try {
				await SettingsService.updateUserSettings({
					filters: {
						vendor: this.filterVendor || '',
						statuses: this.filterStatuses,
						contractType: this.filterContractType || '',
						responsible: this.filterResponsible || '',
						areaId: this.filterAreaId || 0,
						deadlineWithin: this.filterDeadlineWithin || 0,
						minMonthlyCost: this.filterMinMonthlyCost,
					},
				})
			} catch (error) {
				console.error('Failed to persist filters:', error)
			}
		},
	},
}
</script>

<style scoped lang="scss">
.contract-list {
	padding: 8px 20px 20px 50px;
	height: 100%;
	container: contract-list / inline-size;

	&__header {
		display: flex;
		align-items: center;
		gap: 12px;
		min-height: var(--default-clickable-area, 34px);
		margin: 4px 0 8px;

		h2 {
			margin: 0;
			font-size: 20px;
			font-weight: 600;
		}

		&-actions {
			display: flex;
			align-items: center;
			gap: 4px;
			margin-left: auto;
		}
	}

	&__count {
		color: var(--color-text-maxcontrast);
	}

	&__filters {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 8px;
		margin-bottom: 16px;
	}

	&__loading {
		display: flex;
		justify-content: center;
		align-items: center;
		height: 200px;
	}

	&__table {
		background: var(--color-main-background);
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius-container-large, 12px);
		overflow: hidden;
	}

	&__thead {
		display: grid;
		grid-template-columns: 32px minmax(0, 1fr) 170px 120px 130px 32px 96px;
		gap: 12px;
		align-items: center;
		padding: 10px 4px 10px 12px;
		border-bottom: 1px solid var(--color-border);
		font-size: 13px;
		color: var(--color-text-maxcontrast);

		.col-cost { text-align: right; }
		.col-responsible { display: flex; justify-content: center; }
	}

	&__items {
		display: block;
	}
}

/* Filter chips (2.0.1), same look as the filters of the Files app. */
.filter-chip {
	display: inline-flex;
	align-items: center;
	height: 32px;
	border: 1px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius-pill, 100px);

	&__button {
		display: inline-flex;
		align-items: center;
		gap: 4px;
		height: 100%;
		min-height: 0;
		margin: 0;
		padding: 0 8px 0 12px;
		border: none;
		border-radius: var(--border-radius-pill, 100px);
		background: transparent;
		color: var(--color-main-text);
		font-weight: normal;
		cursor: pointer;

		&:hover {
			background: var(--color-background-hover);
		}
	}

	&__clear {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 28px;
		height: 28px;
		min-height: 0;
		margin: 0 2px 0 -4px;
		padding: 0;
		border: none;
		border-radius: 50%;
		background: transparent;
		color: inherit;
		cursor: pointer;

		&:hover {
			background: var(--color-primary-element-light-hover, var(--color-background-hover));
		}
	}

	&--active {
		border-color: transparent;
		background: var(--color-primary-element-light);
		color: var(--color-primary-element-light-text);

		.filter-chip__button {
			color: inherit;
			font-weight: 600;

			&:hover {
				background: var(--color-primary-element-light-hover, var(--color-background-hover));
			}
		}
	}
}

.filter-popover {
	width: 320px;
	padding: 12px;

	/* Dropdown renders inside the popover, so it needs room below. */
	:deep(.vs__dropdown-menu) {
		position: static;
		max-height: 240px;
		margin-top: 4px;
	}
}

.kpi-scope {
	display: flex;
	align-items: center;
	gap: 5px;
	margin: 0 0 8px;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.contract-kpis {
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	gap: 12px;
	margin-bottom: 18px;

	.kpi {
		display: flex;
		flex-direction: column;
		align-items: stretch;
		min-width: 0;
		margin: 0;
		background: var(--color-main-background);
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius-container-large, 12px);
		padding: 12px 14px;
		color: var(--color-main-text);
		font-weight: normal;
		text-align: left;
	}

	.kpi--button {
		cursor: pointer;

		&:hover {
			background: var(--color-background-hover);
		}
	}

	.kpi--pressed {
		background: var(--color-primary-element-light);
		border-color: var(--color-primary-element);
	}

	.kpi__lab {
		display: flex;
		align-items: center;
		gap: 6px;
		font-size: 13px;
		color: var(--color-text-maxcontrast);
	}

	.kpi__num {
		font-size: 22px;
		font-weight: 700;
		line-height: 1.2;
		margin-top: 6px;
		font-variant-numeric: tabular-nums;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.kpi--warn {
		border-color: var(--color-warning);

		.kpi__num { color: var(--color-warning-text); }
	}

	.kpi__sub {
		font-size: 13px;
		color: var(--color-text-maxcontrast);
		margin-top: 2px;
		overflow: hidden;
		text-overflow: ellipsis;
	}
}

/* Same breakpoint as ContractListItem.vue. */
@container contract-list (max-width: 800px) {
	.contract-list__thead {
		grid-template-columns: 32px minmax(0, 1fr) 132px 100px 108px 28px 40px;
		gap: 10px;
	}

	.contract-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@container contract-list (max-width: 700px) {
	.contract-list__thead {
		grid-template-columns: minmax(0, 1fr) 100px 108px 40px;

		.col-avatar,
		.col-status,
		.col-responsible {
			display: none;
		}
	}
}

@container contract-list (max-width: 500px) {
	.contract-kpis { grid-template-columns: 1fr; }
}
</style>
