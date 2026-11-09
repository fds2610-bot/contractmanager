<template>
	<!-- v-if: NcModal darf erst beim Oeffnen gemountet werden, sonst aktiviert
	     sein Focus-Trap schon beim Seitenladen und verschluckt Tab app-weit (#266) -->
	<!-- label-id statt :name: NcModals :name rendert einen sichtbaren <h2> im
	     Kopf, der auf NC 34 die zentrierte Suchleiste ueberlagert (#337). Der
	     Titel steht deshalb nur noch unsichtbar im Body (für Screenreader); die
	     sichtbare Identitaet liefert der Zusammenfassungs-Kopf. -->
	<NcModal v-if="show"
		:show="show"
		:additional-trap-elements="extraTrapElements"
		label-id="contract-form-title"
		size="large"
		@close="$emit('close')">
		<!-- Esc schliesst auch mit Fokus in einem Eingabefeld. NcModals eigener
		     Esc-Handler (useHotKey) ignoriert Events aus Inputs. -->
		<div class="contract-form"
			:class="{ 'contract-form--readonly': readOnly }"
			@keydown.esc="onEscape">
			<h2 id="contract-form-title" class="hidden-visually">
				{{ modalTitle }}
			</h2>
			<form @submit.prevent="handleSubmit">
				<!-- Summary header (existing contracts): key facts at a glance -->
				<div v-if="isEdit || readOnly" class="form-summary">
					<div class="form-summary__top">
						<div class="form-summary__id">
							<div class="form-summary__title">
								{{ form.name || t('nc_vertragswerk', 'Vertrag') }}
							</div>
							<div v-if="form.vendor" class="form-summary__sub">
								{{ form.vendor }}
							</div>
						</div>
						<span class="cm-chip" :class="'cm-chip--' + summaryChip.cls">{{ summaryChip.label }}</span>
					</div>
					<div class="form-summary__facts">
						<div class="fact">
							<span>{{ t('nc_vertragswerk', 'Befristung') }}</span>
							<b>{{ summaryTypeLabel }}</b>
						</div>
						<div v-if="endDateApplicable && summaryEndDate" class="fact">
							<span>{{ form.contractType === 'auto_renewal' ? t('nc_vertragswerk', 'Endet') : t('nc_vertragswerk', 'Läuft aus') }}</span>
							<b>{{ formatDateDisplay(summaryEndDate) }}</b>
						</div>
						<div v-if="showCancellationDeadline" class="fact">
							<span>{{ t('nc_vertragswerk', 'Kündbar bis') }}</span>
							<b>{{ calculatedCancellationDeadline }}</b>
						</div>
						<div v-if="form.cost" class="fact">
							<span>{{ t('nc_vertragswerk', 'Kosten') }}</span>
							<b>{{ summaryCostLabel }}</b>
						</div>
					</div>
				</div>

				<!-- AI Extraction -->
				<div v-if="aiAvailable && !isEdit && !readOnly" class="form-section ai-section">
					<div class="ai-extract-row">
						<NcButton variant="secondary"
							:disabled="extracting"
							@click="analyzeDocument">
							<template #icon>
								<NcLoadingIcon v-if="extracting" :size="20" />
								<FileSearchIcon v-else :size="20" />
							</template>
							{{ extracting ? t('nc_vertragswerk', 'Analysiere...') : t('nc_vertragswerk', 'Dokument analysieren') }}
						</NcButton>
						<span class="ai-hint">{{ t('nc_vertragswerk', 'PDF-Vertrag analysieren und Felder automatisch ausfüllen') }}</span>
					</div>
					<p v-if="extractionNotes" class="extraction-notes">
						{{ extractionNotes }}
					</p>
				</div>

				<!-- Basic Info -->
				<div class="form-section">
					<h3>{{ t('nc_vertragswerk', 'Grunddaten') }}</h3>

					<div class="cm-field-row">
						<label class="cm-field">
							<span>{{ t('nc_vertragswerk', 'Vertragsbezeichnung') + ' *' }}</span>
							<input v-model="form.name"
								class="cm-input"
								:disabled="readOnly"
								:placeholder="t('nc_vertragswerk', 'z.B. Microsoft 365 Business')">
						</label>
						<label class="cm-field">
							<span>{{ t('nc_vertragswerk', 'Vertragspartner') + ' *' }}</span>
							<input v-model="form.vendor"
								class="cm-input"
								list="cm-vendor-list"
								:disabled="readOnly"
								:placeholder="t('nc_vertragswerk', 'z.B. Microsoft')">
							<datalist id="cm-vendor-list">
								<option v-for="opt in vendorOptions" :key="opt.label" :value="opt.label" />
							</datalist>
						</label>
					</div>

					<div v-if="showContactField" class="cm-field">
						<span>{{ t('nc_vertragswerk', 'Kontakt im Adressbuch') }}</span>
						<div v-if="!readOnly && contactsAvailable" class="contact-picker">
							<NcSelect v-model="contactSelection"
								class="cm-select"
								:options="contactResults"
								:loading="contactSearching"
								:filterable="false"
								:placeholder="t('nc_vertragswerk', 'Kontakt suchen...')"
								label="name"
								track-by="uid"
								:clearable="true"
								input-id="contract-contact"
								@search="onContactSearch">
								<template #option="option">
									<span class="contact-option">
										{{ option.name }}
										<small v-if="contactSubline(option)">{{ contactSubline(option) }}</small>
									</span>
								</template>
							</NcSelect>
							<!-- Kontakt direkt anlegen (fds2610/contractmanager#3) -->
							<button type="button"
								class="contact-add"
								:aria-label="t('nc_vertragswerk', 'Neuen Kontakt anlegen')"
								:title="t('nc_vertragswerk', 'Neuen Kontakt anlegen')"
								data-test="contact-add"
								@click="showNewContact = true">
								<PlusCircleIcon :size="26" />
							</button>
						</div>
						<NewContactDialog v-if="showNewContact"
							:organization="form.vendor"
							return-focus="#contract-contact"
							@ready="el => extraTrapElements = [el]"
							@close="closeNewContact"
							@created="onContactCreated" />
						<VendorContactCard v-if="form.vendorContactUid"
							:contact="linkedContact"
							:loading="contactResolving" />
						<p v-else class="cm-hint">
							{{ t('nc_vertragswerk', 'Optional. E-Mail, Telefon und Adresse kommen dann aus dem Adressbuch.') }}
						</p>
					</div>

					<div class="cm-field-row">
						<label class="cm-field">
							<span>{{ t('nc_vertragswerk', 'Vertragsnummer') }}</span>
							<input v-model="form.contractNumber"
								class="cm-input"
								maxlength="255"
								data-test="contract-number"
								:disabled="readOnly"
								:placeholder="t('nc_vertragswerk', 'Optional, z.B. Policennummer')">
						</label>
						<label class="cm-field">
							<span>{{ t('nc_vertragswerk', 'Kundennummer') }}</span>
							<input v-model="form.customerNumber"
								class="cm-input"
								maxlength="255"
								data-test="customer-number"
								:disabled="readOnly"
								:placeholder="t('nc_vertragswerk', 'Optional')">
						</label>
					</div>

					<div class="cm-field-row">
						<label class="cm-field">
							<span>{{ t('nc_vertragswerk', 'Kategorie') }}</span>
							<select v-model="form.areaId"
								class="cm-input"
								data-test="area-select"
								:disabled="readOnly">
								<option v-for="opt in areaOptions" :key="String(opt.value)" :value="opt.value">
									{{ opt.label }}
								</option>
							</select>
						</label>
						<label class="cm-field">
							<span>{{ t('nc_vertragswerk', 'Vertragsart') }}</span>
							<select v-model="form.categoryId"
								class="cm-input"
								data-test="category-select"
								:disabled="readOnly">
								<option v-for="opt in categoryOptions" :key="String(opt.value)" :value="opt.value">
									{{ opt.label }}
								</option>
							</select>
						</label>
					</div>

					<div class="cm-field-row">
						<label class="cm-field">
							<span>{{ t('nc_vertragswerk', 'Status') }}</span>
							<select v-model="form.contractStatus"
								class="cm-input"
								:disabled="readOnly"
								@change="onStatusChange">
								<option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
									{{ opt.label }}
								</option>
							</select>
						</label>
					</div>

					<InsuranceDetails v-if="isInsurance"
						v-model:persons="form.insurancePersons"
						v-model:benefits="form.insuranceBenefits"
						:read-only="readOnly" />

					<div v-if="hasCustomFields" class="cm-field-row cm-field-row--custom">
						<label v-if="customFieldLabels.customFieldLabel1" class="cm-field">
							<span>{{ customFieldLabels.customFieldLabel1 }}</span>
							<input v-model="form.customField1" class="cm-input" :disabled="readOnly">
						</label>
						<label v-if="customFieldLabels.customFieldLabel2" class="cm-field">
							<span>{{ customFieldLabels.customFieldLabel2 }}</span>
							<input v-model="form.customField2" class="cm-input" :disabled="readOnly">
						</label>
						<label v-if="customFieldLabels.customFieldLabel3" class="cm-field">
							<span>{{ customFieldLabels.customFieldLabel3 }}</span>
							<input v-model="form.customField3" class="cm-input" :disabled="readOnly">
						</label>
					</div>
				</div>

				<!-- Dates -->
				<div class="form-section">
					<h3>{{ t('nc_vertragswerk', 'Laufzeit & Kündigung') }}</h3>

					<label class="cm-field">
						<span>{{ t('nc_vertragswerk', 'Befristung') + ' *' }}</span>
						<select v-model="form.contractType" class="cm-input" :disabled="readOnly">
							<option v-for="opt in contractTypeOptions" :key="opt.value" :value="opt.value">
								{{ opt.label }}
							</option>
						</select>
					</label>

					<div class="cm-field-row">
						<label class="cm-field">
							<span>{{ t('nc_vertragswerk', 'Startdatum') + ' *' }}</span>
							<input v-model="startDateStr"
								type="date"
								class="cm-input"
								:disabled="readOnly">
						</label>
						<div class="cm-field">
							<span>{{ t('nc_vertragswerk', 'Enddatum') }}</span>
							<div class="cm-inline">
								<input v-model="endDateStr"
									type="date"
									class="cm-input"
									:disabled="readOnly || !endDateApplicable">
								<NcButton v-if="endDateApplicable && form.endDate && !readOnly"
									variant="tertiary"
									:title="t('nc_vertragswerk', 'Enddatum entfernen (unbefristet)')"
									@click="form.endDate = null">
									<template #icon>
										<Close :size="20" />
									</template>
								</NcButton>
							</div>
							<p v-if="!endDateApplicable" class="cm-hint">
								{{ t('nc_vertragswerk', 'Unbefristete Verträge haben kein Enddatum.') }}
							</p>
						</div>
					</div>

					<NcNoteCard v-if="dateError" type="error">
						{{ dateError }}
					</NcNoteCard>

					<div v-if="form.contractType === 'auto_renewal'" class="cm-field-row">
						<div class="cm-field">
							<span>{{ t('nc_vertragswerk', 'Kündigungsfrist') }}</span>
							<div class="cm-inline">
								<input v-model="form.cancellationPeriodValue"
									type="number"
									min="1"
									class="cm-input cm-input--num"
									:disabled="readOnly">
								<select v-model="form.cancellationPeriodUnit" class="cm-input" :disabled="readOnly">
									<option v-for="opt in periodUnitOptions" :key="opt.value" :value="opt.value">
										{{ opt.label }}
									</option>
								</select>
							</div>
						</div>
						<div class="cm-field">
							<span>{{ t('nc_vertragswerk', 'Verlängerung') }}</span>
							<div class="cm-inline">
								<input v-model="form.renewalPeriodValue"
									type="number"
									min="1"
									class="cm-input cm-input--num"
									:disabled="readOnly">
								<select v-model="form.renewalPeriodUnit" class="cm-input" :disabled="readOnly">
									<option v-for="opt in periodUnitOptions" :key="opt.value" :value="opt.value">
										{{ opt.label }}
									</option>
								</select>
							</div>
						</div>
					</div>

					<div v-if="form.contractType === 'auto_renewal'" class="cm-field-row">
						<div class="cm-field">
							<span>{{ t('nc_vertragswerk', 'Kündigen zum') }}</span>
							<select v-model="form.cancellationDeadlineType" class="cm-input" :disabled="readOnly">
								<option v-for="opt in cancellationDeadlineTypeOptions" :key="opt.value" :value="opt.value">
									{{ opt.label }}
								</option>
							</select>
							<p v-if="form.cancellationDeadlineType === 'month_end'" class="cm-hint">
								{{ t('nc_vertragswerk', 'Zum Monatsende: Die Kündigungsfrist endet am letzten Tag des Monats.') }}
							</p>
						</div>
						<label v-if="showCancellationDeadline" class="cm-field">
							<span>{{ t('nc_vertragswerk', 'Kündbar bis (berechnet)') }}</span>
							<input :value="calculatedCancellationDeadline" class="cm-input" disabled>
						</label>
					</div>
				</div>

				<!-- Cancellation (#136) -->
				<div class="form-section">
					<h3>{{ t('nc_vertragswerk', 'Kündigung') }}</h3>

					<div class="cm-field-row">
						<div class="cm-field">
							<span>{{ t('nc_vertragswerk', 'Gekündigt am') }}</span>
							<div class="cm-inline">
								<input v-model="cancelledOnStr"
									type="date"
									class="cm-input"
									:disabled="readOnly">
								<NcButton v-if="form.cancelledOn && !readOnly"
									variant="tertiary"
									:title="t('nc_vertragswerk', 'Kündigung entfernen')"
									@click="onCancelledOnInput(null)">
									<template #icon>
										<Close :size="20" />
									</template>
								</NcButton>
							</div>
						</div>
						<div v-if="form.cancelledOn" class="cm-field">
							<span>{{ t('nc_vertragswerk', 'Gekündigt zum') }}</span>
							<div class="cm-inline">
								<input v-model="cancelledToStr"
									type="date"
									class="cm-input"
									:disabled="readOnly">
								<NcButton v-if="form.cancelledTo && !readOnly"
									variant="tertiary"
									:title="t('nc_vertragswerk', 'Datum entfernen')"
									@click="form.cancelledTo = null">
									<template #icon>
										<Close :size="20" />
									</template>
								</NcButton>
							</div>
						</div>
					</div>

					<p v-if="!readOnly" class="cm-hint">
						{{ t('nc_vertragswerk', 'Mit „Gekündigt am" wird der Vertrag am Laufzeitende automatisch beendet und archiviert. „Gekündigt zum" beendet ihn stattdessen zu diesem Datum (z. B. bei Sonderkündigung).') }}
					</p>
				</div>

				<!-- Kosten -->
				<div class="form-section">
					<h3>{{ t('nc_vertragswerk', 'Kosten') }}</h3>
					<div class="cm-field-row">
						<label class="cm-field">
							<span>{{ t('nc_vertragswerk', 'Betrag') }}</span>
							<!-- Bewusst type="text": bei type="number" castet Vue den v-model-Wert
								 zur Zahl, und aus 10,50 wird sichtbar 10,5 (#305). -->
							<input v-model="form.cost"
								type="text"
								inputmode="decimal"
								class="cm-input"
								:disabled="readOnly"
								:placeholder="costPlaceholder"
								@blur="normalizeCostField">
						</label>
						<label class="cm-field">
							<span>{{ t('nc_vertragswerk', 'Währung') }}</span>
							<select v-model="form.currency" class="cm-input" :disabled="readOnly">
								<option v-for="opt in currencyOptions" :key="opt.value" :value="opt.value">
									{{ opt.label }}
								</option>
							</select>
						</label>
					</div>

					<NcNoteCard v-if="costError" type="error">
						{{ costError }}
					</NcNoteCard>

					<div class="cm-field-row">
						<label class="cm-field">
							<span>{{ t('nc_vertragswerk', 'Zahlweise') }}</span>
							<select v-model="form.costInterval" class="cm-input" :disabled="readOnly">
								<option v-for="opt in costIntervalOptions" :key="opt.value" :value="opt.value">
									{{ opt.label }}
								</option>
							</select>
						</label>
						<label class="cm-field">
							<span>{{ t('nc_vertragswerk', 'Betragsart') }}</span>
							<select v-model="form.amountType" class="cm-input" :disabled="readOnly">
								<option value="netto">{{ t('nc_vertragswerk', 'Netto') }}</option>
								<option value="brutto">{{ t('nc_vertragswerk', 'Brutto') }}</option>
							</select>
						</label>
					</div>

					<div class="cm-field-row">
						<label class="cm-field">
							<span>{{ t('nc_vertragswerk', 'Zahlungsart') }}</span>
							<select v-model="form.paymentMethod"
								class="cm-input"
								data-test="payment-method"
								:disabled="readOnly">
								<option v-for="opt in paymentMethodOptions" :key="String(opt.value)" :value="opt.value">
									{{ opt.label }}
								</option>
							</select>
						</label>
					</div>
				</div>

				<!-- Dokumente -->
				<div class="form-section">
					<h3>{{ t('nc_vertragswerk', 'Dokumente') }}</h3>
					<div class="cm-field-row">
						<div class="cm-field">
							<span>{{ t('nc_vertragswerk', 'Vertragsordner') }}</span>
							<span v-if="form.contractFolder" class="selected-path" :title="form.contractFolder">
								{{ form.contractFolder.split('/').filter(s => s).pop() }}
							</span>
							<div class="document-buttons document-buttons--compact">
								<span v-if="readOnly && !form.contractFolder" class="no-document-text">
									{{ t('nc_vertragswerk', 'Kein Ordner') }}
								</span>
								<NcButton v-else-if="form.contractFolder"
									variant="primary"
									@click="openInNextcloud(form.contractFolder)">
									<template #icon>
										<Folder :size="20" />
									</template>
									{{ t('nc_vertragswerk', 'Öffnen') }}
								</NcButton>
								<NcButton v-else
									variant="secondary"
									@click="openFolderPicker">
									<template #icon>
										<Folder :size="20" />
									</template>
									{{ t('nc_vertragswerk', 'Wählen') }}
								</NcButton>
								<NcButton v-if="form.contractFolder && !readOnly"
									variant="secondary"
									@click="openFolderPicker">
									{{ t('nc_vertragswerk', 'Ändern') }}
								</NcButton>
								<NcButton v-if="form.contractFolder && !readOnly"
									variant="tertiary"
									:title="t('nc_vertragswerk', 'Entfernen')"
									@click="form.contractFolder = ''">
									<template #icon>
										<Close :size="20" />
									</template>
								</NcButton>
							</div>
						</div>
						<div class="cm-field">
							<span>{{ t('nc_vertragswerk', 'Vertragsdokument') }}</span>
							<span v-if="form.mainDocument" class="selected-path" :title="form.mainDocument">
								<OpenInNewIcon v-if="isExternalDocument" :size="14" class="external-icon" />
								{{ documentDisplayName }}
							</span>
							<div class="document-buttons document-buttons--compact">
								<span v-if="readOnly && !form.mainDocument" class="no-document-text">
									{{ t('nc_vertragswerk', 'Kein Dokument') }}
								</span>
								<NcButton v-else-if="form.mainDocument"
									variant="primary"
									@click="openDocument(form.mainDocument)">
									<template #icon>
										<OpenInNewIcon v-if="isExternalDocument" :size="20" />
										<File v-else :size="20" />
									</template>
									{{ t('nc_vertragswerk', 'Öffnen') }}
								</NcButton>
								<template v-if="!form.mainDocument && !readOnly">
									<NcButton variant="secondary"
										@click="openFilePicker">
										<template #icon>
											<File :size="20" />
										</template>
										{{ t('nc_vertragswerk', 'Datei wählen') }}
									</NcButton>
									<NcButton variant="tertiary"
										@click="showUrlInput = !showUrlInput">
										<template #icon>
											<OpenInNewIcon :size="20" />
										</template>
										{{ t('nc_vertragswerk', 'Externer Link') }}
									</NcButton>
								</template>
								<NcButton v-if="form.mainDocument && !readOnly"
									variant="secondary"
									@click="openFilePicker">
									{{ t('nc_vertragswerk', 'Ändern') }}
								</NcButton>
								<NcButton v-if="form.mainDocument && !readOnly"
									variant="tertiary"
									:title="t('nc_vertragswerk', 'Entfernen')"
									@click="form.mainDocument = ''">
									<template #icon>
										<Close :size="20" />
									</template>
								</NcButton>
							</div>
							<div v-if="showUrlInput && !readOnly && !form.mainDocument" class="url-input-row">
								<input v-model="urlInput"
									class="cm-input"
									:placeholder="t('nc_vertragswerk', 'https://...')"
									@keydown.enter.prevent="addExternalUrl">
								<NcButton variant="primary" @click="addExternalUrl">
									{{ t('nc_vertragswerk', 'Hinzufügen') }}
								</NcButton>
							</div>
						</div>
					</div>
				</div>

				<!-- Erinnerung -->
				<div class="form-section">
					<h3>{{ t('nc_vertragswerk', 'Erinnerung') }}</h3>
					<NcNoteCard v-if="!endDateApplicable || !form.endDate" type="warning">
						{{ t('nc_vertragswerk', 'Erinnerungen sind nur mit gesetztem Enddatum möglich.') }}
					</NcNoteCard>
					<template v-else>
						<div class="reminder-switches">
							<div class="reminder-switch">
								<NcCheckboxRadioSwitch v-model="form.reminderEnabled" type="switch" :disabled="readOnly">
									{{ t('nc_vertragswerk', 'Erinnerung global aktivieren') }}
								</NcCheckboxRadioSwitch>
								<p class="reminder-switch__hint">
									{{ t('nc_vertragswerk', 'Gilt für alle Berechtigten dieses Vertrags.') }}
								</p>
							</div>
							<!-- Per-user opt-out: only for existing contracts, applies to the current user only -->
							<div v-if="isEdit && form.reminderEnabled" class="reminder-switch">
								<NcCheckboxRadioSwitch :model-value="reminderOptedOut"
									type="switch"
									:disabled="optOutSaving"
									@update:model-value="onReminderOptOutChange">
									{{ t('nc_vertragswerk', 'Mich nicht an diesen Vertrag erinnern') }}
								</NcCheckboxRadioSwitch>
								<p class="reminder-switch__hint">
									{{ t('nc_vertragswerk', 'Betrifft nur Ihre eigene Erinnerung, andere Berechtigte bleiben informiert.') }}
								</p>
							</div>
						</div>
						<label v-if="form.reminderEnabled" class="cm-field reminder-lead">
							<span>{{ t('nc_vertragswerk', 'Vorlaufzeit') }}</span>
							<div class="reminder-lead__row">
								<input v-model="form.reminderDays"
									type="number"
									min="0"
									class="cm-input cm-input--num"
									:disabled="readOnly"
									:placeholder="String(defaultReminderDays)">
								<span class="reminder-lead__suffix">{{ t('nc_vertragswerk', 'Tage vor Ablauf') }}</span>
							</div>
							<p class="cm-hint">
								{{ t('nc_vertragswerk', 'Standard: {days} Tage.', { days: defaultReminderDays }) }}
							</p>
						</label>
					</template>
				</div>

				<!-- Notes -->
				<div class="form-section">
					<h3>{{ t('nc_vertragswerk', 'Notizen') }}</h3>

					<!-- eslint-disable vue/no-v-html -- linkifyText escapes HTML before linkifying -->
					<div v-if="readOnly && form.notes"
						class="notes-readonly"
						:aria-label="t('nc_vertragswerk', 'Zusätzliche Notizen')"
						v-html="linkifiedNotes" />
					<!-- eslint-enable vue/no-v-html -->
					<textarea v-else
						v-model="form.notes"
						class="cm-input notes-textarea"
						:maxlength="5000"
						:disabled="readOnly"
						:placeholder="t('nc_vertragswerk', 'Zusätzliche Notizen...')"
						rows="4" />
				</div>

				<!-- Zugriff & Zuständigkeit (Meta) -->
				<div class="form-section">
					<h3>{{ t('nc_vertragswerk', 'Zugriff & Zuständigkeit') }}</h3>
					<div class="cm-field">
						<span>{{ t('nc_vertragswerk', 'Zuständig') }}</span>
						<NcSelect v-if="!readOnly"
							v-model="form.responsiblePrincipal"
							class="cm-select"
							:options="responsibleSearchResults"
							:loading="responsibleSearching"
							:placeholder="t('nc_vertragswerk', 'Benutzer suchen...')"
							label="displayName"
							track-by="id"
							:clearable="true"
							@open="onResponsibleOpen"
							@search="onResponsibleSearch" />
						<span v-else>{{ form.responsiblePrincipal ? form.responsiblePrincipal.displayName : t('nc_vertragswerk', 'Ersteller') }}</span>
						<p v-if="!readOnly" class="cm-hint">
							{{ t('nc_vertragswerk', 'Wer diesen Vertrag betreut. Leer lassen, dann bleibt der Ersteller zuständig.') }}
						</p>
					</div>
					<div class="cm-field cm-field--switch">
						<NcCheckboxRadioSwitch v-model="form.isPrivate" :disabled="readOnly">
							<template #icon>
								<LockIcon v-if="form.isPrivate" :size="20" />
								<LockOpenVariantIcon v-else :size="20" />
							</template>
							{{ form.isPrivate
								? t('nc_vertragswerk', 'Privater Vertrag (nur für mich sichtbar)')
								: t('nc_vertragswerk', 'Öffentlicher Vertrag (für alle Berechtigten sichtbar)') }}
						</NcCheckboxRadioSwitch>
					</div>
				</div>

				<!-- Actions -->
				<div class="form-actions">
					<NcButton v-if="readOnly" variant="primary" @click="$emit('close')">
						{{ t('nc_vertragswerk', 'Schließen') }}
					</NcButton>
					<template v-else>
						<NcButton variant="tertiary" @click="$emit('close')">
							{{ t('nc_vertragswerk', 'Abbrechen') }}
						</NcButton>
						<NcButton variant="primary" type="submit" :disabled="!isValid || loading">
							<template #icon>
								<NcLoadingIcon v-if="loading" :size="20" />
							</template>
							{{ isEdit ? t('nc_vertragswerk', 'Speichern') : t('nc_vertragswerk', 'Erstellen') }}
						</NcButton>
					</template>
				</div>
			</form>
		</div>
	</NcModal>
</template>

<script>
import NcModal from '@nextcloud/vue/components/NcModal'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { getFilePickerBuilder, showSuccess, showError, showWarning } from '@nextcloud/dialogs'
import { mapState } from 'pinia'
import { useAreasStore } from '../store/areas'
import { paymentMethodOptions } from '../utils/paymentMethod'
import { useCategoriesStore } from '../store/categories'
import Folder from 'vue-material-design-icons/FolderOutline.vue'
import File from 'vue-material-design-icons/FileOutline.vue'
import Close from 'vue-material-design-icons/Close.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import LockIcon from 'vue-material-design-icons/LockOutline.vue'
import LockOpenVariantIcon from 'vue-material-design-icons/LockOpenVariantOutline.vue'
import FileSearchIcon from 'vue-material-design-icons/FileSearchOutline.vue'
import { generateUrl } from '@nextcloud/router'
import { getCanonicalLocale } from '@nextcloud/l10n'
import { isPlanned } from '../utils/contractStatus'
import { loadState } from '@nextcloud/initial-state'
import { formatDate, formatDateForInput, parseLocalDate } from '../utils/dateUtils.js'
import { parsePeriod, calculateCancellationDeadline, getEffectiveEndDate } from '../utils/periodUtils.js'
import { isUrl, isInternalUrl, getDisplayName, openDocumentInNewTab } from '../utils/documentUtils.js'
import { linkifyText } from '../utils/linkify.js'
import { reminderEnabledForEndDate } from '../utils/reminderForm'
import { isEndDateApplicable, endDateForSave, effectiveEndForForm } from '../utils/contractFormRules'
import { normalizeCostInput, costForApi, costForInput, costValidationError } from '../utils/costFormat'
import ContractService from '../services/ContractService'
import ExtractionService from '../services/ExtractionService'
import SettingsService from '../services/SettingsService'
import ContactService from '../services/ContactService'
import NewContactDialog from './NewContactDialog.vue'
import PlusCircleIcon from 'vue-material-design-icons/PlusCircle.vue'
import VendorContactCard from './VendorContactCard.vue'
import InsuranceDetails from './InsuranceDetails.vue'
import { isInsuranceCategoryName, personsForForm, benefitsForForm, personsForApi, benefitsForApi } from '../utils/insurance'

export default {
	name: 'ContractForm',
	components: {
		NewContactDialog,
		PlusCircleIcon,
		NcModal,
		NcButton,
		NcSelect,
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
		NcNoteCard,
		Folder,
		File,
		Close,
		OpenInNewIcon,
		LockIcon,
		LockOpenVariantIcon,
		FileSearchIcon,
		VendorContactCard,
		InsuranceDetails,
	},
	props: {
		show: {
			type: Boolean,
			default: false,
		},
		contract: {
			type: Object,
			default: null,
		},
		loading: {
			type: Boolean,
			default: false,
		},
		readOnly: {
			type: Boolean,
			default: false,
		},
	},
	emits: ['close', 'submit'],
	data() {
		const prefs = loadState('nc_vertragswerk', 'userPreferences', { defaultAmountType: 'netto' })
		const defaultAmountType = prefs.defaultAmountType || 'netto'
		return {
			defaultAmountType,
			form: this.getInitialForm(defaultAmountType),
			aiAvailable: false,
			extracting: false,
			extractionNotes: null,
			showUrlInput: false,
			urlInput: '',
			customFieldLabels: {
				customFieldLabel1: '',
				customFieldLabel2: '',
				customFieldLabel3: '',
			},
			vendorOptions: [],
			reminderOptedOut: false,
			optOutSaving: false,
			defaultReminderDays: 14,
			responsibleSearchResults: [],
			responsibleSearching: false,
			responsibleInitialLoaded: false,
			contactsAvailable: false,
			contactResults: [],
			contactSearching: false,
			linkedContact: null,
			showNewContact: false,
			// The nested „Neuer Kontakt“ modal sits outside this one in the DOM;
			// without this the focus trap here pulls the focus back (#3).
			extraTrapElements: [],
			contactResolving: false,
		}
	},
	computed: {
		...mapState(useCategoriesStore, ['allCategories']),
		...mapState(useAreasStore, ['allAreas', 'defaultAreaId']),
		areaOptions() {
			return [
				{ value: null, label: t('nc_vertragswerk', 'Keine Kategorie') },
				...this.allAreas.map(a => ({ value: a.id, label: a.name })),
			]
		},
		paymentMethodOptions() {
			return paymentMethodOptions()
		},
		isEdit() {
			return this.contract !== null && this.contract.id != null
		},
		showContactField() {
			return !!this.form.vendorContactUid || (!this.readOnly && this.contactsAvailable)
		},
		contactSelection: {
			get() {
				if (!this.form.vendorContactUid) {
					return null
				}
				return {
					uid: this.form.vendorContactUid,
					name: this.linkedContact ? this.linkedContact.name : t('nc_vertragswerk', 'Verknüpfter Kontakt'),
				}
			},
			set(option) {
				this.form.vendorContactUid = option ? option.uid : null
				if (option && !this.form.vendor.trim()) {
					this.form.vendor = option.organization || option.name
				}
			},
		},
		// Accessible name of the dialog. Rendered visually hidden and referenced
		// via NcModal's label-id — NcModal's own :name would render a visible
		// <h2> in the header, which on NC 34 overlaps the centered search bar
		// (#337). The visible identity comes from the summary header (edit/view)
		// instead; here we only keep the dialog labelled for screen readers.
		modalTitle() {
			if (this.readOnly) {
				return t('nc_vertragswerk', 'Vertragsdetails')
			}
			return this.isEdit
				? t('nc_vertragswerk', 'Vertrag bearbeiten')
				: t('nc_vertragswerk', 'Neuer Vertrag')
		},
		documentDisplayName() {
			return getDisplayName(this.form.mainDocument)
		},
		linkifiedNotes() {
			return linkifyText(this.form.notes)
		},
		// Native <input type="date"> binds to a "yyyy-mm-dd" string; the form stores
		// Date objects, so proxy through formatDateForInput/parseLocalDate.
		startDateStr: {
			get() {
				return this.form.startDate ? formatDateForInput(this.form.startDate) : ''
			},
			set(value) {
				this.form.startDate = value ? parseLocalDate(value) : null
			},
		},
		endDateStr: {
			get() {
				if (!this.endDateApplicable) return ''
				return this.form.endDate ? formatDateForInput(this.form.endDate) : ''
			},
			set(value) {
				this.form.endDate = value ? parseLocalDate(value) : null
			},
		},
		cancelledOnStr: {
			get() {
				return this.form.cancelledOn ? formatDateForInput(this.form.cancelledOn) : ''
			},
			set(value) {
				this.onCancelledOnInput(value ? parseLocalDate(value) : null)
			},
		},
		cancelledToStr: {
			get() {
				return this.form.cancelledTo ? formatDateForInput(this.form.cancelledTo) : ''
			},
			set(value) {
				this.form.cancelledTo = value ? parseLocalDate(value) : null
			},
		},
		isExternalDocument() {
			return isUrl(this.form.mainDocument) && !isInternalUrl(this.form.mainDocument)
		},
		// Das Betragsfeld ist ein Textfeld (#305), der Browser weist unlesbare
		// Eingaben also nicht mehr selbst ab. Ohne diese Pruefung liefe der Wert
		// bis in die DECIMAL(10,2)-Spalte und scheiterte erst dort (#315).
		costError() {
			switch (costValidationError(this.form.cost)) {
			case 'format':
				return t('nc_vertragswerk', 'Bitte einen Betrag wie 10,50 eingeben')
			case 'range':
				return t('nc_vertragswerk', 'Der Betrag darf höchstens 99.999.999,99 betragen')
			default:
				return ''
			}
		},
		isValid() {
			return (
				this.form.name.trim() !== ''
				&& this.costError === ''
				&& this.form.vendor.trim() !== ''
				&& this.form.startDate !== null
				&& !this.dateError
				&& this.form.contractType !== null
				&& (this.form.contractType !== 'auto_renewal' || (
					this.form.cancellationPeriodValue !== ''
					&& this.form.cancellationPeriodUnit !== null
				))
			)
		},
		// Unbefristete Vertraege haben kein Enddatum (#257): Feld deaktiviert,
		// ein in-memory gehaltener Wert wird ignoriert und nie gespeichert.
		endDateApplicable() {
			return isEndDateApplicable(this.form.contractType)
		},
		dateError() {
			if (this.endDateApplicable && this.form.startDate && this.form.endDate && this.form.startDate >= this.form.endDate) {
				return t('nc_vertragswerk', 'Enddatum muss nach dem Startdatum liegen. Zum Entfernen des Enddatums das ×-Symbol neben dem Feld nutzen.')
			}
			return null
		},
		// --- Summary-Header (Eckdaten oben im Modal) ---
		summaryChip() {
			if (isPlanned({ status: this.form.contractStatus, startDate: this.form.startDate })) {
				return { cls: 'planned', label: t('nc_vertragswerk', 'Geplant') }
			}
			if (this.form.contractStatus === 'cancelled') {
				return { cls: 'cancelled', label: t('nc_vertragswerk', 'Gekündigt') }
			}
			if (this.form.contractStatus === 'ended') {
				return { cls: 'ended', label: t('nc_vertragswerk', 'Beendet') }
			}
			if (this.form.contractType === 'fixed') {
				return { cls: 'active-fixed', label: t('nc_vertragswerk', 'Laufend') }
			}
			return { cls: 'active', label: t('nc_vertragswerk', 'Laufend') }
		},
		summaryEndDate() {
			return effectiveEndForForm({
				endDate: this.form.endDate,
				contractType: this.form.contractType,
				renewalPeriod: this.form.renewalPeriodValue && this.form.renewalPeriodUnit
					? `${this.form.renewalPeriodValue} ${this.form.renewalPeriodUnit}`
					: null,
				status: this.form.contractStatus,
				cancelledOn: this.form.cancelledOn,
				cancelledTo: this.form.cancelledTo,
			})
		},
		summaryTypeLabel() {
			const opt = this.contractTypeOptions.find(o => o.value === this.form.contractType)
			return opt ? opt.label : '—'
		},
		costPlaceholder() {
			return costForInput('0')
		},
		summaryCostLabel() {
			const normalized = normalizeCostInput(this.form.cost)
			const amount = normalized === '' ? NaN : Number(normalized)
			if (!Number.isFinite(amount)) return '—'
			return new Intl.NumberFormat(getCanonicalLocale(), { style: 'currency', currency: this.form.currency || 'EUR' }).format(amount)
		},
		// Personen und Leistungen gibt es nur bei Versicherungen
		isInsurance() {
			if (this.form.categoryId == null) return false
			const category = this.allCategories.find(c => c.id === this.form.categoryId)
			return isInsuranceCategoryName(category?.name)
		},
		categoryOptions() {
			return [
				{ value: null, label: t('nc_vertragswerk', 'Keine Vertragsart') },
				...this.allCategories.map((c) => ({
					value: c.id,
					label: c.name,
				})),
			]
		},
		contractTypeOptions() {
			return [
				{ value: 'fixed', label: t('nc_vertragswerk', 'Befristet') },
				{ value: 'auto_renewal', label: t('nc_vertragswerk', 'Automatische Verlängerung') },
				{ value: 'unlimited', label: t('nc_vertragswerk', 'Unbefristet') },
			]
		},
		cancellationDeadlineTypeOptions() {
			return [
				{ value: 'normal', label: t('nc_vertragswerk', 'Tagesgenau') },
				{ value: 'month_end', label: t('nc_vertragswerk', 'Zum Monatsende') },
			]
		},
		currencyOptions() {
			return [
				{ value: 'EUR', label: 'EUR' },
				{ value: 'USD', label: 'USD' },
				{ value: 'CHF', label: 'CHF' },
				{ value: 'GBP', label: 'GBP' },
			]
		},
		costIntervalOptions() {
			return [
				{ value: 'weekly', label: t('nc_vertragswerk', 'Wöchentlich') },
				{ value: 'monthly', label: t('nc_vertragswerk', 'Monatlich') },
				{ value: 'quarterly', label: t('nc_vertragswerk', 'Quartalsweise') },
				{ value: 'semi_annual', label: t('nc_vertragswerk', 'Halbjährlich') },
				{ value: 'yearly', label: t('nc_vertragswerk', 'Jährlich') },
				{ value: 'one_time', label: t('nc_vertragswerk', 'Einmalig') },
			]
		},
		periodUnitOptions() {
			return [
				{ value: 'days', label: t('nc_vertragswerk', 'Tage') },
				{ value: 'weeks', label: t('nc_vertragswerk', 'Wochen') },
				{ value: 'months', label: t('nc_vertragswerk', 'Monate') },
				{ value: 'years', label: t('nc_vertragswerk', 'Jahre') },
			]
		},
		statusOptions() {
			return [
				{ value: 'active', label: t('nc_vertragswerk', 'Laufend') },
				{ value: 'cancelled', label: t('nc_vertragswerk', 'Gekündigt') },
				{ value: 'ended', label: t('nc_vertragswerk', 'Beendet') },
			]
		},
		showCancellationDeadline() {
			return this.form.contractType === 'auto_renewal' && this.calculatedCancellationDeadline
		},
		hasCustomFields() {
			return this.customFieldLabels.customFieldLabel1
				|| this.customFieldLabels.customFieldLabel2
				|| this.customFieldLabels.customFieldLabel3
		},
		calculatedCancellationDeadline() {
			if (!this.form.endDate || !this.form.cancellationPeriodValue || !this.form.cancellationPeriodUnit) {
				return null
			}
			const periodString = `${this.form.cancellationPeriodValue} ${this.form.cancellationPeriodUnit}`
			const renewalPeriod = this.form.renewalPeriodValue && this.form.renewalPeriodUnit
				? `${this.form.renewalPeriodValue} ${this.form.renewalPeriodUnit}`
				: null
			const deadline = calculateCancellationDeadline(this.form.endDate, periodString, this.form.contractType, renewalPeriod, { deadlineType: this.form.cancellationDeadlineType })
			return deadline ? formatDate(deadline) : null
		},
	},
	watch: {
		show(newVal) {
			if (newVal) {
				this.form = this.getInitialForm(this.defaultAmountType)
				this.responsibleInitialLoaded = false
				this.responsibleSearchResults = []
			}
		},
		contract: {
			immediate: true,
			handler(newVal) {
				if (newVal) {
					this.form = this.contractToForm(newVal)
				}
			},
		},
		'form.vendorContactUid': {
			immediate: true,
			handler(uid) {
				this.resolveContact(uid)
			},
		},
		'form.endDate'(newVal) {
			// Ohne Enddatum keine Erinnerung. Bei vorhandenem Enddatum bleibt der
			// gespeicherte Zustand erhalten (kein Auto-Aktivieren beim Laden, #180).
			this.form.reminderEnabled = reminderEnabledForEndDate(this.form.reminderEnabled, newVal)
		},
	},
	async created() {
		try {
			const status = await ExtractionService.getStatus()
			this.aiAvailable = status.configured
		} catch (e) {
			this.aiAvailable = false
		}
		try {
			const settings = await SettingsService.getUserSettings()
			if (settings.customFieldLabels) {
				this.customFieldLabels = settings.customFieldLabels
			}
			// Resolved default lead time (personal override or global), shown as the
			// placeholder so users see what "leave empty" actually means.
			const days = Number(settings.reminderDays1)
			if (Number.isFinite(days) && days > 0) {
				this.defaultReminderDays = days
			}
		} catch (e) {
			console.debug('Failed to load custom field labels:', e)
		}
		if (!this.readOnly) {
			try {
				this.contactsAvailable = await ContactService.isAvailable()
			} catch (e) {
				this.contactsAvailable = false
			}
		}
		if (this.isEdit) {
			try {
				this.reminderOptedOut = await ContractService.getReminderOptOut(this.contract.id)
			} catch (e) {
				console.debug('Failed to load reminder opt-out state:', e)
			}
		}
	},
	mounted() {
		this.loadVendorOptions()
	},
	methods: {
		// Erst beim Verlassen des Felds formatieren, nicht beim Tippen — sonst
		// springt der Cursor waehrend der Eingabe (#305).
		normalizeCostField() {
			this.form.cost = costForInput(this.form.cost)
		},
		onEscape(event) {
			// Esc in einem offenen Dropdown (NcSelect) soll nur das Dropdown
			// schliessen, nicht das ganze Modal
			if (event.target.closest('.v-select.vs--open')) {
				return
			}
			this.$emit('close')
		},
		// Lazy-load a first batch of users when the picker is first opened, so the
		// dropdown is not empty before typing. The backend returns up to 25 users
		// for an empty query; typing then narrows it server-side.
		async onResponsibleOpen() {
			if (this.responsibleInitialLoaded) return
			this.responsibleInitialLoaded = true
			await this.fetchResponsibleUsers('')
		},
		async onResponsibleSearch(query) {
			await this.fetchResponsibleUsers(query)
		},
		async fetchResponsibleUsers(query) {
			try {
				this.responsibleSearching = true
				// Editor-zugänglicher User-Such-Endpunkt (nicht der admin-only Principal-Search)
				this.responsibleSearchResults = await ContractService.searchUsers(query || '')
			} catch (e) {
				this.responsibleSearchResults = []
			} finally {
				this.responsibleSearching = false
			}
		},
		// Neuer Kontakt (#3): zuordnen und Dialog schließen
		onContactCreated(contact) {
			this.contactResults = [{ uid: contact.uid, name: contact.name, organization: contact.organization, email: contact.emails[0] || '' }]
			this.form.vendorContactUid = contact.uid
			this.linkedContact = contact
			if (!this.form.vendor.trim()) {
				this.form.vendor = contact.organization || contact.name
			}
			this.closeNewContact()
		},
		// NcModal returns the focus to #contract-contact (return-focus)
		closeNewContact() {
			this.showNewContact = false
			this.extraTrapElements = []
		},
		async onContactSearch(query) {
			try {
				this.contactSearching = true
				this.contactResults = query.trim() ? await ContactService.search(query) : []
			} catch (e) {
				this.contactResults = []
			} finally {
				this.contactSearching = false
			}
		},
		contactSubline(option) {
			return [option.organization !== option.name ? option.organization : '', option.email]
				.filter(Boolean)
				.join(' · ')
		},
		async resolveContact(uid) {
			this.linkedContact = null
			if (!uid) {
				this.contactResolving = false
				return
			}
			this.contactResolving = true
			try {
				const contact = await ContactService.resolve(uid)
				// A slower answer for a contact picked earlier must not overwrite the current one
				if (this.form.vendorContactUid === uid) {
					this.linkedContact = contact
				}
			} catch (e) {
				console.debug('Failed to resolve contact:', e)
			} finally {
				if (this.form.vendorContactUid === uid) {
					this.contactResolving = false
				}
			}
		},
		async onReminderOptOutChange(value) {
			this.optOutSaving = true
			try {
				this.reminderOptedOut = await ContractService.setReminderOptOut(this.contract.id, value)
				showSuccess(t('nc_vertragswerk', 'Einstellung gespeichert'))
			} catch (e) {
				console.error('Failed to save reminder opt-out:', e)
				showError(t('nc_vertragswerk', 'Fehler beim Speichern'))
			} finally {
				this.optOutSaving = false
			}
		},
		async loadVendorOptions() {
			if (this.readOnly) return
			try {
				const vendors = await ContractService.getVendors()
				this.vendorOptions = Array.isArray(vendors)
					? vendors.map(v => ({ label: v }))
					: []
			} catch (e) {
				// Autocomplete is a nice-to-have — silently fall back to empty suggestions
				this.vendorOptions = []
			}
		},
		getInitialForm(defaultAmountType = 'netto') {
			return {
				name: '',
				vendor: '',
				vendorContactUid: null,
				categoryId: null,
				contractStatus: 'active',
				startDate: null,
				endDate: null,
				cancelledOn: null,
				cancelledTo: null,
				cancellationPeriodValue: '1',
				cancellationPeriodUnit: 'months',
				contractType: 'auto_renewal',
				cancellationDeadlineType: 'normal',
				renewalPeriodValue: '1',
				renewalPeriodUnit: 'months',
				cost: '',
				currency: 'EUR',
				costInterval: 'monthly',
				amountType: defaultAmountType,
				contractFolder: '',
				mainDocument: '',
				reminderEnabled: true,
				reminderDays: '',
				notes: '',
				isPrivate: false,
				responsiblePrincipal: null,
				customField1: '',
				customField2: '',
				customField3: '',
				contractNumber: '',
				areaId: this.defaultAreaId,
				paymentMethod: null,
				customerNumber: '',
				insurancePersons: personsForForm(null),
				insuranceBenefits: benefitsForForm(null),
			}
		},
		formatDateDisplay(date) {
			return formatDate(date)
		},
		parsePeriodForForm(periodString, defaultValue = '') {
			// Parse format like "3 months" into value and unit for form fields
			if (!periodString) return { value: defaultValue, unit: 'months' }
			const parsed = parsePeriod(periodString)
			if (parsed) {
				// Normalize to plural form for select options
				let unit = parsed.unit
				if (unit === 'day') unit = 'days'
				if (unit === 'week') unit = 'weeks'
				if (unit === 'month') unit = 'months'
				if (unit === 'year') unit = 'years'
				return { value: String(parsed.value), unit }
			}
			// Fallback: try to extract just the number
			const numMatch = periodString.match(/(\d+)/)
			return { value: numMatch ? numMatch[1] : '', unit: 'months' }
		},
		formatPeriod(value, unit) {
			if (!value) return null
			return `${value} ${unit}`
		},
		contractToForm(contract) {
			const cancellation = this.parsePeriodForForm(contract.cancellationPeriod, '1')
			const renewal = this.parsePeriodForForm(contract.renewalPeriod, '1')
			const startDate = parseLocalDate(contract.startDate)
			const endDate = parseLocalDate(contract.endDate)
			return {
				name: contract.name || '',
				vendor: contract.vendor || '',
				categoryId: contract.categoryId,
				contractStatus: contract.status || 'active',
				startDate,
				endDate,
				cancelledOn: parseLocalDate(contract.cancelledOn),
				cancelledTo: parseLocalDate(contract.cancelledTo),
				cancellationPeriodValue: cancellation.value,
				cancellationPeriodUnit: cancellation.unit,
				contractType: contract.contractType || 'fixed',
				cancellationDeadlineType: contract.cancellationDeadlineType || 'normal',
				renewalPeriodValue: renewal.value,
				renewalPeriodUnit: renewal.unit,
				// Normalisieren statt uebernehmen: auf SQLite liefert die
				// DECIMAL(10,2)-Spalte bereits "10.5" statt "10.50" (#305).
				cost: costForInput(contract.cost),
				currency: contract.currency || 'EUR',
				costInterval: contract.costInterval || 'monthly',
				contractFolder: contract.contractFolder || '',
				mainDocument: contract.mainDocument || '',
				reminderEnabled: contract.reminderEnabled !== false,
				reminderDays: contract.reminderDays ? String(contract.reminderDays) : '',
				notes: contract.notes || '',
				amountType: contract.amountType || 'netto',
				isPrivate: contract.isPrivate === true,
				vendorContactUid: contract.vendorContactUid || null,
				responsiblePrincipal: contract.responsibleUser
					? { id: 'user:' + contract.responsibleUser, uid: contract.responsibleUser, displayName: contract.responsibleUser, type: 'user' }
					: null,
				customField1: contract.customField1 || '',
				customField2: contract.customField2 || '',
				customField3: contract.customField3 || '',
				contractNumber: contract.contractNumber || '',
				areaId: contract.areaId ?? null,
				paymentMethod: contract.paymentMethod || null,
				customerNumber: contract.customerNumber || '',
				insurancePersons: personsForForm(contract.insurancePersons),
				insuranceBenefits: benefitsForForm(contract.insuranceBenefits),
			}
		},
		formToPayload() {
			const startDate = this.form.startDate
			const endDate = endDateForSave(this.form.contractType, this.form.endDate)
			return {
				name: this.form.name.trim(),
				vendor: this.form.vendor.trim(),
				vendorContactUid: this.form.vendorContactUid || null,
				categoryId: this.form.categoryId,
				status: this.form.contractStatus,
				startDate: startDate ? this.formatDateForApi(startDate) : null,
				endDate: endDate ? this.formatDateForApi(endDate) : null,
				cancelledOn: this.form.cancelledOn ? this.formatDateForApi(this.form.cancelledOn) : null,
				cancelledTo: this.form.cancelledOn && this.form.cancelledTo ? this.formatDateForApi(this.form.cancelledTo) : null,
				cancellationPeriod: this.form.contractType === 'auto_renewal'
					? this.formatPeriod(this.form.cancellationPeriodValue, this.form.cancellationPeriodUnit)
					: null,
				contractType: this.form.contractType,
				cancellationDeadlineType: this.form.contractType === 'auto_renewal'
					? this.form.cancellationDeadlineType
					: 'normal',
				renewalPeriod: this.form.contractType === 'auto_renewal'
					? this.formatPeriod(this.form.renewalPeriodValue, this.form.renewalPeriodUnit)
					: null,
				// Zwei Nachkommastellen, auch wenn das Feld nie verlassen wurde
				// (Speichern direkt aus dem fokussierten Feld heraus) — #305.
				cost: costForApi(this.form.cost),
				currency: this.form.currency,
				costInterval: this.form.costInterval || null,
				contractFolder: this.form.contractFolder.trim() || null,
				mainDocument: this.form.mainDocument.trim() || null,
				reminderEnabled: this.form.reminderEnabled,
				reminderDays: this.form.reminderDays ? parseInt(this.form.reminderDays, 10) : null,
				notes: this.form.notes.trim() || null,
				amountType: this.form.amountType,
				isPrivate: this.form.isPrivate,
				responsibleUser: this.form.responsiblePrincipal
					? (this.form.responsiblePrincipal.uid || String(this.form.responsiblePrincipal.id).replace('user:', ''))
					: null,
				customField1: this.form.customField1.trim() || null,
				customField2: this.form.customField2.trim() || null,
				customField3: this.form.customField3.trim() || null,
				contractNumber: this.form.contractNumber.trim() || null,
				areaId: this.form.areaId || null,
				paymentMethod: this.form.paymentMethod || null,
				customerNumber: this.form.customerNumber.trim() || null,
				// Wechselt die Kategorie weg von Versicherung, fallen die Angaben weg
				insurancePersons: this.isInsurance ? personsForApi(this.form.insurancePersons) : null,
				insuranceBenefits: this.isInsurance ? benefitsForApi(this.form.insuranceBenefits) : null,
			}
		},
		formatDateForApi(date) {
			return formatDateForInput(date)
		},
		onStatusChange() {
			// Without a cancellation date the background job never ends the contract.
			if (this.form.contractStatus === 'cancelled' && !this.form.cancelledOn) {
				const today = new Date()
				today.setHours(0, 0, 0, 0)
				this.onCancelledOnInput(today)
			}
		},
		onCancelledOnInput(value) {
			this.form.cancelledOn = value
			if (!value) {
				this.form.cancelledTo = null
				// Cancellation removed — revert auto-set status back to active
				if (this.form.contractStatus === 'cancelled') {
					this.form.contractStatus = 'active'
				}
				return
			}
			// Prefill "Gekündigt zum" with the contract's effective end date so the
			// user doesn't have to type it twice — it already follows from the
			// contract data. Only when empty, so a deliberately entered date (e.g.
			// a special termination) is never overwritten. Stays editable. (#213)
			if (!this.form.cancelledTo) {
				const renewalPeriod = this.form.renewalPeriodValue && this.form.renewalPeriodUnit
					? `${this.form.renewalPeriodValue} ${this.form.renewalPeriodUnit}`
					: null
				const effectiveEnd = getEffectiveEndDate(this.form.endDate, this.form.contractType, renewalPeriod)
				if (effectiveEnd) {
					this.form.cancelledTo = effectiveEnd
				}
			}
		},
		async openFolderPicker() {
			try {
				const picker = getFilePickerBuilder(t('nc_vertragswerk', 'Vertragsordner wählen'))
					.setMultiSelect(false)
					.setType(1)
					.allowDirectories()
					.build()
				const path = await picker.pick()
				if (path) {
					this.form.contractFolder = path
				}
			} catch (e) {
				// User cancelled - do nothing
				console.debug('Folder picker cancelled', e)
			}
		},
		async openFilePicker() {
			try {
				const picker = getFilePickerBuilder(t('nc_vertragswerk', 'Vertragsdokument wählen'))
					.setMultiSelect(false)
					.setType(1)
					.build()
				const path = await picker.pick()
				if (path) {
					this.form.mainDocument = path
				}
			} catch (e) {
				console.debug('File picker cancelled', e)
			}
		},
		addExternalUrl() {
			const url = this.urlInput.trim()
			if (!url) return
			if (!isUrl(url)) {
				showError(t('nc_vertragswerk', 'Bitte eine gültige URL eingeben (https://...)'))
				return
			}
			this.form.mainDocument = url
			this.urlInput = ''
			this.showUrlInput = false
		},
		async analyzeDocument() {
			try {
				const picker = getFilePickerBuilder(t('nc_vertragswerk', 'PDF-Vertrag auswählen'))
					.setMultiSelect(false)
					.setType(1)
					.setMimeTypeFilter(['application/pdf'])
					.build()
				const path = await picker.pick()
				if (!path) return

				this.extracting = true
				this.extractionNotes = null

				const result = await ExtractionService.extractFromPdf(path)

				if (result.success && result.data) {
					this.applyExtractedData(result.data)

					if (result.data.confidence < 0.5) {
						showWarning(t('nc_vertragswerk', 'Niedrige Erkennungsgenauigkeit – bitte alle Felder prüfen'))
					} else {
						showSuccess(t('nc_vertragswerk', 'Vertragsdaten erfolgreich erkannt'))
					}

					if (result.data.extractionNotes) {
						this.extractionNotes = result.data.extractionNotes
					}

					// Set the document as main document
					this.form.mainDocument = path
				} else {
					showError(result.error || t('nc_vertragswerk', 'Analyse fehlgeschlagen'))
				}
			} catch (e) {
				console.error('Document analysis failed:', e)
				const message = e.response?.data?.error || t('nc_vertragswerk', 'Analyse fehlgeschlagen')
				showError(message)
			} finally {
				this.extracting = false
			}
		},
		applyExtractedData(data) {
			if (data.name) this.form.name = data.name
			if (data.vendor) this.form.vendor = data.vendor
			if (data.contractType) this.form.contractType = data.contractType
			if (data.currency) this.form.currency = data.currency
			if (data.cost) this.form.cost = costForInput(data.cost)
			if (data.startDate) {
				const start = parseLocalDate(data.startDate)
				if (start && !isNaN(start.getTime())) {
					this.form.startDate = start
				}
			}
			if (data.endDate) {
				const end = parseLocalDate(data.endDate)
				if (end && !isNaN(end.getTime())) {
					this.form.endDate = end
				}
			}
			if (data.cancellationPeriod) {
				const cp = this.parsePeriodForForm(data.cancellationPeriod)
				this.form.cancellationPeriodValue = cp.value
				this.form.cancellationPeriodUnit = cp.unit
			}
			if (data.renewalPeriod) {
				const rp = this.parsePeriodForForm(data.renewalPeriod)
				this.form.renewalPeriodValue = rp.value
				this.form.renewalPeriodUnit = rp.unit
			}
			if (data.contractStatus) {
				this.form.contractStatus = data.contractStatus
			}
		},
		handleSubmit() {
			if (!this.isValid) return
			this.$emit('submit', this.formToPayload())
		},
		openInNextcloud(folder) {
			if (!folder) return
			window.open(generateUrl('/apps/files/?dir={dir}', { dir: folder }), '_blank', 'noopener,noreferrer')
		},
		openDocument(value) {
			openDocumentInNewTab(value)
		},
	},
}
</script>

<style scoped lang="scss">
.contact-picker {
	display: flex;
	align-items: center;
	gap: 6px;

	.cm-select { flex: 1 1 auto; min-width: 0; }
}

// Grüner Plus-Button wie bei den Versicherungsangaben
.contact-add {
	flex: 0 0 auto;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 36px;
	height: 36px;
	min-height: 0;
	padding: 0;
	margin: 0;
	border: none;
	border-radius: 50%;
	background: transparent;
	color: var(--color-element-success, #099f05);
	cursor: pointer;

	&:hover,
	&:focus-visible { background: var(--color-background-hover); }
}

.contract-form {
	padding: 4px 22px 0;
	max-height: min(90vh, calc(100vh - 120px));
	overflow-y: auto;
}

/* Accessible dialog title (#337): named for screen readers, never shown. */
.hidden-visually {
	position: absolute;
	width: 1px;
	height: 1px;
	margin: -1px;
	padding: 0;
	border: 0;
	overflow: hidden;
	clip: rect(0 0 0 0);
	white-space: nowrap;
}

/*
 * Read-only details view: NC renders disabled fields dimmed (muted colour +
 * 0.7 opacity), which is too faint to read as a details view. Restore full
 * contrast for the displayed values.
 */
.contract-form--readonly {
	.cm-input:disabled {
		opacity: 1;
		color: var(--color-main-text);
		-webkit-text-fill-color: var(--color-main-text);
	}

	:deep(.v-select.vs--disabled .vs__selected) {
		opacity: 1;
		color: var(--color-main-text);
		-webkit-text-fill-color: var(--color-main-text);
	}
}

.form-section {
	padding: 18px 0;
	border-top: 1px solid var(--color-border-light, var(--color-border));

	&:first-of-type { border-top: none; }

	h3 {
		display: flex;
		align-items: center;
		gap: 7px;
		margin: 0 0 14px;
		font-size: 12px;
		font-weight: 700;
		color: var(--color-text-maxcontrast);
		text-transform: uppercase;
		letter-spacing: 0.5px;
	}
}

/* Summary header with the key facts (existing contracts). */
.form-summary {
	padding: 6px 0 16px;
	border-bottom: 1px solid var(--color-border);

	&__top {
		display: flex;
		align-items: flex-start;
		gap: 12px;
		// NcModal's close button sits on top of the content in the right corner.
		padding-inline-end: 28px;
	}

	&__id { flex: 1; min-width: 0; }

	&__title { font-size: 19px; font-weight: 700; line-height: 1.2; }

	&__sub { font-size: 13px; color: var(--color-text-maxcontrast); margin-top: 2px; }

	&__facts {
		display: flex;
		gap: 26px;
		flex-wrap: wrap;
		margin-top: 14px;

		.fact { font-size: 12px; color: var(--color-text-maxcontrast); }

		.fact b {
			display: block;
			font-size: 14px;
			color: var(--color-main-text);
			font-variant-numeric: tabular-nums;
			margin-top: 2px;
		}
	}
}

/*
 * Uniform field system (modelled on the Vinarium modals): every control —
 * text, number, date, <select> and <textarea> — shares the same .cm-input
 * look so the form reads as one consistent surface instead of a mix of
 * segmented switches and dropdown pills.
 */
.cm-field {
	display: block;
	margin-bottom: 14px;

	&:last-child { margin-bottom: 0; }

	> span {
		display: block;
		font-size: 13px;
		color: var(--color-text-maxcontrast);
		margin-bottom: 4px;
	}
}

.cm-field-row {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 14px;
	align-items: start;
	margin-bottom: 14px;

	&:last-child { margin-bottom: 0; }

	.cm-field { margin-bottom: 0; }

	&--custom { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
}

.cm-input {
	width: 100%;
	min-height: 36px;
	padding: 6px 10px;
	border: 1px solid var(--color-border-dark, var(--color-border));
	border-radius: var(--border-radius, 8px);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font: inherit;
	box-sizing: border-box;

	&:hover:not(:disabled) { border-color: var(--color-border-maxcontrast, var(--color-border-dark)); }

	&:focus,
	&:focus-visible {
		outline: none;
		border-color: var(--color-primary-element);
		box-shadow: 0 0 0 2px var(--color-primary-element-light);
	}

	&:disabled { cursor: default; }
}

// NC's global stylesheet forces `appearance: none` on <select>, which strips the
// native dropdown arrow. Add an explicit chevron so selects read as dropdowns and
// stay visually distinct from the text inputs while sharing the same frame.
select.cm-input {
	cursor: pointer;
	padding-right: 32px;
	background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%238b8b8b'%3E%3Cpath d='M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z'/%3E%3C/svg%3E");
	background-repeat: no-repeat;
	background-position: right 8px center;
	background-size: 18px;
}

textarea.cm-input {
	min-height: 6em;
	max-height: 50vh;
	resize: vertical;
	line-height: 1.5;
}

.cm-inline {
	display: flex;
	align-items: center;
	gap: 8px;

	.cm-input { flex: 1 1 auto; min-width: 0; }
	.cm-input--num { flex: 0 0 84px; }
}

.cm-input--num { flex: 0 0 84px; }

.contact-option {
	display: flex;
	flex-direction: column;
	line-height: 1.3;

	small {
		color: var(--color-text-maxcontrast);
	}
}

.cm-hint {
	margin: 6px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;

	&--top { margin: 0 0 8px; }
}

.form-actions {
	position: sticky;
	bottom: 0;
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	margin: 24px -20px 0;
	padding: 16px 20px;
	border-top: 1px solid var(--color-border);
	background: var(--color-main-background);
}

/* Document pickers */
.document-buttons {
	display: flex;
	gap: 4px;
	align-items: center;
	flex-wrap: wrap;
}

.url-input-row {
	display: flex;
	gap: 8px;
	align-items: center;
	margin-top: 8px;
}

.no-document-text {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.selected-path {
	display: block;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	margin-bottom: 6px;
}

.external-icon {
	display: inline-flex;
	vertical-align: text-bottom;
	margin-right: 2px;
}

.reminder-switches {
	display: flex;
	flex-direction: column;
	gap: 14px;
}

.reminder-switch__hint {
	margin: 2px 0 0;
	padding-left: 48px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.reminder-lead {
	margin-top: 18px;
	max-width: 340px;

	&__row {
		display: flex;
		align-items: center;
		gap: 10px;
	}

	&__suffix {
		color: var(--color-text-maxcontrast);
		font-size: 14px;
		white-space: nowrap;
	}
}

.cm-field--switch {
	margin-top: 4px;
}

/*
 * NcSelect (only used for the async user search) styled to match the flat
 * .cm-input frame so it does not read as a leftover pill among the native fields.
 */
.cm-select {
	:deep(.vs__dropdown-toggle) {
		min-height: 36px;
		padding-bottom: 0;
		border: 1px solid var(--color-border-dark, var(--color-border));
		border-radius: var(--border-radius, 8px);
		background-color: var(--color-main-background);
	}

	&:hover :deep(.vs__dropdown-toggle) {
		border-color: var(--color-border-maxcontrast, var(--color-border-dark));
	}

	:deep(.vs__selected-options) {
		padding: 0 4px;
	}
}

.ai-section {
	background: var(--color-primary-element-light, #e8f0fe);
	padding: 16px;
	border-radius: 8px;
	border-top: none;
}

.ai-extract-row {
	display: flex;
	align-items: center;
	gap: 12px;
}

.ai-hint {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.extraction-notes {
	margin-top: 8px;
	padding: 8px 12px;
	background: var(--color-warning-hover, #fff3cd);
	border-radius: 4px;
	font-size: 13px;
	color: var(--color-warning-text, #856404);
}

.notes-readonly {
	width: 100%;
	min-height: 80px;
	padding: 8px 12px;
	background: var(--color-background-hover);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	font-size: 14px;
	line-height: 1.5;
	white-space: pre-wrap;
	word-wrap: break-word;

	a {
		color: var(--color-primary-element);
		text-decoration: underline;

		&:hover, &:focus {
			text-decoration: none;
		}
	}
}

@media (max-width: 720px) {
	.cm-field-row { grid-template-columns: 1fr; gap: 12px; }
}
</style>
