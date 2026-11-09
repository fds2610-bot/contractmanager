<template>
	<!-- „Neuer Kontakt“ aus dem Vertragsformular (fds2610/contractmanager#3).
	     Liegt über dem Vertragsdialog; Kopf- und Fußzeile stehen fest, nur der
	     Inhalt dazwischen scrollt. -->
	<NcModal :show="true"
		label-id="new-contact-title"
		size="normal"
		:set-return-focus="returnFocus"
		@close="$emit('close')">
		<div class="new-contact" data-test="new-contact-dialog">
			<header class="new-contact__head">
				<h2 id="new-contact-title">
					{{ t('nc_vertragswerk', 'Neuer Kontakt') }}
				</h2>
			</header>

			<form id="new-contact-form" class="new-contact__body" @submit.prevent="save">
				<label class="nc-field">
					<span>{{ t('nc_vertragswerk', 'Adressbuch') }}</span>
					<select v-model="addressBookKey" class="nc-input" data-test="new-contact-addressbook">
						<option v-for="book in addressBooks" :key="book.key" :value="book.key">
							{{ book.name }}
						</option>
					</select>
				</label>

				<div class="nc-row">
					<label class="nc-field">
						<span>{{ t('nc_vertragswerk', 'Vorname') }}</span>
						<input ref="firstName"
							v-model="contact.firstName"
							class="nc-input"
							data-test="new-contact-first-name">
					</label>
					<label class="nc-field">
						<span>{{ t('nc_vertragswerk', 'Nachname') }}</span>
						<input v-model="contact.lastName" class="nc-input" data-test="new-contact-last-name">
					</label>
				</div>
				<div class="nc-row">
					<label class="nc-field">
						<span>{{ t('nc_vertragswerk', 'Firma') }}</span>
						<input v-model="contact.organization" class="nc-input" data-test="new-contact-organization">
					</label>
					<label class="nc-field">
						<span>{{ t('nc_vertragswerk', 'Abteilung') }}</span>
						<input v-model="contact.department" class="nc-input">
					</label>
				</div>
				<label class="nc-field">
					<span>{{ t('nc_vertragswerk', 'Position') }}</span>
					<input v-model="contact.title" class="nc-input">
				</label>

				<!-- Telefon / E-Mail: beliebig viele, jeweils mit Typ -->
				<div v-for="list in typedLists" :key="list.key" class="nc-block">
					<span class="nc-label">{{ list.label }}</span>
					<div v-for="(entry, index) in contact[list.key]"
						:key="list.key + index"
						class="nc-line"
						:data-test="'new-contact-' + list.key">
						<select v-model="entry.type" class="nc-input nc-input--type" :aria-label="t('nc_vertragswerk', 'Typ')">
							<option v-for="opt in list.types" :key="opt.value" :value="opt.value">
								{{ opt.label }}
							</option>
						</select>
						<input v-model="entry.value"
							class="nc-input"
							:type="list.inputType"
							:aria-label="list.label">
						<button type="button"
							class="nc-btn nc-btn--add"
							:aria-label="list.addLabel"
							:title="list.addLabel"
							@click="addEntry(list.key, index)">
							<PlusCircleIcon :size="24" />
						</button>
						<button v-if="contact[list.key].length > 1"
							type="button"
							class="nc-btn nc-btn--remove"
							:aria-label="t('nc_vertragswerk', 'Entfernen')"
							:title="t('nc_vertragswerk', 'Entfernen')"
							@click="contact[list.key].splice(index, 1)">
							<MinusCircleIcon :size="24" />
						</button>
					</div>
				</div>

				<div class="nc-block">
					<span class="nc-label">{{ t('nc_vertragswerk', 'Adressen') }}</span>
					<div v-for="(address, index) in contact.addresses"
						:key="'adr' + index"
						class="nc-address"
						data-test="new-contact-address">
						<div class="nc-line">
							<select v-model="address.type" class="nc-input nc-input--type" :aria-label="t('nc_vertragswerk', 'Typ')">
								<option v-for="opt in addressTypes" :key="opt.value" :value="opt.value">
									{{ opt.label }}
								</option>
							</select>
							<input v-model="address.street"
								class="nc-input"
								:placeholder="t('nc_vertragswerk', 'Straße und Hausnummer')"
								:aria-label="t('nc_vertragswerk', 'Straße und Hausnummer')">
							<button type="button"
								class="nc-btn nc-btn--add"
								:aria-label="t('nc_vertragswerk', 'Weitere Adresse hinzufügen')"
								:title="t('nc_vertragswerk', 'Weitere Adresse hinzufügen')"
								@click="contact.addresses.splice(index + 1, 0, emptyAddress())">
								<PlusCircleIcon :size="24" />
							</button>
							<button v-if="contact.addresses.length > 1"
								type="button"
								class="nc-btn nc-btn--remove"
								:aria-label="t('nc_vertragswerk', 'Entfernen')"
								:title="t('nc_vertragswerk', 'Entfernen')"
								@click="contact.addresses.splice(index, 1)">
								<MinusCircleIcon :size="24" />
							</button>
						</div>
						<div class="nc-address__grid">
							<input v-model="address.postalCode"
								class="nc-input"
								:placeholder="t('nc_vertragswerk', 'PLZ')"
								:aria-label="t('nc_vertragswerk', 'PLZ')">
							<input v-model="address.city"
								class="nc-input"
								:placeholder="t('nc_vertragswerk', 'Ort')"
								:aria-label="t('nc_vertragswerk', 'Ort')">
							<input v-model="address.region"
								class="nc-input"
								:placeholder="t('nc_vertragswerk', 'Bundesland')"
								:aria-label="t('nc_vertragswerk', 'Bundesland')">
							<input v-model="address.country"
								class="nc-input"
								:placeholder="t('nc_vertragswerk', 'Land')"
								:aria-label="t('nc_vertragswerk', 'Land')">
						</div>
					</div>
				</div>

				<div class="nc-row">
					<label class="nc-field">
						<span>{{ t('nc_vertragswerk', 'Webseite') }}</span>
						<input v-model="contact.url" class="nc-input" type="url">
					</label>
					<label class="nc-field">
						<span>{{ t('nc_vertragswerk', 'Geburtstag') }}</span>
						<input v-model="contact.birthday" class="nc-input" type="date">
					</label>
				</div>
				<label class="nc-field">
					<span>{{ t('nc_vertragswerk', 'Notiz') }}</span>
					<textarea v-model="contact.note" class="nc-input" rows="3" />
				</label>
			</form>

			<footer class="new-contact__foot">
				<p v-if="error" class="new-contact__error">
					{{ error }}
				</p>
				<NcButton @click="$emit('close')">
					{{ t('nc_vertragswerk', 'Abbrechen') }}
				</NcButton>
				<NcButton variant="primary"
					:disabled="!canSave || saving"
					data-test="new-contact-save"
					@click="save">
					<template v-if="saving" #icon>
						<NcLoadingIcon :size="20" />
					</template>
					{{ t('nc_vertragswerk', 'Kontakt anlegen') }}
				</NcButton>
			</footer>
		</div>
	</NcModal>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcModal from '@nextcloud/vue/components/NcModal'
import PlusCircleIcon from 'vue-material-design-icons/PlusCircle.vue'
import MinusCircleIcon from 'vue-material-design-icons/MinusCircle.vue'
import ContactService from '../services/ContactService'

const emptyEntry = (type) => ({ type, value: '' })

export default {
	name: 'NewContactDialog',
	components: { NcButton, NcLoadingIcon, NcModal, PlusCircleIcon, MinusCircleIcon },
	props: {
		// Selector of the element that gets the focus back when the dialog closes
		returnFocus: {
			type: String,
			default: undefined,
		},
		// Vorbelegung der Firma, z. B. mit dem Vertragspartner
		organization: {
			type: String,
			default: '',
		},
	},
	emits: ['close', 'created', 'ready'],
	data() {
		return {
			addressBooks: [],
			addressBookKey: '',
			contact: {
				firstName: '',
				lastName: '',
				organization: this.organization,
				department: '',
				title: '',
				phones: [emptyEntry('WORK')],
				emails: [emptyEntry('WORK')],
				addresses: [this.emptyAddress()],
				url: '',
				birthday: '',
				note: '',
			},
			saving: false,
			error: '',
		}
	},
	computed: {
		canSave() {
			return this.addressBookKey !== ''
				&& (this.contact.firstName.trim() !== '' || this.contact.lastName.trim() !== '' || this.contact.organization.trim() !== '')
		},
		typedLists() {
			return [
				{
					key: 'phones',
					label: t('nc_vertragswerk', 'Telefon'),
					addLabel: t('nc_vertragswerk', 'Weitere Telefonnummer hinzufügen'),
					inputType: 'tel',
					types: [
						{ value: 'WORK', label: t('nc_vertragswerk', 'Geschäftlich') },
						{ value: 'CELL', label: t('nc_vertragswerk', 'Mobil') },
						{ value: 'HOME', label: t('nc_vertragswerk', 'Privat') },
						{ value: 'FAX', label: t('nc_vertragswerk', 'Fax') },
						{ value: 'VOICE', label: t('nc_vertragswerk', 'Sonstige') },
					],
				},
				{
					key: 'emails',
					label: t('nc_vertragswerk', 'E-Mail'),
					addLabel: t('nc_vertragswerk', 'Weitere E-Mail-Adresse hinzufügen'),
					inputType: 'email',
					types: this.addressTypes,
				},
			]
		},
		addressTypes() {
			return [
				{ value: 'WORK', label: t('nc_vertragswerk', 'Geschäftlich') },
				{ value: 'HOME', label: t('nc_vertragswerk', 'Privat') },
				{ value: 'OTHER', label: t('nc_vertragswerk', 'Sonstige') },
			]
		},
	},
	async created() {
		try {
			this.addressBooks = await ContactService.addressBooks()
			const preselected = this.addressBooks.find(b => b.default) ?? this.addressBooks[0]
			this.addressBookKey = preselected ? preselected.key : ''
			if (!this.addressBooks.length) {
				this.error = t('nc_vertragswerk', 'Kein Adressbuch, in dem Sie Kontakte anlegen dürfen.')
			}
		} catch (e) {
			this.error = t('nc_vertragswerk', 'Adressbücher konnten nicht geladen werden.')
		}
	},
	mounted() {
		this.$nextTick(() => {
			// The modal is teleported to <body>: hand its element to the
			// contract modal below, so that one's focus trap lets us type here.
			const mask = this.$refs.firstName?.closest('.modal-mask')
			if (mask) this.$emit('ready', mask)
			this.$refs.firstName?.focus()
		})
	},
	methods: {
		emptyAddress() {
			return { type: 'WORK', street: '', postalCode: '', city: '', region: '', country: '' }
		},
		addEntry(key, index) {
			this.contact[key].splice(index + 1, 0, emptyEntry(this.contact[key][index].type))
		},
		async save() {
			if (!this.canSave || this.saving) return
			this.saving = true
			this.error = ''
			try {
				const created = await ContactService.create(this.addressBookKey, this.contact)
				this.$emit('created', created)
			} catch (e) {
				this.error = e?.response?.data?.error || t('nc_vertragswerk', 'Der Kontakt konnte nicht angelegt werden.')
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped lang="scss">
// Feste Kopf- und Fußzeile, nur der Inhalt scrollt
.new-contact {
	display: flex;
	flex-direction: column;
	max-height: min(90vh, calc(100vh - 120px));

	&__head {
		flex: 0 0 auto;
		padding: 16px 20px 8px;
		border-bottom: 1px solid var(--color-border);

		h2 {
			margin: 0;
			font-size: 18px;
		}
	}

	&__body {
		flex: 1 1 auto;
		min-height: 0;
		overflow-y: auto;
		padding: 16px 20px;
	}

	&__foot {
		flex: 0 0 auto;
		display: flex;
		align-items: center;
		justify-content: flex-end;
		gap: 8px;
		padding: 12px 20px;
		border-top: 1px solid var(--color-border);
	}

	&__error {
		flex: 1;
		margin: 0;
		color: var(--color-text-error, var(--color-error));
	}
}

.nc-field {
	display: block;
	margin-bottom: 12px;

	> span {
		display: block;
		font-size: 13px;
		color: var(--color-text-maxcontrast);
		margin-bottom: 4px;
	}
}

.nc-row {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 12px;

	.nc-field { margin-bottom: 12px; }
}

.nc-block {
	margin-bottom: 12px;
}

.nc-label {
	display: block;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	margin-bottom: 4px;
}

.nc-line {
	display: flex;
	align-items: center;
	gap: 6px;
	margin-bottom: 6px;
}

.nc-address {
	margin-bottom: 10px;

	&__grid {
		display: grid;
		grid-template-columns: 110px 1fr 1fr 1fr;
		gap: 6px;
		padding-right: 80px;
	}
}

.nc-input {
	width: 100%;
	min-height: 36px;
	padding: 6px 10px;
	margin: 0;
	border: 1px solid var(--color-border-dark, var(--color-border));
	border-radius: var(--border-radius, 8px);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font: inherit;
	box-sizing: border-box;

	&:focus,
	&:focus-visible {
		outline: none;
		border-color: var(--color-primary-element);
		box-shadow: 0 0 0 2px var(--color-primary-element-light);
	}

	&--type {
		flex: 0 0 140px;
		width: 140px;
	}
}

.nc-btn {
	flex: 0 0 auto;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 34px;
	height: 34px;
	min-height: 0;
	padding: 0;
	margin: 0;
	border: none;
	border-radius: 50%;
	background: transparent;
	cursor: pointer;

	&:hover,
	&:focus-visible { background: var(--color-background-hover); }

	&--add { color: var(--color-element-success, #099f05); }
	&--remove { color: var(--color-element-error, #c90000); }
}
</style>
