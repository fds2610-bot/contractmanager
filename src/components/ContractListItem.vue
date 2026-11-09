<template>
	<div class="contract-list-item"
		:class="{ 'contract-list-item--dragging': dragging, 'contract-list-item--selected': selected }"
		:draggable="isDraggable"
		@click="onOpen"
		@dragstart="onDragStart"
		@dragend="dragging = false">
		<NcAvatar class="contract-list-item__avatar"
			:display-name="contract.vendor || contract.name || '?'"
			:is-no-user="true"
			:disable-menu="true"
			:disable-tooltip="true"
			:size="32"
			aria-hidden="true" />
		<div class="contract-list-item__name">
			<a class="contract-name"
				href="#"
				draggable="false"
				:aria-current="selected ? 'true' : undefined"
				@click.prevent.stop="onOpen">
				{{ contract.name }}
			</a>
			<div class="contract-list-item__meta">
				<span>{{ contract.vendor }}</span>
				<template v-for="number in numbers" :key="number.title">
					<span class="sep">·</span>
					<span :title="number.title" class="contract-list-item__number">{{ number.text }}</span>
				</template>
				<template v-if="contract.renewalPeriod && contract.contractType === 'auto_renewal'">
					<span class="sep">·</span>
					<span>{{ t('nc_vertragswerk', 'Verlängerung:') }} {{ formatPeriod(contract.renewalPeriod) }}</span>
				</template>
				<template v-if="mode === 'trash' && contract.deletedAt">
					<span class="sep">·</span>
					<span>{{ t('nc_vertragswerk', 'Gelöscht:') }} {{ formatDate(contract.deletedAt) }}</span>
				</template>
				<template v-if="mode === 'trash' && contract.deletedBy">
					<span class="sep">·</span>
					<span>{{ t('nc_vertragswerk', 'Gelöscht von') }}: {{ contract.deletedBy }}</span>
				</template>
				<template v-if="showCreator && contract.createdBy">
					<span class="sep">·</span>
					<span>{{ t('nc_vertragswerk', 'Erstellt von') }}: {{ contract.createdBy }}</span>
				</template>
			</div>
		</div>
		<div class="contract-list-item__status">
			<span class="cm-chip" :class="'cm-chip--' + chip.cls" :title="chip.title || chip.label">
				<span class="cm-chip__text">{{ chip.label }}</span>
			</span>
			<span v-if="contract.isPrivate" class="cm-chip cm-chip--lock" :title="t('nc_vertragswerk', 'Privater Vertrag (nur für mich sichtbar)')">
				<LockIcon :size="14" />
				{{ t('nc_vertragswerk', 'Privat') }}
			</span>
		</div>
		<div class="contract-list-item__cost">
			{{ contract.cost ? formatCost(contract.cost, contract.currency) : '—' }}
			<span v-if="contract.cost && intervalLabel" class="contract-list-item__interval">{{ intervalLabel }}</span>
		</div>
		<div class="contract-list-item__deadline">
			<span v-if="deadlineLabel" class="contract-list-item__deadline-label">{{ deadlineLabel }}</span>
			<span>{{ deadlineDisplay }}</span>
			<span v-if="deadlineRelative"
				class="contract-list-item__deadline-rel"
				:class="'contract-list-item__deadline-rel--' + deadlineRelative.urgency">{{ deadlineRelative.text }}</span>
		</div>
		<div class="contract-list-item__responsible">
			<NcAvatar v-if="contract.responsibleUser"
				:user="contract.responsibleUser"
				:size="28"
				:hide-status="true"
				:disable-menu="true" />
		</div>
		<div class="contract-list-item__actions" @click.stop>
			<NcButton v-if="contract.contractFolder"
				class="contract-list-item__quick"
				variant="tertiary"
				:title="t('nc_vertragswerk', 'Vertragsordner öffnen')"
				@click.stop="openFolder">
				<template #icon>
					<FolderOpenIcon :size="20" />
				</template>
			</NcButton>
			<NcButton v-if="contract.mainDocument"
				class="contract-list-item__quick"
				variant="tertiary"
				:title="t('nc_vertragswerk', 'Vertragsdokument öffnen')"
				@click.stop="openDocument">
				<template #icon>
					<FileDocumentIcon :size="20" />
				</template>
			</NcButton>
			<NcActions :force-menu="true">
				<NcActionButton v-if="!contract.archived && canEdit && mode !== 'trash'"
					@click.stop="$emit('archive', contract)">
					<template #icon>
						<ArchiveIcon :size="20" />
					</template>
					{{ t('nc_vertragswerk', 'Archivieren') }}
				</NcActionButton>
				<!-- In the trash the server lists only what this user may restore
				     (#438), so the action does not depend on the editor role there:
				     a responsible viewer must be able to undo a deletion too. -->
				<NcActionButton v-if="mode === 'trash' || (contract.archived && canEdit)"
					:close-after-click="true"
					@click="$emit('restore', contract)">
					<template #icon>
						<RestoreIcon :size="20" />
					</template>
					{{ t('nc_vertragswerk', 'Wiederherstellen') }}
				</NcActionButton>
				<!-- Duplizieren und Bearbeiten gibt es nur in der Vertragsliste.
				     Im Archiv wird angesehen, wiederhergestellt und geloescht,
				     im Papierkorb wiederhergestellt und endgueltig geloescht
				     (#328). -->
				<NcActionButton v-if="canEdit && mode === 'default'"
					@click.stop="$emit('duplicate', contract)">
					<template #icon>
						<ContentDuplicate :size="20" />
					</template>
					{{ t('nc_vertragswerk', 'Duplizieren') }}
				</NcActionButton>
				<NcActionButton v-if="canEdit && mode === 'default'"
					@click.stop="$emit('edit', contract)">
					<template #icon>
						<PencilIcon :size="20" />
					</template>
					{{ t('nc_vertragswerk', 'Bearbeiten') }}
				</NcActionButton>
				<NcActionButton v-if="canEdit && mode !== 'trash'"
					class="delete-action"
					:close-after-click="true"
					@click="showDeleteDialog = true">
					<template #icon>
						<DeleteIcon :size="20" />
					</template>
					{{ t('nc_vertragswerk', 'Löschen') }}
				</NcActionButton>
				<NcActionButton v-if="mode === 'trash' && isAdmin"
					class="delete-action"
					:close-after-click="true"
					@click="$emit('deletePermanently', contract)">
					<template #icon>
						<DeleteForeverIcon :size="20" />
					</template>
					{{ t('nc_vertragswerk', 'Endgültig löschen') }}
				</NcActionButton>
			</NcActions>
		</div>

		<!-- Delete confirmation dialog -->
		<NcDialog v-if="showDeleteDialog"
			:name="t('nc_vertragswerk', 'Vertrag löschen')"
			:message="t('nc_vertragswerk', 'Der Vertrag wird in den Papierkorb verschoben und nach 30 Tagen automatisch gelöscht. Bis dahin kann er wiederhergestellt werden.')"
			:buttons="deleteDialogButtons"
			@update:open="showDeleteDialog = $event" />
	</div>
</template>

<script>
import { mapState, mapActions } from 'pinia'
import { useContractsStore } from '../store/contracts'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import ArchiveIcon from 'vue-material-design-icons/ArchiveOutline.vue'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import PencilIcon from 'vue-material-design-icons/PencilOutline.vue'
import DeleteIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import DeleteForeverIcon from 'vue-material-design-icons/DeleteForeverOutline.vue'
import LockIcon from 'vue-material-design-icons/LockOutline.vue'
import FileDocumentIcon from 'vue-material-design-icons/FileDocumentOutline.vue'
import FolderOpenIcon from 'vue-material-design-icons/FolderOpenOutline.vue'
import ContentDuplicate from 'vue-material-design-icons/ContentDuplicate.vue'
import { generateUrl } from '@nextcloud/router'
import { getCanonicalLocale } from '@nextcloud/l10n'
import { formatDate } from '../utils/dateUtils.js'
import { formatPeriod, getDeadlineInfo } from '../utils/periodUtils.js'
import { daysUntil, deadlineUrgency, intervalPerLabel, relativeDays, statusChip } from '../utils/listDisplay'
import { DEFAULT_REMINDER_DAYS_1 } from '../utils/contractStatus'
import { openDocumentInNewTab } from '../utils/documentUtils.js'
import { numberLines } from '../utils/contractMirror'
import { CONTRACT_DND_TYPE } from '../utils/categoryDrop'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'ContractListItem',
	components: {
		NcActions,
		NcActionButton,
		NcButton,
		NcDialog,
		NcAvatar,
		ArchiveIcon,
		RestoreIcon,
		PencilIcon,
		DeleteIcon,
		DeleteForeverIcon,
		LockIcon,
		FileDocumentIcon,
		FolderOpenIcon,
		ContentDuplicate,
	},
	props: {
		contract: {
			type: Object,
			required: true,
		},
		showCreator: {
			type: Boolean,
			default: false,
		},
		mode: {
			type: String,
			default: 'default',
		},
		// First-reminder window in days (admin setting). When omitted the
		// helper falls back to its built-in default.
		defaultReminderDays: {
			type: Number,
			default: undefined,
		},
		// Row is the one shown in the details sidebar (2.0.1).
		selected: {
			type: Boolean,
			default: false,
		},
	},
	emits: ['edit', 'view', 'select', 'duplicate', 'archive', 'restore', 'delete', 'deletePermanently'],
	data() {
		return {
			showDeleteDialog: false,
			dragging: false,
		}
	},
	computed: {
		...mapState(useContractsStore, ['isAdmin', 'canEdit']),
		numbers() {
			return numberLines(this.contract)
		},
		// Single derived status chip, shared with the details sidebar.
		chip() {
			return statusChip(this.contract, this.defaultReminderDays)
		},
		intervalLabel() {
			return intervalPerLabel(this.contract.costInterval)
		},
		// Value + semantics of the "Frist / Ende" column (#252): the shown date
		// means different things per contract, so each row carries its own label.
		deadlineInfo() {
			return getDeadlineInfo(this.contract)
		},
		deadlineDisplay() {
			return this.deadlineInfo.date ? this.formatDate(this.deadlineInfo.date) : '—'
		},
		deadlineLabel() {
			const labels = {
				cancelBy: t('nc_vertragswerk', 'Kündbar bis'),
				cancelledTo: t('nc_vertragswerk', 'Gekündigt zum'),
				runsUntil: t('nc_vertragswerk', 'Läuft bis'),
			}
			return labels[this.deadlineInfo.labelKey] || null
		},
		// „in 10 Tagen" below the date, coloured by how pressing it is (2.0.1).
		// Archive and trash show no urgency: nothing there needs acting on.
		deadlineRelative() {
			const date = this.deadlineInfo.date
			if (!date || this.mode !== 'default') return null
			const days = daysUntil(date)
			const reminder = this.defaultReminderDays ?? DEFAULT_REMINDER_DAYS_1
			return { text: relativeDays(days), urgency: deadlineUrgency(days, this.deadlineInfo.labelKey, reminder) }
		},
		// Archive and trash are view-only (#328) — dragging a row there must not
		// be able to change its category, even for editors.
		isDraggable() {
			return this.canEdit && this.mode === 'default'
		},
		deleteDialogButtons() {
			return [
				{
					label: t('nc_vertragswerk', 'Abbrechen'),
					callback: () => { this.showDeleteDialog = false },
				},
				{
					label: t('nc_vertragswerk', 'In Papierkorb'),
					variant: 'warning',
					callback: () => { this.handleDelete() },
				},
			]
		},
	},
	methods: {
		...mapActions(useContractsStore, ['deleteContract']),
		formatDate,
		formatPeriod,
		// Drag the row onto a category in the sidebar to reassign it (#359).
		// Only editors may recategorise, and only from the default (active
		// contracts) list — archive and trash rows are not draggable.
		onDragStart(event) {
			if (!this.isDraggable) {
				event.preventDefault()
				return
			}
			this.dragging = true
			event.dataTransfer.setData(CONTRACT_DND_TYPE, String(this.contract.id))
			event.dataTransfer.effectAllowed = 'move'
		},
		// Click on the row (2.0.1): in the contract list it opens the details
		// sidebar; archive and trash keep opening the form as before.
		onOpen() {
			if (this.mode === 'default') {
				this.$emit('select', this.contract)
				return
			}
			this.onEdit()
		},
		onEdit() {
			if (this.canEdit) {
				this.$emit('edit', this.contract)
			} else {
				this.$emit('view', this.contract)
			}
		},
		async handleDelete() {
			try {
				await this.deleteContract(this.contract.id)
				showSuccess(t('nc_vertragswerk', 'Vertrag in Papierkorb verschoben'))
				this.$emit('delete', this.contract)
			} catch (error) {
				console.error('Failed to delete contract:', error)
				showError(t('nc_vertragswerk', 'Fehler beim Löschen'))
			} finally {
				this.showDeleteDialog = false
			}
		},
		formatCost(cost, currency) {
			if (!cost) return ''
			const amount = parseFloat(cost)
			return new Intl.NumberFormat(getCanonicalLocale(), {
				style: 'currency',
				currency: currency || 'EUR',
			}).format(amount)
		},
		openFolder() {
			if (!this.contract.contractFolder) return
			const filesUrl = generateUrl('/apps/files/?dir={dir}', {
				dir: this.contract.contractFolder,
			})
			window.open(filesUrl, '_blank', 'noopener,noreferrer')
		},
		openDocument() {
			openDocumentInNewTab(this.contract.mainDocument)
		},
	},
}
</script>

<style scoped lang="scss">
/* Colours come only from the Nextcloud theme variables (2.0.1), so the dark
   and high-contrast themes work without extra rules. */

/* Shared column layout — MUST match .contract-list__thead in ContractList.vue */
.contract-list-item {
	display: grid;
	grid-template-columns: 32px minmax(0, 1fr) 170px 120px 130px 32px 96px;
	align-items: center;
	gap: 12px;
	padding: 0 4px 0 12px;
	background: var(--color-main-background);
	border-bottom: 1px solid var(--color-border);
	min-height: 58px;
	cursor: pointer;
	transition: background-color 0.1s ease;

	&:last-child { border-bottom: none; }

	&:hover {
		background: var(--color-background-hover);
	}

	&--selected,
	&--selected:hover {
		background: var(--color-primary-element-light);
	}

	/* grab cursor signals the row can be dragged onto a sidebar category
	   (#359); only shown where dragging is actually enabled. */
	&[draggable="true"]:active {
		cursor: grabbing;
	}

	&--dragging {
		opacity: 0.5;
		cursor: grabbing;
	}

	&__name {
		min-width: 0;
		padding: 8px 0;
	}

	&__meta {
		margin-top: 1px;
		font-size: 13px;
		color: var(--color-text-maxcontrast);
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;

		.sep { opacity: 0.5; margin: 0 5px; }
	}

	&__status {
		display: flex;
		flex-wrap: wrap;
		gap: 6px;
		min-width: 0;

		.cm-chip {
			max-width: 100%;
		}
	}

	&__cost {
		display: flex;
		flex-direction: column;
		align-items: flex-end;
		font-weight: 600;
		font-variant-numeric: tabular-nums;
		white-space: nowrap;
	}

	&__interval {
		font-size: 12px;
		font-weight: 400;
		color: var(--color-text-maxcontrast);
	}

	&__deadline {
		display: flex;
		flex-direction: column;
		font-variant-numeric: tabular-nums;
		white-space: nowrap;
		color: var(--color-main-text);
	}

	&__deadline-label {
		font-size: 12px;
		line-height: 1.3;
		color: var(--color-text-maxcontrast);
	}

	&__deadline-rel {
		font-size: 12px;
		line-height: 1.3;
		font-weight: 600;

		&--critical { color: var(--color-error-text); }
		&--warning { color: var(--color-warning-text); }
		&--normal { color: var(--color-text-maxcontrast); font-weight: 400; }
	}

	&__responsible {
		display: flex;
		justify-content: center;
	}

	&__actions {
		display: flex;
		align-items: center;
		justify-content: flex-end;
		gap: 2px;
		cursor: default;
	}

	/* Folder and document buttons appear on hover or keyboard focus; the
	   „…" menu stays visible so every action remains reachable. */
	/* Nested selector: NcButton's own rules have the same specificity. */
	& &__quick {
		opacity: 0;
	}

	&:hover &__quick,
	&:focus-within &__quick,
	&--selected &__quick {
		opacity: 1;
	}
}

/* Narrow list (details sidebar open, small screens): tighter columns, only
   the „…" menu in the action column. The container is .contract-list. */
@container contract-list (max-width: 800px) {
	.contract-list-item {
		grid-template-columns: 32px minmax(0, 1fr) 132px 100px 108px 28px 40px;
		gap: 10px;
	}

	.contract-list-item .contract-list-item__quick {
		display: none;
	}
}

/* Very narrow list: the status moves below the name, both avatar columns
   are dropped (vendor and responsible person are in the sidebar). */
@container contract-list (max-width: 700px) {
	.contract-list-item {
		grid-template-columns: minmax(0, 1fr) 100px 108px 40px;
		grid-template-areas:
			"name cost deadline actions"
			"status cost deadline actions";
		row-gap: 0;
	}

	.contract-list-item__avatar,
	.contract-list-item__responsible {
		display: none;
	}

	.contract-list-item__name { grid-area: name; padding-bottom: 2px; }
	.contract-list-item__status { grid-area: status; padding-bottom: 8px; }
	.contract-list-item__cost { grid-area: cost; }
	.contract-list-item__deadline { grid-area: deadline; }
	.contract-list-item__actions { grid-area: actions; }
}

.contract-name {
	display: block;
	font-weight: 600;
	color: var(--color-main-text);
	text-decoration: none;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;

	&:focus-visible {
		outline: 2px solid var(--color-main-text);
		outline-offset: 2px;
		border-radius: 2px;
	}
}

.delete-action {
	color: var(--color-error) !important;
}
</style>
