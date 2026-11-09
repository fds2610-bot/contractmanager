<template>
	<!-- „Kategorien" (fds2610/contractmanager#7): admins manage, everyone else reads -->
	<div class="settings-section area-settings">
		<h3>{{ isAdmin ? t('nc_vertragswerk', 'Kategorien verwalten') : t('nc_vertragswerk', 'Kategorien') }}</h3>
		<p class="settings-description">
			{{ isAdmin
				? t('nc_vertragswerk', 'Kategorien ordnen Verträge Lebensbereichen zu, z. B. Wohnen oder Fahrzeuge. Umbenennen gilt für alle zugeordneten Verträge.')
				: t('nc_vertragswerk', 'Kategorien können nur von Administratoren verwaltet werden.') }}
		</p>

		<template v-if="isAdmin">
			<div class="area-add">
				<NcTextField v-model="newName"
					:label="t('nc_vertragswerk', 'Neue Kategorie')"
					class="area-input"
					data-test="area-new"
					@keyup.enter="add" />
				<NcButton variant="primary"
					:disabled="!newName.trim() || busy"
					@click="add">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
					{{ t('nc_vertragswerk', 'Hinzufügen') }}
				</NcButton>
			</div>

			<div class="area-list-edit">
				<div v-for="area in allAreas"
					:key="area.id"
					class="area-edit-item"
					data-test="area-item">
					<template v-if="editingId === area.id">
						<NcTextField v-model="editingName"
							:label="t('nc_vertragswerk', 'Name')"
							class="area-input"
							@keyup.enter="save(area)"
							@keyup.esc="editingId = null" />
						<NcButton variant="primary" :aria-label="t('nc_vertragswerk', 'Speichern')" @click="save(area)">
							<template #icon>
								<CheckIcon :size="20" />
							</template>
						</NcButton>
						<NcButton variant="tertiary" :aria-label="t('nc_vertragswerk', 'Abbrechen')" @click="editingId = null">
							<template #icon>
								<CloseIcon :size="20" />
							</template>
						</NcButton>
					</template>
					<template v-else>
						<span class="area-name">{{ area.name }}</span>
						<div class="area-actions">
							<NcButton variant="tertiary" :aria-label="t('nc_vertragswerk', 'Umbenennen')" @click="startEdit(area)">
								<template #icon>
									<PencilIcon :size="20" />
								</template>
							</NcButton>
							<NcButton variant="tertiary" :aria-label="t('nc_vertragswerk', 'Löschen')" @click="askDelete(area)">
								<template #icon>
									<DeleteIcon :size="20" />
								</template>
							</NcButton>
						</div>
					</template>
				</div>
			</div>
		</template>

		<div v-else class="area-list">
			<span v-for="area in allAreas" :key="area.id" class="area-item">{{ area.name }}</span>
		</div>

		<NcDialog v-if="deleting"
			:name="t('nc_vertragswerk', 'Kategorie löschen')"
			@close="deleting = null">
			<p>{{ deleteQuestion }}</p>
			<template #actions>
				<NcButton @click="deleting = null">
					{{ t('nc_vertragswerk', 'Abbrechen') }}
				</NcButton>
				<NcButton variant="error" data-test="area-delete-confirm" @click="remove">
					{{ t('nc_vertragswerk', 'Löschen') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>
import { mapState, mapActions } from 'pinia'
import { translatePlural as n } from '@nextcloud/l10n'
import { showError, showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import PencilIcon from 'vue-material-design-icons/PencilOutline.vue'
import DeleteIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import { useAreasStore } from '../store/areas'
import { useContractsStore } from '../store/contracts'

export default {
	name: 'AreaSettings',
	components: { NcButton, NcDialog, NcTextField, PlusIcon, CheckIcon, CloseIcon, PencilIcon, DeleteIcon },
	props: {
		isAdmin: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			newName: '',
			editingId: null,
			editingName: '',
			deleting: null,
			busy: false,
		}
	},
	computed: {
		...mapState(useAreasStore, ['allAreas']),
		deleteQuestion() {
			if (!this.deleting) return ''
			const count = this.deleting.contractCount ?? 0
			return count > 0
				? n('nc_vertragswerk',
					'Kategorie „{name}“ löschen? %n Vertrag verliert dabei seine Kategorie.',
					'Kategorie „{name}“ löschen? %n Verträge verlieren dabei ihre Kategorie.',
					count, { name: this.deleting.name })
				: t('nc_vertragswerk', 'Kategorie „{name}“ löschen?', { name: this.deleting.name })
		},
	},
	created() {
		this.fetchAreas().catch(() => showError(t('nc_vertragswerk', 'Kategorien konnten nicht geladen werden')))
	},
	methods: {
		...mapActions(useAreasStore, ['fetchAreas', 'createArea', 'updateArea', 'deleteArea']),
		...mapActions(useContractsStore, ['fetchContracts']),
		async add() {
			const name = this.newName.trim()
			if (!name || this.busy) return
			this.busy = true
			try {
				await this.createArea(name)
				this.newName = ''
				showSuccess(t('nc_vertragswerk', 'Kategorie hinzugefügt'))
			} catch (e) {
				showError(t('nc_vertragswerk', 'Fehler beim Hinzufügen der Kategorie'))
			} finally {
				this.busy = false
			}
		},
		// Fresh counts from the server: the browser does not know every
		// contract (other users' private ones, ones created meanwhile).
		async askDelete(area) {
			try {
				await this.fetchAreas()
			} catch (e) {
				// keep the known count
			}
			this.deleting = this.allAreas.find(a => a.id === area.id) ?? area
		},
		startEdit(area) {
			this.editingId = area.id
			this.editingName = area.name
		},
		async save(area) {
			const name = this.editingName.trim()
			if (!name) return
			try {
				await this.updateArea(area.id, name)
				this.editingId = null
				showSuccess(t('nc_vertragswerk', 'Kategorie aktualisiert'))
			} catch (e) {
				showError(t('nc_vertragswerk', 'Fehler beim Aktualisieren der Kategorie'))
			}
		},
		async remove() {
			const area = this.deleting
			this.deleting = null
			try {
				await this.deleteArea(area.id)
				// The server cleared the category on the contracts; reload them.
				await this.fetchContracts()
				showSuccess(t('nc_vertragswerk', 'Kategorie gelöscht'))
			} catch (e) {
				showError(t('nc_vertragswerk', 'Fehler beim Löschen der Kategorie'))
			}
		},
	},
}
</script>

<style scoped lang="scss">
.area-add {
	display: flex;
	align-items: flex-end;
	gap: 8px;
	margin: 16px 0;

	.area-input { max-width: 300px; }
}

.area-list-edit {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.area-edit-item {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 8px 12px;
	background: var(--color-background-dark);
	border-radius: 8px;

	.area-name {
		flex: 1;
		font-size: 14px;
	}

	.area-input {
		flex: 1;
		max-width: 300px;
	}

	.area-actions {
		display: flex;
		gap: 4px;
		opacity: 0.6;
		transition: opacity 0.2s;
	}

	&:hover .area-actions { opacity: 1; }
}

.area-list {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin-top: 12px;
}

.area-item {
	padding: 6px 12px;
	background: var(--color-background-dark);
	border-radius: 16px;
	font-size: 13px;
}
</style>
