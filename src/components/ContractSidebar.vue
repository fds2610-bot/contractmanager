<template>
	<NcAppSidebar v-model:active="activeTab"
		class="contract-sidebar"
		:name="contract.name || ''"
		:subname="contract.vendor || ''"
		data-test="contract-sidebar"
		@close="$emit('close')">
		<template #description>
			<div class="contract-sidebar__tags">
				<span class="cm-chip" :class="'cm-chip--' + chip.cls" :title="chip.title">{{ chip.label }}</span>
				<span v-if="categoryLabel" class="contract-sidebar__tag">
					<span class="contract-sidebar__bullet" :style="{ background: categoryDot }" aria-hidden="true" />
					{{ categoryLabel }}
				</span>
				<span v-if="areaLabel" class="contract-sidebar__tag">{{ areaLabel }}</span>
				<span v-if="contract.isPrivate" class="contract-sidebar__tag">
					<LockIcon :size="14" />
					{{ t('nc_vertragswerk', 'Privat') }}
				</span>
			</div>
			<div class="contract-sidebar__actions">
				<NcButton v-if="canEdit" variant="secondary" @click="requestForm('edit', contract)">
					<template #icon>
						<PencilIcon :size="20" />
					</template>
					{{ t('nc_vertragswerk', 'Bearbeiten') }}
				</NcButton>
				<NcButton v-else variant="secondary" @click="requestForm('view', contract)">
					<template #icon>
						<EyeIcon :size="20" />
					</template>
					{{ t('nc_vertragswerk', 'Alle Angaben') }}
				</NcButton>
				<NcButton v-if="contract.mainDocument" variant="tertiary" @click="openDocument">
					<template #icon>
						<FileDocumentIcon :size="20" />
					</template>
					{{ t('nc_vertragswerk', 'Dokument') }}
				</NcButton>
				<NcButton v-if="contract.contractFolder" variant="tertiary" @click="openFolder">
					<template #icon>
						<FolderOpenIcon :size="20" />
					</template>
					{{ t('nc_vertragswerk', 'Ordner') }}
				</NcButton>
			</div>
		</template>

		<template v-if="canEdit" #secondary-actions>
			<NcActionButton :close-after-click="true" @click="requestForm('view', contract)">
				<template #icon>
					<EyeIcon :size="20" />
				</template>
				{{ t('nc_vertragswerk', 'Alle Angaben') }}
			</NcActionButton>
			<NcActionButton :close-after-click="true" @click="requestForm('duplicate', contract)">
				<template #icon>
					<ContentDuplicateIcon :size="20" />
				</template>
				{{ t('nc_vertragswerk', 'Duplizieren') }}
			</NcActionButton>
		</template>

		<NcAppSidebarTab id="details" :name="t('nc_vertragswerk', 'Details')" :order="1">
			<template #icon>
				<InformationIcon :size="20" />
			</template>
			<div class="contract-sidebar__tab">
				<NcNoteCard v-if="deadlineNote" type="warning" :heading="deadlineNote.heading">
					{{ deadlineNote.text }}
				</NcNoteCard>

				<div v-if="term" class="term" data-test="term-bar">
					<div class="term__bar">
						<div class="term__fill" :style="{ width: term.todayPct + '%' }" />
						<div v-if="term.deadlinePct !== null"
							class="term__mark term__mark--deadline"
							:class="{ 'term__mark--warn': chip.cls === 'soon' }"
							:style="{ left: term.deadlinePct + '%' }">
							<span :style="anchor(term.deadlinePct)">{{ t('nc_vertragswerk', 'kündbar bis {date}', { date: term.deadlineLabel }) }}</span>
						</div>
						<div class="term__mark term__mark--today" :style="{ left: term.todayPct + '%' }">
							<span :style="anchor(term.todayPct)">{{ t('nc_vertragswerk', 'heute') }}</span>
						</div>
					</div>
					<div class="term__ends">
						<span>{{ term.startCaption }} {{ term.startLabel }}</span>
						<span>{{ t('nc_vertragswerk', 'Laufzeitende') }} {{ term.endLabel }}</span>
					</div>
				</div>

				<dl class="facts">
					<template v-for="fact in facts" :key="fact.key">
						<dt>{{ fact.label }}</dt>
						<dd :data-test="'fact-' + fact.key">
							<NcUserBubble v-if="fact.key === 'responsible'"
								:user="contract.responsibleUser"
								:display-name="contract.responsibleUser"
								:size="24" />
							<template v-else>
								{{ fact.value }}
							</template>
						</dd>
					</template>
				</dl>

				<div v-if="contract.notes" class="contract-sidebar__notes">
					<h3>{{ t('nc_vertragswerk', 'Notizen') }}</h3>
					<p>{{ contract.notes }}</p>
				</div>
			</div>
		</NcAppSidebarTab>

		<NcAppSidebarTab id="documents" :name="t('nc_vertragswerk', 'Dokumente')" :order="2">
			<template #icon>
				<PaperclipIcon :size="20" />
			</template>
			<div class="contract-sidebar__tab">
				<ul v-if="contract.mainDocument" class="files">
					<li>
						<button type="button" class="files__entry" @click="openDocument">
							<FileDocumentIcon :size="20" />
							<span class="files__text">
								{{ documentName }}
								<small>{{ t('nc_vertragswerk', 'Vertragsdokument') }}</small>
							</span>
						</button>
					</li>
				</ul>

				<template v-if="contract.contractFolder">
					<h3 class="files__heading">
						{{ t('nc_vertragswerk', 'Vertragsordner') }}
					</h3>
					<div v-if="folder.loading" class="contract-sidebar__loading">
						<NcLoadingIcon :size="32" />
					</div>
					<p v-else-if="folder.error" class="contract-sidebar__hint">
						{{ t('nc_vertragswerk', 'Der Ordner ist nicht erreichbar.') }}
					</p>
					<p v-else-if="folder.files.length === 0" class="contract-sidebar__hint">
						{{ t('nc_vertragswerk', 'Der Ordner ist leer.') }}
					</p>
					<ul v-else class="files" data-test="folder-files">
						<li v-for="file in folder.files" :key="file.path">
							<button type="button" class="files__entry" @click="openFile(file)">
								<FolderIcon v-if="file.isDir" :size="20" />
								<FileIcon v-else :size="20" />
								<span class="files__text">
									{{ file.name }}
									<small v-if="!file.isDir">{{ formatSize(file.size) }}</small>
								</span>
							</button>
						</li>
					</ul>
					<NcButton variant="tertiary" @click="openFolder">
						<template #icon>
							<FolderOpenIcon :size="20" />
						</template>
						{{ t('nc_vertragswerk', 'In Dateien öffnen') }}
					</NcButton>
				</template>

				<NcEmptyContent v-if="!contract.mainDocument && !contract.contractFolder"
					:name="t('nc_vertragswerk', 'Keine Dokumente')"
					:description="t('nc_vertragswerk', 'Hinterlegen Sie beim Bearbeiten ein Vertragsdokument oder einen Vertragsordner.')">
					<template #icon>
						<PaperclipIcon :size="48" />
					</template>
				</NcEmptyContent>
			</div>
		</NcAppSidebarTab>

		<NcAppSidebarTab id="activity" :name="t('nc_vertragswerk', 'Verlauf')" :order="3">
			<template #icon>
				<HistoryIcon :size="20" />
			</template>
			<div class="contract-sidebar__tab">
				<ul class="history">
					<li v-for="entry in history" :key="entry.key">
						<component :is="entry.icon" :size="20" />
						<span>
							{{ entry.text }}
							<small>{{ entry.date }}</small>
						</span>
					</li>
				</ul>
				<p class="contract-sidebar__hint">
					{{ t('nc_vertragswerk', 'Einzelne Änderungen am Vertrag werden nicht protokolliert.') }}
				</p>
			</div>
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script>
import axios from '@nextcloud/axios'
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'
import { getCanonicalLocale } from '@nextcloud/l10n'
import { mapState, mapActions } from 'pinia'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcUserBubble from '@nextcloud/vue/components/NcUserBubble'
import ContentDuplicateIcon from 'vue-material-design-icons/ContentDuplicate.vue'
import EyeIcon from 'vue-material-design-icons/EyeOutline.vue'
import FileDocumentIcon from 'vue-material-design-icons/FileDocumentOutline.vue'
import FileIcon from 'vue-material-design-icons/FileOutline.vue'
import FolderIcon from 'vue-material-design-icons/FolderOutline.vue'
import FolderOpenIcon from 'vue-material-design-icons/FolderOpenOutline.vue'
import HistoryIcon from 'vue-material-design-icons/History.vue'
import InformationIcon from 'vue-material-design-icons/InformationOutline.vue'
import LockIcon from 'vue-material-design-icons/LockOutline.vue'
import PaperclipIcon from 'vue-material-design-icons/Paperclip.vue'
import PencilIcon from 'vue-material-design-icons/PencilOutline.vue'
import CalendarIcon from 'vue-material-design-icons/CalendarOutline.vue'
import PlusIcon from 'vue-material-design-icons/PlusCircleOutline.vue'
import CancelIcon from 'vue-material-design-icons/Cancel.vue'
import { useContractsStore } from '../store/contracts'
import { useCategoriesStore } from '../store/categories'
import { useAreasStore } from '../store/areas'
import { useUiStore } from '../store/ui'
import { categoryColor, daysUntil, intervalPerLabel, relativeDays, statusChip } from '../utils/listDisplay'
import { formatPeriod, getDeadlineInfo, getEffectiveEndDate, subtractPeriod } from '../utils/periodUtils.js'
import { formatDate, parseLocalDate } from '../utils/dateUtils.js'
import { formatAmount } from '../utils/contractMirror'
import { paymentMethodLabel } from '../utils/paymentMethod'
import { getDisplayName, openDocumentInNewTab } from '../utils/documentUtils.js'

/**
 * Details of the selected contract on the right (2.0.1): the list stays
 * visible, editing still happens in the contract form.
 */
export default {
	name: 'ContractSidebar',
	components: {
		NcAppSidebar,
		NcAppSidebarTab,
		NcActionButton,
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		NcNoteCard,
		NcUserBubble,
		ContentDuplicateIcon,
		EyeIcon,
		FileDocumentIcon,
		FileIcon,
		FolderIcon,
		FolderOpenIcon,
		HistoryIcon,
		InformationIcon,
		LockIcon,
		PaperclipIcon,
		PencilIcon,
	},
	props: {
		contract: {
			type: Object,
			required: true,
		},
	},
	emits: ['close'],
	data() {
		return {
			activeTab: 'details',
			folder: { loading: false, error: false, files: [], path: null },
		}
	},
	computed: {
		...mapState(useContractsStore, ['canEdit']),
		...mapState(useCategoriesStore, ['allCategories']),
		...mapState(useAreasStore, ['allAreas']),
		...mapState(useUiStore, ['reminderDays']),
		chip() {
			return statusChip(this.contract, this.reminderDays)
		},
		categoryLabel() {
			if (!this.contract.categoryId) return ''
			return this.allCategories.find(c => c.id === this.contract.categoryId)?.name ?? ''
		},
		categoryDot() {
			return categoryColor(this.contract.categoryId)
		},
		areaLabel() {
			if (!this.contract.areaId) return ''
			return this.allAreas.find(a => a.id === this.contract.areaId)?.name ?? ''
		},
		deadlineInfo() {
			return getDeadlineInfo(this.contract)
		},
		// Hint on top while the cancellation window is closing.
		deadlineNote() {
			if (this.chip.cls !== 'soon' || !this.deadlineInfo.date) return null
			const date = formatDate(this.deadlineInfo.date)
			const days = daysUntil(this.deadlineInfo.date)
			if (this.deadlineInfo.labelKey === 'cancelBy') {
				return {
					heading: t('nc_vertragswerk', 'Kündigung bis {date} möglich', { date }),
					text: this.contract.renewalPeriod
						? t('nc_vertragswerk', 'Frist endet {relative}. Danach verlängert sich der Vertrag um {period}.', { relative: relativeDays(days), period: formatPeriod(this.contract.renewalPeriod) })
						: t('nc_vertragswerk', 'Frist endet {relative}.', { relative: relativeDays(days) }),
				}
			}
			return {
				heading: t('nc_vertragswerk', 'Vertrag endet am {date}', { date }),
				text: t('nc_vertragswerk', 'Ende {relative}.', { relative: relativeDays(days) }),
			}
		},
		// Bar from the start of the current term to its end, with today and
		// the cancellation deadline. Auto-renewing contracts show the running
		// renewal period, not the whole history since the first start.
		term() {
			const c = this.contract
			if (!c.endDate) return null
			const end = getEffectiveEndDate(c.endDate, c.contractType, c.renewalPeriod, { status: c.status, cancelledTo: c.cancelledTo })
			if (!end) return null
			const first = c.startDate ? parseLocalDate(c.startDate) : null
			let start = first
			let startCaption = t('nc_vertragswerk', 'Beginn')
			if (c.contractType === 'auto_renewal' && c.renewalPeriod) {
				const periodStart = subtractPeriod(end, c.renewalPeriod)
				if (periodStart && (!first || periodStart > first)) {
					start = periodStart
					startCaption = t('nc_vertragswerk', 'Laufzeit ab')
				}
			}
			if (!start || !(end > start)) return null
			const span = end.getTime() - start.getTime()
			const pct = d => Math.max(0, Math.min(100, (d.getTime() - start.getTime()) / span * 100))
			const deadline = this.deadlineInfo.labelKey === 'cancelBy' ? this.deadlineInfo.date : null
			return {
				startCaption,
				startLabel: formatDate(start),
				endLabel: formatDate(end),
				todayPct: pct(new Date()),
				deadlinePct: deadline ? pct(deadline) : null,
				deadlineLabel: deadline ? deadline.toLocaleDateString(getCanonicalLocale(), { day: '2-digit', month: '2-digit' }) : '',
			}
		},
		facts() {
			const c = this.contract
			const list = []
			const add = (key, label, value) => {
				if (value !== null && value !== undefined && String(value).trim() !== '') {
					list.push({ key, label, value })
				}
			}
			add('contractNumber', t('nc_vertragswerk', 'Vertragsnummer'), c.contractNumber)
			add('customerNumber', t('nc_vertragswerk', 'Kundennummer'), c.customerNumber)
			if (c.cost) {
				const parts = [formatAmount(c.cost, c.currency), intervalPerLabel(c.costInterval), c.amountType === 'brutto' ? t('nc_vertragswerk', 'brutto') : t('nc_vertragswerk', 'netto')]
				add('cost', t('nc_vertragswerk', 'Kosten'), parts.filter(Boolean).join(' · '))
			}
			add('paymentMethod', t('nc_vertragswerk', 'Zahlungsart'), paymentMethodLabel(c.paymentMethod))
			add('type', t('nc_vertragswerk', 'Befristung'), c.contractType === 'auto_renewal'
				? (c.renewalPeriod
					? t('nc_vertragswerk', 'Verlängert sich um {period}', { period: formatPeriod(c.renewalPeriod) })
					: t('nc_vertragswerk', 'Automatische Verlängerung'))
				: t('nc_vertragswerk', 'Festlaufzeit'))
			if (c.cancellationPeriod) {
				add('notice', t('nc_vertragswerk', 'Kündigungsfrist'), c.cancellationDeadlineType === 'month_end'
					? t('nc_vertragswerk', '{period} zum Monatsende', { period: formatPeriod(c.cancellationPeriod) })
					: formatPeriod(c.cancellationPeriod))
			}
			if (this.deadlineInfo.date) {
				const labels = {
					cancelBy: t('nc_vertragswerk', 'Kündbar bis'),
					cancelledTo: t('nc_vertragswerk', 'Gekündigt zum'),
					runsUntil: t('nc_vertragswerk', 'Läuft bis'),
				}
				add('deadline', labels[this.deadlineInfo.labelKey] ?? t('nc_vertragswerk', 'Frist / Ende'), formatDate(this.deadlineInfo.date))
			}
			if (c.responsibleUser) {
				list.push({ key: 'responsible', label: t('nc_vertragswerk', 'Zuständig'), value: c.responsibleUser })
			}
			add('folder', t('nc_vertragswerk', 'Vertragsordner'), c.contractFolder)
			return list
		},
		history() {
			const c = this.contract
			const entries = []
			if (c.createdAt) {
				entries.push({
					key: 'created',
					icon: PlusIcon,
					at: new Date(c.createdAt),
					text: c.createdBy
						? t('nc_vertragswerk', 'Angelegt von {user}', { user: c.createdBy })
						: t('nc_vertragswerk', 'Angelegt'),
				})
			}
			if (c.updatedAt && c.updatedAt !== c.createdAt) {
				entries.push({ key: 'updated', icon: PencilIcon, at: new Date(c.updatedAt), text: t('nc_vertragswerk', 'Zuletzt geändert') })
			}
			if (c.startDate) {
				entries.push({ key: 'start', icon: CalendarIcon, at: parseLocalDate(c.startDate), text: t('nc_vertragswerk', 'Vertragsbeginn') })
			}
			if (c.cancelledOn) {
				entries.push({ key: 'cancelled', icon: CancelIcon, at: parseLocalDate(c.cancelledOn), text: t('nc_vertragswerk', 'Gekündigt') })
			}
			return entries
				.filter(e => e.at && !isNaN(e.at.getTime()))
				.sort((a, b) => b.at - a.at)
				.map(e => ({ ...e, date: formatDate(e.at) }))
		},
		documentName() {
			return getDisplayName(this.contract.mainDocument)
		},
	},
	watch: {
		activeTab(tab) {
			if (tab === 'documents') this.loadFolder()
		},
		'contract.id'() {
			this.folder = { loading: false, error: false, files: [], path: null }
			if (this.activeTab === 'documents') this.loadFolder()
		},
		'contract.contractFolder'() {
			if (this.activeTab === 'documents') this.loadFolder()
		},
	},
	methods: {
		...mapActions(useUiStore, ['requestForm']),
		anchor(pct) {
			if (pct > 75) return { transform: 'translateX(-100%)' }
			if (pct < 25) return { transform: 'none' }
			return {}
		},
		openDocument() {
			openDocumentInNewTab(this.contract.mainDocument)
		},
		openFolder() {
			const url = generateUrl('/apps/files/?dir={dir}', { dir: this.contract.contractFolder })
			window.open(url, '_blank', 'noopener,noreferrer')
		},
		openFile(file) {
			const url = file.isDir
				? generateUrl('/apps/files/?dir={dir}', { dir: file.path })
				: (file.fileId ? generateUrl('/f/{fileId}', { fileId: file.fileId }) : generateUrl('/apps/files/?dir={dir}', { dir: this.contract.contractFolder }))
			window.open(url, '_blank', 'noopener,noreferrer')
		},
		formatSize(bytes) {
			if (!Number.isFinite(bytes)) return ''
			const units = ['B', 'KB', 'MB', 'GB']
			let value = bytes
			let unit = 0
			while (value >= 1024 && unit < units.length - 1) {
				value /= 1024
				unit++
			}
			return new Intl.NumberFormat(getCanonicalLocale(), { maximumFractionDigits: unit === 0 ? 0 : 1 }).format(value) + ' ' + units[unit]
		},
		// Files directly in the contract folder, read via WebDAV (depth 1) from
		// the current user's files, like the folder button in the list.
		async loadFolder() {
			const path = this.contract.contractFolder
			if (!path || this.folder.path === path || this.folder.loading) return
			this.folder = { loading: true, error: false, files: [], path }
			try {
				const user = getCurrentUser()?.uid
				const base = `/remote.php/dav/files/${encodeURIComponent(user)}`
				const clean = '/' + path.split('/').filter(Boolean).map(encodeURIComponent).join('/')
				const response = await axios({
					method: 'PROPFIND',
					url: base + clean,
					headers: { Depth: '1' },
					data: `<?xml version="1.0"?>
						<d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">
							<d:prop><oc:fileid/><d:getcontentlength/><d:resourcetype/></d:prop>
						</d:propfind>`,
				})
				if (this.folder.path !== path) return
				const xml = new DOMParser().parseFromString(response.data, 'application/xml')
				const files = []
				for (const node of xml.getElementsByTagNameNS('DAV:', 'response')) {
					const href = decodeURIComponent(node.getElementsByTagNameNS('DAV:', 'href')[0]?.textContent ?? '')
					// href: /remote.php/dav/files/<user>/<path…>
					const marker = '/remote.php/dav/files/'
					const parts = href.slice(href.indexOf(marker) + marker.length).split('/').filter(Boolean).slice(1)
					const name = parts[parts.length - 1]
					const isDir = node.getElementsByTagNameNS('DAV:', 'collection').length > 0
					const filePath = '/' + parts.join('/')
					if (filePath.replace(/\/$/, '') === ('/' + path.split('/').filter(Boolean).join('/'))) continue
					files.push({
						name,
						path: filePath,
						isDir,
						fileId: node.getElementsByTagNameNS('http://owncloud.org/ns', 'fileid')[0]?.textContent || null,
						size: Number(node.getElementsByTagNameNS('DAV:', 'getcontentlength')[0]?.textContent),
					})
				}
				files.sort((a, b) => (b.isDir - a.isDir) || a.name.localeCompare(b.name))
				this.folder = { loading: false, error: false, files, path }
			} catch (e) {
				console.debug('Contract folder not readable:', e)
				if (this.folder.path === path) {
					this.folder = { loading: false, error: true, files: [], path }
				}
			}
		},
	},
}
</script>

<style scoped lang="scss">
.contract-sidebar {
	&__tags {
		display: flex;
		flex-wrap: wrap;
		gap: 6px;
		margin-bottom: 10px;
	}

	&__tag {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		height: 24px;
		padding: 0 10px;
		border-radius: var(--border-radius-pill, 100px);
		background: var(--color-background-dark);
		font-size: 13px;
	}

	&__bullet {
		width: 9px;
		height: 9px;
		border-radius: 50%;
	}

	&__actions {
		display: flex;
		flex-wrap: wrap;
		gap: 6px;
	}

	&__tab {
		padding: 4px 4px 16px;
	}

	&__notes {
		margin-top: 16px;

		h3 {
			font-size: 14px;
			font-weight: 600;
			margin: 0 0 4px;
		}

		p {
			white-space: pre-wrap;
			overflow-wrap: anywhere;
		}
	}

	&__hint {
		margin: 8px 0;
		color: var(--color-text-maxcontrast);
		font-size: 13px;
	}

	&__loading {
		display: flex;
		justify-content: center;
		padding: 16px;
	}
}

.term {
	margin: 8px 0 20px;

	&__bar {
		position: relative;
		height: 8px;
		margin: 28px 0 28px;
		border-radius: var(--border-radius-pill, 100px);
		background: var(--color-background-dark);
	}

	&__fill {
		position: absolute;
		inset: 0 auto 0 0;
		border-radius: inherit;
		background: var(--color-primary-element);
	}

	&__mark {
		position: absolute;
		top: -5px;
		width: 3px;
		height: 18px;
		border-radius: 2px;

		span {
			position: absolute;
			left: 1px;
			transform: translateX(-50%);
			font-size: 12px;
			font-weight: 600;
			white-space: nowrap;
		}

		&--today {
			background: var(--color-main-text);

			span { bottom: 20px; }
		}

		&--deadline {
			background: var(--color-border-maxcontrast);
			color: var(--color-text-maxcontrast);

			span { top: 20px; }
		}

		&--warn {
			background: var(--color-warning);
			color: var(--color-warning-text);
		}
	}

	&__ends {
		display: flex;
		justify-content: space-between;
		gap: 8px;
		font-size: 12px;
		color: var(--color-text-maxcontrast);
	}
}

.facts {
	display: grid;
	grid-template-columns: minmax(110px, auto) 1fr;
	gap: 8px 12px;
	margin: 0;

	dt,
	dd {
		padding: 0;
		text-align: left;
	}

	dt {
		color: var(--color-text-maxcontrast);
	}

	dd {
		margin: 0;
		min-width: 0;
		overflow-wrap: anywhere;
		font-variant-numeric: tabular-nums;
	}
}

.files {
	display: grid;
	gap: 2px;
	margin: 0 0 8px;
	padding: 0;
	list-style: none;

	&__heading {
		font-size: 14px;
		font-weight: 600;
		margin: 16px 0 6px;
	}

	&__entry {
		display: flex;
		align-items: center;
		gap: 10px;
		width: 100%;
		min-height: var(--default-clickable-area, 34px);
		margin: 0;
		padding: 6px 8px;
		border: none;
		border-radius: var(--border-radius-element, 8px);
		background: transparent;
		color: var(--color-main-text);
		font-weight: normal;
		text-align: left;
		cursor: pointer;

		&:hover {
			background: var(--color-background-hover);
		}
	}

	&__text {
		min-width: 0;
		overflow-wrap: anywhere;

		small {
			display: block;
			font-size: 12px;
			color: var(--color-text-maxcontrast);
		}
	}
}

.history {
	display: grid;
	gap: 4px;
	margin: 0;
	padding: 0;
	list-style: none;

	li {
		display: flex;
		align-items: flex-start;
		gap: 10px;
		padding: 6px 4px;
	}

	small {
		display: block;
		font-size: 12px;
		color: var(--color-text-maxcontrast);
	}
}

</style>
