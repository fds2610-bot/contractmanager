<template>
	<div class="settings-view">
		<div class="settings-view__header">
			<h2>{{ t('nc_vertragswerk', 'Einstellungen') }}</h2>
		</div>

		<div class="settings-layout">
			<nav class="settings-nav">
				<div class="settings-nav-group">
					{{ t('nc_vertragswerk', 'Meine Einstellungen') }}
				</div>
				<button type="button"
					class="settings-nav-item"
					:class="{ active: activeSection === 'notifications' }"
					@click="activeSection = 'notifications'">
					<BellIcon :size="18" /> {{ t('nc_vertragswerk', 'Benachrichtigungen') }}
				</button>
				<button type="button"
					class="settings-nav-item"
					:class="{ active: activeSection === 'amount' }"
					@click="activeSection = 'amount'">
					<CashMultipleIcon :size="18" /> {{ t('nc_vertragswerk', 'Betragsangabe') }}
				</button>
				<button type="button"
					class="settings-nav-item"
					:class="{ active: activeSection === 'backup' }"
					@click="activeSection = 'backup'">
					<BackupRestoreIcon :size="18" /> {{ t('nc_vertragswerk', 'Auto-Backup') }}
				</button>
				<button type="button"
					class="settings-nav-item"
					:class="{ active: activeSection === 'calendar' }"
					@click="activeSection = 'calendar'">
					<CalendarIcon :size="18" /> {{ t('nc_vertragswerk', 'Kalender-Abo') }}
				</button>
				<template v-if="$isAdmin">
					<div class="settings-nav-group">
						{{ t('nc_vertragswerk', 'Administration') }}
					</div>
					<button type="button"
						class="settings-nav-item"
						:class="{ active: activeSection === 'permissions' }"
						@click="activeSection = 'permissions'">
						<ShieldIcon :size="18" /> {{ t('nc_vertragswerk', 'Berechtigungen') }}
					</button>
					<button type="button"
						class="settings-nav-item"
						:class="{ active: activeSection === 'transfer' }"
						@click="activeSection = 'transfer'">
						<SwapHorizontalIcon :size="18" /> {{ t('nc_vertragswerk', 'Verträge übertragen') }}
					</button>
					<button type="button"
						class="settings-nav-item"
						:class="{ active: activeSection === 'admin' }"
						@click="activeSection = 'admin'">
						<CogIcon :size="18" /> {{ t('nc_vertragswerk', 'Administrator-Einstellungen') }}
					</button>
					<button type="button"
						class="settings-nav-item"
						:class="{ active: activeSection === 'categories' }"
						@click="activeSection = 'categories'">
						<TagIcon :size="18" /> {{ t('nc_vertragswerk', 'Vertragsarten') }}
					</button>
				</template>
				<button v-else
					type="button"
					class="settings-nav-item"
					:class="{ active: activeSection === 'categories' }"
					@click="activeSection = 'categories'">
					<TagIcon :size="18" /> {{ t('nc_vertragswerk', 'Vertragsarten') }}
				</button>
				<button type="button"
					class="settings-nav-item"
					:class="{ active: activeSection === 'areas' }"
					data-test="settings-nav-areas"
					@click="activeSection = 'areas'">
					<ShapeIcon :size="18" /> {{ t('nc_vertragswerk', 'Kategorien') }}
				</button>
			</nav>

			<div class="settings-content">
				<!-- User Settings -->
				<div v-show="activeSection === 'notifications'" class="settings-section">
					<h3>{{ t('nc_vertragswerk', 'Benachrichtigungen') }}</h3>
					<p class="settings-description">
						{{ t('nc_vertragswerk', 'Legen Sie fest, für welche Verträge Sie Erinnerungen erhalten möchten, wie früh und auf welchem Weg.') }}
					</p>

					<!-- Reminder mode: which contracts -->
					<div class="settings-item">
						<label class="settings-label">{{ t('nc_vertragswerk', 'Erinnerungen für') }}</label>
						<NcCheckboxRadioSwitch v-model="reminderMode"
							value="all"
							name="reminderMode"
							type="radio"
							@update:model-value="onReminderModeChange">
							{{ t('nc_vertragswerk', 'Alle Verträge, die ich sehe') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch v-model="reminderMode"
							value="own"
							name="reminderMode"
							type="radio"
							@update:model-value="onReminderModeChange">
							{{ t('nc_vertragswerk', 'Nur meine eigenen Verträge') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch v-model="reminderMode"
							value="none"
							name="reminderMode"
							type="radio"
							@update:model-value="onReminderModeChange">
							{{ t('nc_vertragswerk', 'Keine Erinnerungen') }}
						</NcCheckboxRadioSwitch>
					</div>

					<template v-if="reminderMode !== 'none'">
						<!-- Channel: e-mail -->
						<div class="settings-item">
							<NcCheckboxRadioSwitch v-model="emailReminder" @update:model-value="onEmailReminderChange">
								{{ t('nc_vertragswerk', 'Per E-Mail benachrichtigen') }}
							</NcCheckboxRadioSwitch>
							<p class="settings-description">
								{{ t('nc_vertragswerk', 'E-Mails gehen an Ihre in Nextcloud hinterlegte Adresse.') }}
							</p>
						</div>

						<!-- Channel: personal Talk chat -->
						<div class="settings-item">
							<label class="settings-label">{{ t('nc_vertragswerk', 'Eigener Nextcloud Talk Chat (optional)') }}</label>
							<p class="settings-description">
								{{ t('nc_vertragswerk', 'Token eines Chats, in dem Sie Erinnerungen erhalten möchten (aus der Chat-URL). Leer lassen, um Talk nicht zu nutzen.') }}
							</p>
							<NcTextField v-model="userReminders.talkChatToken"
								:placeholder="t('nc_vertragswerk', 'z.B. abc123xyz')"
								class="settings-input" />
						</div>

						<!-- Personal lead time -->
						<div class="settings-item reminder-days">
							<label class="settings-label">{{ t('nc_vertragswerk', 'Eigene Vorlaufzeit (Tage vor der Frist)') }}</label>
							<p class="settings-description">
								{{ t('nc_vertragswerk', 'Leer lassen, um die Standardwerte des Administrators zu verwenden.') }}
							</p>
							<div class="reminder-inputs">
								<div class="reminder-input-group">
									<label>{{ t('nc_vertragswerk', 'Erste Erinnerung') }}</label>
									<NcTextField v-model="userReminders.reminderDays1Personal"
										type="number"
										:min="1"
										:placeholder="String(reminderDefaults.days1)"
										class="number-input" />
									<span class="unit">{{ t('nc_vertragswerk', 'Tage') }}</span>
								</div>
								<div class="reminder-input-group">
									<label>{{ t('nc_vertragswerk', 'Letzte Erinnerung') }}</label>
									<NcTextField v-model="userReminders.reminderDays2Personal"
										type="number"
										:min="1"
										:placeholder="String(reminderDefaults.days2)"
										class="number-input" />
									<span class="unit">{{ t('nc_vertragswerk', 'Tage') }}</span>
								</div>
							</div>
						</div>

						<div class="settings-actions">
							<NcButton variant="primary" :disabled="savingUserReminders" @click="saveUserReminderSettings">
								<template #icon>
									<NcLoadingIcon v-if="savingUserReminders" :size="20" />
								</template>
								{{ t('nc_vertragswerk', 'Speichern') }}
							</NcButton>
						</div>
					</template>
				</div>

				<div v-show="activeSection === 'amount'" class="settings-section">
					<h3>{{ t('nc_vertragswerk', 'Betragsangabe') }}</h3>
					<p class="settings-description">
						{{ t('nc_vertragswerk', 'Standard für neue Verträge. Pro Vertrag kann davon abgewichen werden.') }}
					</p>
					<div class="settings-item">
						<NcCheckboxRadioSwitch v-model="defaultAmountType"
							value="netto"
							name="defaultAmountType"
							type="radio"
							@update:model-value="onDefaultAmountTypeChange">
							{{ t('nc_vertragswerk', 'Netto') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch v-model="defaultAmountType"
							value="brutto"
							name="defaultAmountType"
							type="radio"
							@update:model-value="onDefaultAmountTypeChange">
							{{ t('nc_vertragswerk', 'Brutto') }}
						</NcCheckboxRadioSwitch>
					</div>
				</div>

				<!-- Auto-Backup (#296) -->
				<div v-show="activeSection === 'backup'" class="settings-section">
					<h3>{{ t('nc_vertragswerk', 'Automatisches Backup') }}</h3>
					<p class="settings-description">
						{{ t('nc_vertragswerk', 'Sichert Ihre eigenen Vertragsdaten regelmäßig als JSON-Datei in einen Nextcloud-Ordner, damit der Stand in jedem normalen Nextcloud-Backup landet.') }}
					</p>
					<div class="settings-item">
						<NcCheckboxRadioSwitch :model-value="backupEnabled" @update:model-value="onBackupEnabledChange">
							{{ t('nc_vertragswerk', 'Automatisches Backup aktivieren') }}
						</NcCheckboxRadioSwitch>
					</div>
					<template v-if="backupEnabled">
						<div class="settings-item">
							<label class="settings-label">{{ t('nc_vertragswerk', 'Zielordner') }}</label>
							<input v-model="backupFolder"
								class="settings-input"
								placeholder="/VertragsWerk-Backup"
								@blur="onBackupFolderChange">
						</div>
						<div class="settings-item">
							<label class="settings-label">{{ t('nc_vertragswerk', 'Intervall') }}</label>
							<select v-model="backupInterval" class="settings-input" @change="onBackupIntervalChange">
								<option value="daily">{{ t('nc_vertragswerk', 'Täglich') }}</option>
								<option value="weekly">{{ t('nc_vertragswerk', 'Wöchentlich') }}</option>
								<option value="monthly">{{ t('nc_vertragswerk', 'Monatlich') }}</option>
							</select>
						</div>
						<div class="settings-item">
							<label class="settings-label">{{ t('nc_vertragswerk', 'Uhrzeit') }}</label>
							<select v-model="backupHour" class="settings-input" @change="onBackupHourChange">
								<option value="">
									{{ t('nc_vertragswerk', 'Keine feste Uhrzeit') }}
								</option>
								<option v-for="hour in 24" :key="hour" :value="String(hour - 1)">
									{{ String(hour - 1).padStart(2, '0') }}:00
								</option>
							</select>
						</div>
						<p class="settings-description">
							{{ t('nc_vertragswerk', 'Es werden die letzten 30 Sicherungen behalten; ältere werden automatisch entfernt.') }}
						</p>
						<div class="settings-item">
							<label class="settings-label">{{ t('nc_vertragswerk', 'Zuletzt gesichert') }}</label>
							<span class="settings-value">{{ lastBackupText }}</span>
						</div>
						<div class="settings-item">
							<label class="settings-label">{{ t('nc_vertragswerk', 'Nächste Sicherung') }}</label>
							<span class="settings-value">{{ nextBackupText }}</span>
						</div>
						<div class="settings-item">
							<NcButton variant="secondary" :disabled="backingUpNow" @click="triggerBackupNow">
								<template #icon>
									<NcLoadingIcon v-if="backingUpNow" :size="20" />
									<BackupRestoreIcon v-else :size="20" />
								</template>
								{{ t('nc_vertragswerk', 'Jetzt sichern') }}
							</NcButton>
						</div>
					</template>

					<template v-if="canEdit">
						<h3 class="settings-subheading">
							{{ t('nc_vertragswerk', 'Backup einlesen') }}
						</h3>
						<p class="settings-description">
							{{ t('nc_vertragswerk', 'Liest eine Sicherungsdatei (JSON) aus Ihren Nextcloud-Dateien ein, auch aus einer anderen Nextcloud. Die Verträge gehören danach Ihnen. Verträge, die Sie schon haben (gleicher Name, Anbieter und Beginn), werden übersprungen.') }}
						</p>
						<div class="settings-item">
							<NcButton variant="secondary" :disabled="importBusy" @click="pickImportFile">
								<template #icon>
									<NcLoadingIcon v-if="importBusy" :size="20" />
									<UploadIcon v-else :size="20" />
								</template>
								{{ t('nc_vertragswerk', 'Sicherungsdatei wählen') }}
							</NcButton>
						</div>
					</template>
				</div>

				<!-- Kalender-Abo (#68) -->
				<div v-show="activeSection === 'calendar'" class="settings-section">
					<h3>{{ t('nc_vertragswerk', 'Kalender-Abo') }}</h3>
					<p class="settings-description">
						{{ t('nc_vertragswerk', 'Abonnieren Sie Ihre Vertragsfristen als schreibgeschützten Kalender in der Nextcloud-Kalender-App. Pro Vertrag erscheint ein Ganztags-Termin an der Kündigungsfrist (bzw. dem Vertragsende). Enthalten sind nur die Verträge, für die Sie Erinnerungen erhalten – so läuft der Kalender nicht über.') }}
					</p>
					<template v-if="calendarFeedUrl">
						<div class="settings-item">
							<label class="settings-label">{{ t('nc_vertragswerk', 'Abo-URL') }}</label>
							<div class="calendar-feed-row">
								<input ref="calendarFeedInput"
									class="settings-input calendar-feed-url"
									type="text"
									readonly
									:value="calendarFeedUrl">
								<NcButton variant="secondary" @click="copyCalendarFeedUrl">
									{{ t('nc_vertragswerk', 'Kopieren') }}
								</NcButton>
							</div>
							<p class="settings-description">
								{{ t('nc_vertragswerk', 'In der Kalender-App: „Neues Abonnement” bzw. „Abonnement aus Link” und diese URL einfügen.') }}
							</p>
						</div>
						<div class="settings-item">
							<NcButton variant="tertiary" @click="resetCalendarFeed">
								{{ t('nc_vertragswerk', 'URL neu erzeugen') }}
							</NcButton>
							<p class="settings-description">
								{{ t('nc_vertragswerk', 'Die bisherige URL wird dabei ungültig. Nutzen Sie das, falls die URL versehentlich geteilt wurde.') }}
							</p>
						</div>
					</template>
					<div v-else class="settings-item">
						<NcButton variant="primary" :disabled="generatingCalendarFeed" @click="resetCalendarFeed">
							<template #icon>
								<NcLoadingIcon v-if="generatingCalendarFeed" :size="20" />
							</template>
							{{ t('nc_vertragswerk', 'Abo-URL erzeugen') }}
						</NcButton>
						<p class="settings-description">
							{{ t('nc_vertragswerk', 'Die URL enthält einen geheimen Zugangs-Token. Geben Sie sie nicht weiter.') }}
						</p>
					</div>
				</div>

				<!-- Admin Settings -->
				<template v-if="$isAdmin">
					<!-- Permission Settings -->
					<div v-show="activeSection === 'permissions'" class="settings-section admin-section">
						<h3>
							<ShieldIcon :size="20" class="admin-icon" />
							{{ t('nc_vertragswerk', 'Berechtigungen') }}
						</h3>

						<!-- Editor Permission -->
						<div class="settings-item">
							<label class="settings-label">{{ t('nc_vertragswerk', 'Editor-Berechtigung') }}</label>
							<p class="settings-description">
								{{ t('nc_vertragswerk', 'Benutzer und Gruppen mit Editor-Rechten können alle sichtbaren Verträge erstellen und bearbeiten.') }}
							</p>
							<NcSelect v-model="permissionSettings.editors"
								:options="searchResults"
								:loading="searching"
								:placeholder="t('nc_vertragswerk', 'Benutzer oder Gruppen suchen...')"
								:multiple="true"
								label="displayName"
								track-by="id"
								class="permission-select"
								@update:model-value="onEditorsChange">
								<template #option="option">
									<div class="permission-option">
										<AccountGroupIcon v-if="option.type === 'group'" :size="20" />
										<AccountIcon v-else :size="20" />
										<span>{{ option.displayName }}</span>
										<span class="permission-option-type">
											{{ option.type === 'group' ? t('nc_vertragswerk', 'Gruppe') : t('nc_vertragswerk', 'Benutzer') }}
										</span>
									</div>
								</template>
								<template #selected-option="option">
									<div class="permission-tag">
										<AccountGroupIcon v-if="option.type === 'group'" :size="16" />
										<AccountIcon v-else :size="16" />
										<span>{{ option.displayName }}</span>
									</div>
								</template>
							</NcSelect>
						</div>

						<!-- Viewer Permission -->
						<div class="settings-item">
							<label class="settings-label">{{ t('nc_vertragswerk', 'Viewer-Berechtigung') }}</label>
							<p class="settings-description">
								{{ t('nc_vertragswerk', 'Benutzer und Gruppen mit Viewer-Rechten können alle Verträge nur ansehen.') }}
							</p>
							<NcSelect v-model="permissionSettings.viewers"
								:options="searchResults"
								:loading="searching"
								:placeholder="t('nc_vertragswerk', 'Benutzer oder Gruppen suchen...')"
								:multiple="true"
								label="displayName"
								track-by="id"
								class="permission-select"
								@update:model-value="onViewersChange">
								<template #option="option">
									<div class="permission-option">
										<AccountGroupIcon v-if="option.type === 'group'" :size="20" />
										<AccountIcon v-else :size="20" />
										<span>{{ option.displayName }}</span>
										<span class="permission-option-type">
											{{ option.type === 'group' ? t('nc_vertragswerk', 'Gruppe') : t('nc_vertragswerk', 'Benutzer') }}
										</span>
									</div>
								</template>
								<template #selected-option="option">
									<div class="permission-tag">
										<AccountGroupIcon v-if="option.type === 'group'" :size="16" />
										<AccountIcon v-else :size="16" />
										<span>{{ option.displayName }}</span>
									</div>
								</template>
							</NcSelect>
						</div>
					</div>

					<!-- Verträge übertragen -->
					<div v-show="activeSection === 'transfer'" class="settings-section admin-section">
						<h3>
							<ShieldIcon :size="20" class="admin-icon" />
							{{ t('nc_vertragswerk', 'Verträge übertragen') }}
						</h3>
						<p class="settings-description">
							{{ t('nc_vertragswerk', 'Überträgt die Zuständigkeit für alle Verträge einer Person auf eine andere, zum Beispiel bei einem Mitarbeiterwechsel. „Erstellt von" bleibt unverändert.') }}
						</p>
						<div class="settings-item">
							<label class="settings-label">{{ t('nc_vertragswerk', 'Von') }}</label>
							<NcSelect v-model="transferFrom"
								:options="transferUserResults"
								:loading="transferSearching"
								:placeholder="t('nc_vertragswerk', 'Benutzer suchen...')"
								label="displayName"
								track-by="id"
								:clearable="true"
								@open="onTransferOpen"
								@search="onTransferSearch"
								@update:model-value="onTransferFromChange" />
							<p v-if="transferCount !== null" class="settings-description">
								{{ t('nc_vertragswerk', 'Betrifft {count} Verträge', { count: transferCount }) }}
							</p>
						</div>
						<div class="settings-item">
							<label class="settings-label">{{ t('nc_vertragswerk', 'Auf') }}</label>
							<NcSelect v-model="transferTo"
								:options="transferUserResults"
								:loading="transferSearching"
								:placeholder="t('nc_vertragswerk', 'Benutzer suchen...')"
								label="displayName"
								track-by="id"
								:clearable="true"
								@open="onTransferOpen"
								@search="onTransferSearch" />
						</div>
						<div class="settings-actions">
							<NcButton variant="primary" :disabled="!canTransfer || transferring" @click="showTransferDialog = true">
								<template #icon>
									<NcLoadingIcon v-if="transferring" :size="20" />
								</template>
								{{ t('nc_vertragswerk', 'Übertragen') }}
							</NcButton>
						</div>
						<NcDialog v-if="showTransferDialog"
							:name="t('nc_vertragswerk', 'Verträge übertragen')"
							:message="t('nc_vertragswerk', '{count} Verträge von {from} auf {to} übertragen?', { count: transferCount || 0, from: transferFrom ? transferFrom.displayName : '', to: transferTo ? transferTo.displayName : '' })"
							@update:open="showTransferDialog = $event">
							<template #actions>
								<NcButton @click="showTransferDialog = false">
									{{ t('nc_vertragswerk', 'Abbrechen') }}
								</NcButton>
								<NcButton variant="primary" @click="doTransfer">
									{{ t('nc_vertragswerk', 'Übertragen') }}
								</NcButton>
							</template>
						</NcDialog>
					</div>

					<div v-show="activeSection === 'admin'" class="settings-section admin-section">
						<h3>
							<ShieldIcon :size="20" class="admin-icon" />
							{{ t('nc_vertragswerk', 'Administrator-Einstellungen') }}
						</h3>

						<!-- Reminder link health (overwrite.cli.url) -->
						<div v-if="showReminderLinkWarning" class="reminder-link-note">
							<button type="button"
								class="reminder-link-summary"
								:aria-expanded="reminderLinkExpanded ? 'true' : 'false'"
								@click="reminderLinkExpanded = !reminderLinkExpanded">
								<InformationOutlineIcon :size="18" class="reminder-link-summary__icon" />
								<span>{{ t('nc_vertragswerk', 'Hinweis') }}</span>
								<ChevronUpIcon v-if="reminderLinkExpanded" :size="18" />
								<ChevronDownIcon v-else :size="18" />
							</button>
							<div v-if="reminderLinkExpanded" class="reminder-link-details">
								<p class="reminder-link-intro">
									{{ t('nc_vertragswerk', 'Links in Erinnerungs-E-Mails führen eventuell zur falschen Adresse:') }}
								</p>
								<dl class="reminder-link-values">
									<div>
										<dt>{{ t('nc_vertragswerk', 'Hinterlegt') }}</dt>
										<dd>{{ reminderLink.cliUrl || '—' }}</dd>
									</div>
									<div>
										<dt>{{ t('nc_vertragswerk', 'Du nutzt') }}</dt>
										<dd>{{ safeAccessHost }}</dd>
									</div>
								</dl>
								<p class="reminder-link-fix">
									{{ t('nc_vertragswerk', 'So korrigierst du das: bei verwaltetem Hosting im Verwaltungs-Panel deines Anbieters, auf einem eigenen Server per Kommandozeile:') }}
								</p>
								<code class="reminder-link-command">{{ reminderLinkCommand }}</code>
							</div>
						</div>

						<!-- Default reminder lead time -->
						<div class="settings-item reminder-days">
							<label class="settings-label">{{ t('nc_vertragswerk', 'Standard-Vorlaufzeit (Tage vor Kündigungsfrist)') }}</label>
							<p class="settings-description">
								{{ t('nc_vertragswerk', 'Gilt für alle Benutzer, die keine eigene Vorlaufzeit eingestellt haben.') }}
							</p>

							<div class="reminder-inputs">
								<div class="reminder-input-group">
									<label>{{ t('nc_vertragswerk', 'Erste Erinnerung') }}</label>
									<NcTextField v-model="adminSettings.reminderDays1"
										type="number"
										:min="1"
										class="number-input"
										@blur="saveReminderDays('reminderDays1')" />
									<span class="unit">{{ t('nc_vertragswerk', 'Tage') }}</span>
								</div>

								<div class="reminder-input-group">
									<label>{{ t('nc_vertragswerk', 'Letzte Erinnerung') }}</label>
									<NcTextField v-model="adminSettings.reminderDays2"
										type="number"
										:min="1"
										class="number-input"
										@blur="saveReminderDays('reminderDays2')" />
									<span class="unit">{{ t('nc_vertragswerk', 'Tage') }}</span>
								</div>
							</div>
						</div>

						<!-- Successor for contracts of deleted accounts (#299) -->
						<div class="settings-item">
							<label class="settings-label">{{ t('nc_vertragswerk', 'Nachfolger bei gelöschtem Konto') }}</label>
							<p class="settings-description">
								{{ t('nc_vertragswerk', 'Wird ein Konto gelöscht, geht die Zuständigkeit für dessen Verträge an diese Person über. Ohne Angabe bleiben die Verträge unverändert. Private Verträge werden nie automatisch übertragen, sie bleiben für Administratoren sichtbar. Verträge werden dabei nie gelöscht.') }}
							</p>
							<NcSelect v-model="deletionSuccessorOption"
								:options="transferUserResults"
								:loading="transferSearching"
								:placeholder="t('nc_vertragswerk', 'Benutzer suchen...')"
								label="displayName"
								track-by="id"
								:clearable="true"
								input-id="deletion-successor"
								@open="onTransferOpen"
								@search="onTransferSearch"
								@update:model-value="onDeletionSuccessorChange" />
							<p class="settings-description">
								{{ t('nc_vertragswerk', 'Denk daran, den Eintrag anzupassen, wenn diese Person selbst das Unternehmen verlässt.') }}
							</p>
						</div>

						<!-- Standard-Adressbuch für neue Kontakte (#3) -->
						<div class="settings-item">
							<label class="settings-label" for="default-addressbook">{{ t('nc_vertragswerk', 'Standard-Adressbuch für neu angelegte Kontakte') }}</label>
							<p class="settings-description">
								{{ t('nc_vertragswerk', 'Wird beim Anlegen eines Kontakts aus dem Vertragsformular vorausgewählt und kann dort geändert werden. Gilt für alle Benutzer über den Namen des Adressbuchs; wer keines mit diesem Namen hat, bekommt sein erstes verfügbares.') }}
							</p>
							<select id="default-addressbook"
								v-model="adminSettings.defaultAddressBook"
								class="settings-select"
								data-test="default-addressbook"
								@change="saveAdminField('defaultAddressBook', adminSettings.defaultAddressBook)">
								<option value="">
									{{ t('nc_vertragswerk', 'Erstes verfügbares Adressbuch') }}
								</option>
								<option v-for="name in addressBookNames" :key="name" :value="name">
									{{ name }}
								</option>
							</select>
						</div>
					</div>

					<!-- Custom Fields Settings -->
					<div v-show="activeSection === 'admin'" class="settings-section admin-section">
						<h3>
							<ShieldIcon :size="20" class="admin-icon" />
							{{ t('nc_vertragswerk', 'Zusatzfelder') }}
						</h3>
						<p class="settings-description">
							{{ t('nc_vertragswerk', 'Aktivieren Sie bis zu 3 optionale Zusatzfelder für Verträge.') }}
						</p>

						<div v-for="n in 3" :key="'cf' + n" class="settings-item custom-field-item">
							<NcCheckboxRadioSwitch :model-value="customFieldEnabled(n)"
								@update:model-value="toggleCustomField(n, $event)">
								{{ t('nc_vertragswerk', 'Zusatzfeld {n}', { n }) }}
							</NcCheckboxRadioSwitch>
							<NcTextField v-if="customFieldEnabled(n)"
								v-model="adminSettings['customFieldLabel' + n]"
								:placeholder="customFieldPlaceholders[n - 1]"
								class="settings-input custom-field-label"
								@blur="saveAdminField('customFieldLabel' + n, adminSettings['customFieldLabel' + n])" />
						</div>
					</div>

					<!-- AI Extraction Settings -->
					<div v-show="activeSection === 'admin'" class="settings-section admin-section">
						<h3>
							<ShieldIcon :size="20" class="admin-icon" />
							{{ t('nc_vertragswerk', 'KI-Vertragsanalyse') }}
						</h3>
						<p class="settings-description">
							{{ t('nc_vertragswerk', 'Automatische Erkennung von Vertragsdaten aus PDF-Dokumenten.') }}
						</p>

						<div class="settings-item">
							<label class="settings-label">{{ t('nc_vertragswerk', 'KI-Provider') }}</label>
							<NcSelect v-model="adminSettings.aiProvider"
								:options="aiProviderOptions"
								:placeholder="t('nc_vertragswerk', 'Deaktiviert')"
								label="label"
								track-by="value"
								:reduce="option => option.value"
								:clearable="true"
								class="settings-input" />
							<p class="ai-status" :class="aiActive ? 'ai-status--active' : 'ai-status--inactive'">
								{{ aiActive
									? t('nc_vertragswerk', 'Aktiv – Analyse verfügbar')
									: (adminSettings.aiProvider
										? t('nc_vertragswerk', 'Inaktiv – kein API-Key gespeichert')
										: t('nc_vertragswerk', 'Deaktiviert – kein Provider gewählt')) }}
							</p>
						</div>

						<template v-if="adminSettings.aiProvider">
							<div class="settings-item">
								<label class="settings-label">{{ t('nc_vertragswerk', 'API Key') }}</label>
								<NcTextField v-model="adminSettings.aiApiKey"
									type="password"
									:placeholder="t('nc_vertragswerk', 'API Key eingeben')"
									class="settings-input" />
							</div>

							<div class="settings-item">
								<label class="settings-label">{{ t('nc_vertragswerk', 'API URL') }}</label>
								<p class="settings-description">
									{{ t('nc_vertragswerk', 'Standard-URL wird automatisch gesetzt. Für Ollama z.B. http://localhost:11434/v1') }}
								</p>
								<NcTextField v-model="adminSettings.aiApiUrl"
									:placeholder="aiDefaultUrl"
									class="settings-input" />
							</div>

							<div class="settings-item">
								<label class="settings-label">{{ t('nc_vertragswerk', 'Modell') }}</label>
								<NcTextField v-model="adminSettings.aiModel"
									:placeholder="aiDefaultModel"
									class="settings-input" />
							</div>
						</template>

						<div class="settings-actions settings-actions--inline">
							<NcButton variant="primary" :disabled="savingAi" @click="saveAiSettings">
								<template #icon>
									<NcLoadingIcon v-if="savingAi" :size="20" />
								</template>
								{{ t('nc_vertragswerk', 'KI-Einstellungen speichern') }}
							</NcButton>
							<p class="settings-description settings-description--hint">
								{{ t('nc_vertragswerk', 'Provider, API-Key, URL und Modell werden zusammen gespeichert.') }}
							</p>
						</div>
					</div>

					<!-- Category Management (Admin only) -->
					<div v-show="activeSection === 'categories'" class="settings-section">
						<h3>{{ t('nc_vertragswerk', 'Vertragsarten verwalten') }}</h3>
						<p class="settings-description">
							{{ t('nc_vertragswerk', 'Vertragsarten hinzufügen, bearbeiten oder löschen.') }}
						</p>

						<div class="category-management">
							<!-- Add new category -->
							<div class="category-add">
								<NcTextField v-model="newCategoryName"
									:placeholder="t('nc_vertragswerk', 'Neue Vertragsart...')"
									class="category-input"
									@keyup.enter="addCategory" />
								<NcButton variant="primary"
									:disabled="!newCategoryName.trim() || addingCategory"
									@click="addCategory">
									<template #icon>
										<PlusIcon :size="20" />
									</template>
									{{ t('nc_vertragswerk', 'Hinzufügen') }}
								</NcButton>
							</div>

							<!-- Category list -->
							<div class="category-list-edit">
								<div v-for="category in categories"
									:key="category.id"
									class="category-edit-item">
									<template v-if="editingCategoryId === category.id">
										<NcTextField v-model="editingCategoryName"
											class="category-input"
											@keyup.enter="saveCategory(category)"
											@keyup.esc="cancelEdit" />
										<NcButton variant="primary" @click="saveCategory(category)">
											<template #icon>
												<CheckIcon :size="20" />
											</template>
										</NcButton>
										<NcButton variant="tertiary" @click="cancelEdit">
											<template #icon>
												<CloseIcon :size="20" />
											</template>
										</NcButton>
									</template>
									<template v-else>
										<span class="category-name">{{ category.name }}</span>
										<div class="category-actions">
											<NcButton variant="tertiary" @click="startEdit(category)">
												<template #icon>
													<PencilIcon :size="20" />
												</template>
											</NcButton>
											<NcButton variant="tertiary"
												@click="confirmDeleteCategory(category)">
												<template #icon>
													<DeleteIcon :size="20" />
												</template>
											</NcButton>
										</div>
									</template>
								</div>
							</div>
						</div>
					</div>
				</template>

				<!-- „Kategorien“ (#7), Verwaltung nur für Admins -->
				<AreaSettings v-if="activeSection === 'areas'" :is-admin="$isAdmin" />

				<!-- Categories (read-only for non-admins) -->
				<div v-if="!$isAdmin" v-show="activeSection === 'categories'" class="settings-section">
					<h3>{{ t('nc_vertragswerk', 'Vertragsarten') }}</h3>
					<p class="settings-description">
						{{ t('nc_vertragswerk', 'Vertragsarten können nur von Administratoren verwaltet werden.') }}
					</p>

					<div class="category-list">
						<div v-for="category in categories"
							:key="category.id"
							class="category-item">
							<span class="category-name">{{ category.name }}</span>
						</div>
					</div>
				</div>
			</div>
		</div>

		<NcDialog v-if="importPreview"
			:name="t('nc_vertragswerk', 'Backup einlesen?')"
			@close="closeImportDialog">
			<p class="import-file">
				{{ importPath }}
			</p>
			<ul class="import-summary">
				<li>{{ n('nc_vertragswerk', '%n Vertrag wird eingelesen', '%n Verträge werden eingelesen', importPreview.contracts) }}</li>
				<li v-if="importPreview.duplicates > 0">
					{{ n('nc_vertragswerk', '%n Vertrag ist schon vorhanden und wird übersprungen', '%n Verträge sind schon vorhanden und werden übersprungen', importPreview.duplicates) }}
				</li>
				<li v-if="importPreview.inTrash > 0">
					{{ n('nc_vertragswerk', '%n Vertrag liegt im Papierkorb und wird übersprungen. Stellen Sie ihn dort wieder her.', '%n Verträge liegen im Papierkorb und werden übersprungen. Stellen Sie sie dort wieder her.', importPreview.inTrash) }}
				</li>
				<li v-if="importPreview.invalid > 0">
					{{ n('nc_vertragswerk', '%n Eintrag ist unvollständig und wird übersprungen', '%n Einträge sind unvollständig und werden übersprungen', importPreview.invalid) }}
				</li>
				<li v-if="importPreview.categories > 0">
					{{ n('nc_vertragswerk', '%n Vertragsart wird neu angelegt', '%n Vertragsarten werden neu angelegt', importPreview.categories) }}
				</li>
				<li v-if="importPreview.missingFiles > 0">
					{{ n('nc_vertragswerk', 'Bei %n Vertrag wurde das verknüpfte Dokument oder der Ordner hier nicht gefunden. Der Pfad bleibt erhalten, die Dateien müssen Sie selbst nachkopieren.', 'Bei %n Verträgen wurden verknüpfte Dokumente oder Ordner hier nicht gefunden. Die Pfade bleiben erhalten, die Dateien müssen Sie selbst nachkopieren.', importPreview.missingFiles) }}
				</li>
				<li v-if="importPreview.unknownUsers > 0">
					{{ n('nc_vertragswerk', 'Bei %n Vertrag gibt es den Zuständigen hier nicht, das Feld bleibt leer.', 'Bei %n Verträgen gibt es den Zuständigen hier nicht, das Feld bleibt leer.', importPreview.unknownUsers) }}
				</li>
			</ul>
			<template #actions>
				<NcButton @click="closeImportDialog">
					{{ t('nc_vertragswerk', 'Abbrechen') }}
				</NcButton>
				<NcButton variant="primary"
					:disabled="importBusy || importPreview.contracts === 0"
					@click="runImport">
					<template v-if="importBusy" #icon>
						<NcLoadingIcon :size="20" />
					</template>
					{{ t('nc_vertragswerk', 'Einlesen') }}
				</NcButton>
			</template>
		</NcDialog>

		<NcDialog v-if="showDeleteCategoryDialog"
			:name="t('nc_vertragswerk', 'Vertragsart löschen')"
			@close="showDeleteCategoryDialog = false">
			<p>{{ t('nc_vertragswerk', 'Vertragsart "{name}" wirklich löschen?', { name: deletingCategory ? deletingCategory.name : '' }) }}</p>
			<template #actions>
				<NcButton @click="showDeleteCategoryDialog = false">
					{{ t('nc_vertragswerk', 'Abbrechen') }}
				</NcButton>
				<NcButton variant="error" @click="executeDeleteCategory">
					{{ t('nc_vertragswerk', 'Löschen') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>
import { mapState, mapActions } from 'pinia'
import { getCanonicalLocale, translatePlural as n } from '@nextcloud/l10n'
import { useCategoriesStore } from '../store/categories'
import { useContractsStore } from '../store/contracts'
import ContactService from '../services/ContactService'
import AreaSettings from '../components/AreaSettings.vue'
import ShapeIcon from 'vue-material-design-icons/Shape.vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import ShieldIcon from 'vue-material-design-icons/Shield.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import AccountIcon from 'vue-material-design-icons/Account.vue'
import AccountGroupIcon from 'vue-material-design-icons/AccountGroup.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import ChevronUpIcon from 'vue-material-design-icons/ChevronUp.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import BellIcon from 'vue-material-design-icons/Bell.vue'
import CashMultipleIcon from 'vue-material-design-icons/CashMultiple.vue'
import BackupRestoreIcon from 'vue-material-design-icons/BackupRestore.vue'
import UploadIcon from 'vue-material-design-icons/Upload.vue'
import CalendarIcon from 'vue-material-design-icons/Calendar.vue'
import SwapHorizontalIcon from 'vue-material-design-icons/SwapHorizontal.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import TagIcon from 'vue-material-design-icons/Tag.vue'
import SettingsService from '../services/SettingsService'
import ContractService from '../services/ContractService'
import ImportService from '../services/ImportService'
import { isAiActive } from '../utils/aiSettings'
import { resolveCustomFieldEnabled, customFieldEnabledKey } from '../utils/customFields'
import { getFilePickerBuilder, showSuccess, showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'

export default {
	name: 'SettingsView',
	components: {
		AreaSettings,
		ShapeIcon,
		NcButton,
		NcDialog,
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
		NcTextField,
		NcSelect,
		ShieldIcon,
		PlusIcon,
		PencilIcon,
		DeleteIcon,
		CheckIcon,
		CloseIcon,
		AccountIcon,
		AccountGroupIcon,
		ChevronDownIcon,
		ChevronUpIcon,
		InformationOutlineIcon,
		BellIcon,
		CashMultipleIcon,
		BackupRestoreIcon,
		UploadIcon,
		CalendarIcon,
		SwapHorizontalIcon,
		CogIcon,
		TagIcon,
	},
	data() {
		return {
			activeSection: 'notifications',
			showDeleteCategoryDialog: false,
			deletingCategory: null,
			emailReminder: false,
			reminderMode: 'own',
			userReminders: {
				talkChatToken: '',
				reminderDays1Personal: '',
				reminderDays2Personal: '',
			},
			reminderDefaults: {
				days1: 14,
				days2: 3,
			},
			savingUserReminders: false,
			defaultAmountType: 'netto',
			backupEnabled: false,
			backupFolder: '/VertragsWerk-Backup',
			backupInterval: 'weekly',
			// '' = no fixed hour; select values are strings.
			backupHour: '',
			savedBackupHour: '',
			backupLastRun: 0,
			backupNextRun: 0,
			backingUpNow: false,
			importBusy: false,
			importPath: '',
			importPreview: null,
			calendarFeedUrl: '',
			generatingCalendarFeed: false,
			savingAi: false,
			adminSettings: {
				reminderDays1: 14,
				reminderDays2: 3,
				customFieldLabel1: '',
				customFieldLabel2: '',
				customFieldLabel3: '',
				customField1Enabled: false,
				customField2Enabled: false,
				customField3Enabled: false,
				aiProvider: '',
				aiApiKey: '',
				aiApiUrl: '',
				aiModel: '',
				deletionSuccessor: '',
				defaultAddressBook: '',
			},
			// Object form of adminSettings.deletionSuccessor for the NcSelect.
			deletionSuccessorOption: null,
			// Namen der eigenen Adressbücher für die Auswahl oben (#3)
			ownAddressBookNames: [],
			reminderLink: null,
			reminderLinkExpanded: false,
			permissionSettings: {
				editors: [],
				viewers: [],
			},
			searchResults: [],
			searching: false,
			transferFrom: null,
			transferTo: null,
			transferCount: null,
			transferUserResults: [],
			transferSearching: false,
			transferInitialLoaded: false,
			transferring: false,
			showTransferDialog: false,
			newCategoryName: '',
			addingCategory: false,
			editingCategoryId: null,
			editingCategoryName: '',
		}
	},
	computed: {
		addressBookNames() {
			const names = [...this.ownAddressBookNames]
			const current = this.adminSettings.defaultAddressBook
			if (current && !names.includes(current)) names.push(current)
			return names
		},
		...mapState(useCategoriesStore, {
			categories: 'allCategories',
		}),
		...mapState(useContractsStore, ['canEdit']),
		customFieldPlaceholders() {
			return [
				t('nc_vertragswerk', 'z.B. Versicherungsnummer'),
				t('nc_vertragswerk', 'z.B. Zugeordnet an'),
				t('nc_vertragswerk', 'z.B. Kostenstelle'),
			]
		},
		// "Last / next backup" display (#397). Both are absolute Unix timestamps
		// computed by the backend, so the frontend only formats them.
		lastBackupText() {
			if (!this.backupLastRun) {
				return t('nc_vertragswerk', 'Noch nie')
			}
			return new Date(this.backupLastRun * 1000).toLocaleString(getCanonicalLocale())
		},
		nextBackupText() {
			if (!this.backupNextRun) {
				return t('nc_vertragswerk', 'Nach der ersten Sicherung')
			}
			return new Date(this.backupNextRun * 1000).toLocaleString(getCanonicalLocale())
		},
		aiProviderOptions() {
			return [
				{ value: 'claude', label: 'Claude (Anthropic)' },
				{ value: 'openai_compatible', label: t('nc_vertragswerk', 'OpenAI-kompatibel (Mistral, Ollama, OpenAI, ...)') },
			]
		},
		aiDefaultUrl() {
			if (this.adminSettings.aiProvider === 'claude') return 'https://api.anthropic.com'
			return 'https://api.openai.com/v1'
		},
		aiDefaultModel() {
			if (this.adminSettings.aiProvider === 'claude') return 'claude-sonnet-4-6'
			return 'gpt-4o'
		},
		// KI ist erst "aktiv", wenn ein Provider gewählt UND ein API-Key
		// gespeichert ist. Der Key kommt vom Server maskiert zurück (nicht-leer),
		// ein frisch eingegebener Key ist der Klartext — beides zählt als gesetzt.
		aiActive() {
			return isAiActive(this.adminSettings)
		},
		canTransfer() {
			return !!this.transferFrom && !!this.transferTo
				&& this.principalUid(this.transferFrom) !== this.principalUid(this.transferTo)
		},
		// True when reminder mail links may point at the wrong host.
		showReminderLinkWarning() {
			return !!this.reminderLink && this.reminderLink.status !== 'ok'
		},
		// accessHost stammt aus dem HTTP-Host-Header. Vor der Anzeige defensiv auf
		// gültige Hostname-/Port-Zeichen reduzieren, damit ein manipulierter Header
		// keinen präparierten Text in den angezeigten occ-Befehl schmuggeln kann.
		safeAccessHost() {
			const raw = (this.reminderLink && this.reminderLink.accessHost) || ''
			return raw.replace(/[^a-zA-Z0-9.:-]/g, '')
		},
		reminderLinkCommand() {
			const host = this.safeAccessHost || 'deine-domain.tld'
			return `sudo -u www-data php occ config:system:set overwrite.cli.url --value="https://${host}"`
		},
	},
	async created() {
		this.fetchCategories()
		await this.loadUserSettings()
		if (this.$isAdmin) {
			await this.loadAdminSettings()
			await this.loadPermissionSettings()
			this.loadAddressBookNames()
			await this.performSearch('')
		}
	},
	methods: {
		...mapActions(useCategoriesStore, ['fetchCategories', 'createCategory', 'updateCategory', 'deleteCategory']),
		...mapActions(useContractsStore, ['fetchContracts', 'fetchArchivedContracts']),

		customFieldEnabled(n) {
			return this.adminSettings[customFieldEnabledKey(n)]
		},

		// Toggle only flips the active flag; the label is left untouched. Enabling
		// no longer prefills a hardcoded value, and disabling keeps the name so it
		// survives being switched off and on again (#368). A deactivated field is
		// gated out of the contract form server-side (empty label there).
		toggleCustomField(n, enabled) {
			const key = customFieldEnabledKey(n)
			this.adminSettings[key] = enabled
			this.saveAdminField(key, enabled)
		},

		// Autosave a single independent admin field (reminder days, custom-field
		// labels, deletion successor). The backend PUT /admin applies only the
		// fields that are non-null, so sending one field leaves the rest — and
		// especially the AI credentials — untouched. The AI block keeps its own
		// deliberate save (saveAiSettings) because it only makes sense as a
		// complete, validated set. (#151)
		async loadAddressBookNames() {
			try {
				this.ownAddressBookNames = (await ContactService.addressBooks()).map(b => b.name)
			} catch (e) {
				this.ownAddressBookNames = []
			}
		},

		async saveAdminField(field, value) {
			try {
				await SettingsService.updateAdminSettings({ [field]: value })
				showSuccess(t('nc_vertragswerk', 'Einstellung gespeichert'))
			} catch (error) {
				console.error('Failed to save admin setting ' + field + ':', error)
				showError(t('nc_vertragswerk', 'Fehler beim Speichern'))
			}
		},

		// A cleared reminder-days field parses to NaN, which JSON-serializes to
		// null and is silently ignored by the backend (see saveAdminField) — do
		// not report a false "gespeichert" for a value that was never persisted.
		saveReminderDays(field) {
			const parsed = parseInt(this.adminSettings[field], 10)
			if (Number.isNaN(parsed)) {
				return
			}
			this.saveAdminField(field, parsed)
		},

		async loadUserSettings() {
			try {
				const settings = await SettingsService.getUserSettings()
				this.emailReminder = settings.emailReminder
				this.reminderMode = settings.reminderMode || 'own'
				this.userReminders = {
					talkChatToken: settings.talkChatToken || '',
					reminderDays1Personal: settings.reminderDays1Personal ?? '',
					reminderDays2Personal: settings.reminderDays2Personal ?? '',
				}
				this.reminderDefaults = {
					days1: settings.reminderDays1 || 14,
					days2: settings.reminderDays2 || 3,
				}
				this.defaultAmountType = settings.defaultAmountType || 'netto'
				this.backupEnabled = settings.backupEnabled === true
				this.backupFolder = settings.backupFolder || '/VertragsWerk-Backup'
				this.backupInterval = settings.backupInterval || 'weekly'
				this.backupHour = typeof settings.backupHour === 'number' ? String(settings.backupHour) : ''
				this.savedBackupHour = this.backupHour
				this.backupLastRun = settings.backupLastRun || 0
				this.backupNextRun = settings.backupNextRun || 0
				this.calendarFeedUrl = settings.calendarFeedUrl || ''
			} catch (error) {
				console.error('Failed to load user settings:', error)
			}
		},

		async resetCalendarFeed() {
			this.generatingCalendarFeed = true
			try {
				const { calendarFeedUrl } = await SettingsService.resetCalendarFeedToken()
				this.calendarFeedUrl = calendarFeedUrl
				showSuccess(t('nc_vertragswerk', 'Abo-URL erzeugt'))
			} catch (error) {
				console.error('Failed to reset calendar feed token:', error)
				showError(t('nc_vertragswerk', 'Fehler beim Speichern'))
			} finally {
				this.generatingCalendarFeed = false
			}
		},

		async copyCalendarFeedUrl() {
			try {
				await navigator.clipboard.writeText(this.calendarFeedUrl)
				showSuccess(t('nc_vertragswerk', 'In die Zwischenablage kopiert'))
			} catch (error) {
				// Clipboard API can be blocked (e.g. non-secure context) — fall back
				// to selecting the field so the user can copy manually.
				const input = this.$refs.calendarFeedInput
				if (input) {
					input.select()
				}
				showError(t('nc_vertragswerk', 'Kopieren nicht möglich – bitte manuell markieren'))
			}
		},

		async onBackupEnabledChange(value) {
			const previous = this.backupEnabled
			this.backupEnabled = value
			try {
				await SettingsService.updateUserSettings({ backupEnabled: value })
				showSuccess(t('nc_vertragswerk', 'Einstellung gespeichert'))
			} catch (error) {
				console.error('Failed to save backup enabled:', error)
				showError(t('nc_vertragswerk', 'Fehler beim Speichern'))
				this.backupEnabled = previous
			}
		},

		async onBackupFolderChange() {
			const previous = this.backupFolder
			try {
				await SettingsService.updateUserSettings({ backupFolder: this.backupFolder })
				showSuccess(t('nc_vertragswerk', 'Einstellung gespeichert'))
			} catch (error) {
				console.error('Failed to save backup folder:', error)
				showError(t('nc_vertragswerk', 'Fehler beim Speichern'))
				this.backupFolder = previous
			}
		},

		async onBackupIntervalChange() {
			const previous = this.backupInterval
			try {
				const updated = await SettingsService.updateUserSettings({ backupInterval: this.backupInterval })
				// "Nächste Sicherung" depends on the interval; take the server's
				// freshly computed value so the two never diverge.
				if (typeof updated.backupNextRun === 'number') {
					this.backupNextRun = updated.backupNextRun
				}
				showSuccess(t('nc_vertragswerk', 'Einstellung gespeichert'))
			} catch (error) {
				console.error('Failed to save backup interval:', error)
				showError(t('nc_vertragswerk', 'Fehler beim Speichern'))
				this.backupInterval = previous
			}
		},

		async onBackupHourChange() {
			try {
				const updated = await SettingsService.updateUserSettings({
					backupHour: this.backupHour === '' ? -1 : Number(this.backupHour),
				})
				if (typeof updated.backupNextRun === 'number') {
					this.backupNextRun = updated.backupNextRun
				}
				this.savedBackupHour = this.backupHour
				showSuccess(t('nc_vertragswerk', 'Einstellung gespeichert'))
			} catch (error) {
				console.error('Failed to save backup hour:', error)
				showError(t('nc_vertragswerk', 'Fehler beim Speichern'))
				this.backupHour = this.savedBackupHour
			}
		},

		async pickImportFile() {
			let path
			try {
				path = await getFilePickerBuilder(t('nc_vertragswerk', 'Sicherungsdatei wählen'))
					.setMultiSelect(false)
					.setMimeTypeFilter(['application/json'])
					.addButton({
						label: t('nc_vertragswerk', 'Auswählen'),
						variant: 'primary',
						callback: () => {},
					})
					.build()
					.pick()
			} catch (e) {
				console.debug('Import file picker cancelled', e)
				return
			}
			if (!path) {
				return
			}
			this.importBusy = true
			try {
				this.importPreview = await ImportService.preview(path)
				this.importPath = path
			} catch (error) {
				console.error('Import preview failed:', error)
				showError(error?.response?.data?.error || t('nc_vertragswerk', 'Die Datei konnte nicht gelesen werden.'))
			} finally {
				this.importBusy = false
			}
		},

		async runImport() {
			this.importBusy = true
			try {
				const summary = await ImportService.import(this.importPath)
				this.closeImportDialog()
				showSuccess(n('nc_vertragswerk', '%n Vertrag eingelesen', '%n Verträge eingelesen', summary.contracts))
				await Promise.all([this.fetchContracts(), this.fetchArchivedContracts(), this.fetchCategories()])
			} catch (error) {
				console.error('Import failed:', error)
				showError(error?.response?.data?.error || t('nc_vertragswerk', 'Einlesen fehlgeschlagen'))
			} finally {
				this.importBusy = false
			}
		},

		closeImportDialog() {
			this.importPreview = null
			this.importPath = ''
		},

		// Manually trigger a backup ("Jetzt sichern", #397) and refresh last/next.
		async triggerBackupNow() {
			this.backingUpNow = true
			try {
				const { backupLastRun, backupNextRun } = await SettingsService.triggerBackupNow()
				this.backupLastRun = backupLastRun
				this.backupNextRun = backupNextRun
				showSuccess(t('nc_vertragswerk', 'Sicherung erstellt'))
			} catch (error) {
				console.error('Failed to trigger backup:', error)
				showError(t('nc_vertragswerk', 'Sicherung fehlgeschlagen'))
			} finally {
				this.backingUpNow = false
			}
		},

		async onReminderModeChange(value) {
			const previous = this.reminderMode
			try {
				await SettingsService.updateUserSettings({ reminderMode: value })
				showSuccess(t('nc_vertragswerk', 'Einstellung gespeichert'))
			} catch (error) {
				console.error('Failed to save reminder mode:', error)
				showError(t('nc_vertragswerk', 'Fehler beim Speichern'))
				this.reminderMode = previous
			}
		},

		async saveUserReminderSettings() {
			this.savingUserReminders = true
			try {
				// Empty string clears the personal value (0 → backend treats as "use default").
				const days1 = this.userReminders.reminderDays1Personal
				const days2 = this.userReminders.reminderDays2Personal
				await SettingsService.updateUserSettings({
					talkChatToken: this.userReminders.talkChatToken,
					reminderDays1Personal: days1 === '' ? 0 : parseInt(days1, 10),
					reminderDays2Personal: days2 === '' ? 0 : parseInt(days2, 10),
				})
				showSuccess(t('nc_vertragswerk', 'Einstellung gespeichert'))
			} catch (error) {
				console.error('Failed to save reminder settings:', error)
				showError(t('nc_vertragswerk', 'Fehler beim Speichern'))
			} finally {
				this.savingUserReminders = false
			}
		},

		async onDefaultAmountTypeChange() {
			const previous = this.defaultAmountType
			try {
				await SettingsService.updateUserSettings({ defaultAmountType: this.defaultAmountType })
			} catch (error) {
				console.error('Failed to save default amount type:', error)
				showError(t('nc_vertragswerk', 'Fehler beim Speichern'))
				this.defaultAmountType = previous
			}
		},

		async loadAdminSettings() {
			try {
				const settings = await SettingsService.getAdminSettings()
				this.adminSettings = {
					reminderDays1: settings.reminderDays1 || 14,
					reminderDays2: settings.reminderDays2 || 3,
					customFieldLabel1: settings.customFieldLabel1 || '',
					customFieldLabel2: settings.customFieldLabel2 || '',
					customFieldLabel3: settings.customFieldLabel3 || '',
					customField1Enabled: resolveCustomFieldEnabled(settings, 1),
					customField2Enabled: resolveCustomFieldEnabled(settings, 2),
					customField3Enabled: resolveCustomFieldEnabled(settings, 3),
					aiProvider: settings.aiProvider || '',
					aiApiKey: settings.aiApiKey || '',
					aiApiUrl: settings.aiApiUrl || '',
					aiModel: settings.aiModel || '',
					deletionSuccessor: settings.deletionSuccessor || '',
					defaultAddressBook: settings.defaultAddressBook || '',
				}
				this.deletionSuccessorOption = settings.deletionSuccessor
					? {
						id: 'user:' + settings.deletionSuccessor,
						uid: settings.deletionSuccessor,
						displayName: settings.deletionSuccessorDisplayName || settings.deletionSuccessor,
					}
					: null
				this.reminderLink = settings.reminderLink || null
			} catch (error) {
				console.error('Failed to load admin settings:', error)
			}
		},

		async loadPermissionSettings() {
			try {
				const settings = await SettingsService.getPermissionSettings()
				// Convert string IDs to objects for NcSelect
				this.permissionSettings.editors = await this.convertIdsToObjects(settings.editors || [])
				this.permissionSettings.viewers = await this.convertIdsToObjects(settings.viewers || [])
			} catch (error) {
				console.error('Failed to load permission settings:', error)
			}
		},

		async convertIdsToObjects(ids) {
			// Convert stored IDs like "group:admin" or "user:john" to display objects
			const objects = []
			for (const id of ids) {
				const [type, identifier] = id.split(':')
				objects.push({
					id,
					type,
					displayName: identifier, // Will be updated by search if user searches
					...(type === 'group' ? { gid: identifier } : { uid: identifier }),
				})
			}
			return objects
		},

		async performSearch(query) {
			try {
				this.searching = true
				this.searchResults = await SettingsService.searchUsersAndGroups(query)
			} catch (error) {
				console.error('Failed to search users/groups:', error)
				this.searchResults = []
			} finally {
				this.searching = false
			}
		},

		principalUid(principal) {
			if (!principal) return ''
			return principal.uid || String(principal.id || '').replace('user:', '')
		},
		// Clearing the field is a valid choice: no successor means contracts of
		// a deleted account are left untouched.
		onDeletionSuccessorChange(option) {
			this.adminSettings.deletionSuccessor = this.principalUid(option)
			this.saveAdminField('deletionSuccessor', this.adminSettings.deletionSuccessor || '')
		},

		// Lazy-load a first batch of users when a transfer picker is first opened,
		// so the dropdown is not empty before typing (same behaviour as the
		// "responsible" picker in the contract form).
		async onTransferOpen() {
			if (this.transferInitialLoaded) return
			this.transferInitialLoaded = true
			await this.fetchTransferUsers('')
		},
		async onTransferSearch(query) {
			await this.fetchTransferUsers(query)
		},
		async fetchTransferUsers(query) {
			try {
				this.transferSearching = true
				this.transferUserResults = await ContractService.searchUsers(query || '')
			} catch (e) {
				this.transferUserResults = []
			} finally {
				this.transferSearching = false
			}
		},

		async onTransferFromChange() {
			this.transferCount = null
			if (!this.transferFrom) return
			try {
				this.transferCount = await ContractService.transferPreview(this.principalUid(this.transferFrom))
			} catch (e) {
				console.error('Failed to load transfer preview:', e)
			}
		},

		async doTransfer() {
			this.showTransferDialog = false
			if (!this.canTransfer) return
			this.transferring = true
			try {
				const count = await ContractService.transfer(this.principalUid(this.transferFrom), this.principalUid(this.transferTo))
				showSuccess(t('nc_vertragswerk', '{count} Verträge übertragen', { count }))
				this.transferFrom = null
				this.transferTo = null
				this.transferCount = null
			} catch (e) {
				console.error('Transfer failed:', e)
				showError(t('nc_vertragswerk', 'Übertragung fehlgeschlagen'))
			} finally {
				this.transferring = false
			}
		},

		async onEditorsChange(value) {
			await this.savePermissionSettings('editors', value)
		},

		async onViewersChange(value) {
			await this.savePermissionSettings('viewers', value)
		},

		async savePermissionSettings(field, value) {
			try {
				const ids = value.map(item => item.id)
				await SettingsService.updatePermissionSettings({
					[field]: ids,
				})
				showSuccess(t('nc_vertragswerk', 'Einstellung gespeichert'))
			} catch (error) {
				console.error('Failed to save permission settings:', error)
				showError(t('nc_vertragswerk', 'Fehler beim Speichern'))
			}
		},

		async onEmailReminderChange(value) {
			try {
				await SettingsService.updateUserSettings({ emailReminder: value })
				showSuccess(t('nc_vertragswerk', 'Einstellung gespeichert'))
			} catch (error) {
				console.error('Failed to save user settings:', error)
				showError(t('nc_vertragswerk', 'Fehler beim Speichern'))
				this.emailReminder = !value
			}
		},

		// The AI block is saved as one deliberate, complete set (provider + key +
		// URL + model), not autosaved per field: a half-entered credential set
		// should not persist. Only the AI fields are sent and only the AI fields
		// of the response are taken back — the masked key in particular — so this
		// never clobbers the independently autosaved fields. (#151)
		async saveAiSettings() {
			this.savingAi = true
			try {
				const result = await SettingsService.updateAdminSettings({
					aiProvider: this.adminSettings.aiProvider || '',
					aiApiKey: this.adminSettings.aiApiKey,
					aiApiUrl: this.adminSettings.aiApiUrl,
					aiModel: this.adminSettings.aiModel,
				})
				this.adminSettings.aiProvider = result.aiProvider || ''
				this.adminSettings.aiApiKey = result.aiApiKey || ''
				this.adminSettings.aiApiUrl = result.aiApiUrl || ''
				this.adminSettings.aiModel = result.aiModel || ''
				showSuccess(t('nc_vertragswerk', 'KI-Einstellungen gespeichert'))
			} catch (error) {
				console.error('Failed to save AI settings:', error)
				showError(t('nc_vertragswerk', 'Fehler beim Speichern der KI-Einstellungen'))
			} finally {
				this.savingAi = false
			}
		},

		async addCategory() {
			if (!this.newCategoryName.trim()) return

			this.addingCategory = true
			try {
				await this.createCategory(this.newCategoryName.trim())
				this.newCategoryName = ''
				showSuccess(t('nc_vertragswerk', 'Vertragsart hinzugefügt'))
			} catch (error) {
				console.error('Failed to add category:', error)
				showError(t('nc_vertragswerk', 'Fehler beim Hinzufügen der Vertragsart'))
			} finally {
				this.addingCategory = false
			}
		},

		startEdit(category) {
			this.editingCategoryId = category.id
			this.editingCategoryName = category.name
		},

		cancelEdit() {
			this.editingCategoryId = null
			this.editingCategoryName = ''
		},

		async saveCategory(category) {
			if (!this.editingCategoryName.trim()) return

			try {
				await this.updateCategory({
					id: category.id,
					name: this.editingCategoryName.trim(),
				})
				this.cancelEdit()
				showSuccess(t('nc_vertragswerk', 'Vertragsart aktualisiert'))
			} catch (error) {
				console.error('Failed to update category:', error)
				showError(t('nc_vertragswerk', 'Fehler beim Aktualisieren der Vertragsart'))
			}
		},

		confirmDeleteCategory(category) {
			this.deletingCategory = category
			this.showDeleteCategoryDialog = true
		},

		async executeDeleteCategory() {
			if (!this.deletingCategory) return
			try {
				await this.deleteCategory(this.deletingCategory.id)
				showSuccess(t('nc_vertragswerk', 'Vertragsart gelöscht'))
			} catch (error) {
				console.error('Failed to delete category:', error)
				showError(t('nc_vertragswerk', 'Fehler beim Löschen der Vertragsart'))
			} finally {
				this.showDeleteCategoryDialog = false
				this.deletingCategory = null
			}
		},
	},
}
</script>

<style scoped lang="scss">
.settings-view {
	padding: 20px;
	padding-left: 50px;
	max-width: 1100px;

	&__header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 24px;

		h2 {
			margin: 0;
			font-size: 20px;
			font-weight: 600;
		}
	}
}

.settings-section {
	margin-bottom: 32px;
	padding-bottom: 24px;
	border-bottom: 1px solid var(--color-border);

	h3 {
		margin: 0 0 12px;
		font-size: 16px;
		font-weight: 600;
		display: flex;
		align-items: center;
		gap: 8px;
	}
}

.admin-section {
	background: var(--color-background-dark);
	padding: 20px;
	border-radius: 8px;
	margin-top: 24px;

	h3 {
		color: var(--color-primary);
	}
}

.admin-icon {
	color: var(--color-primary);
}

/* WorkTime-style 2-column settings: sticky section nav + content panel. */
.settings-layout {
	display: grid;
	grid-template-columns: 240px 1fr;
	gap: 24px;
	align-items: start;
}

.settings-nav {
	position: sticky;
	top: 0;
	display: flex;
	flex-direction: column;
	padding-right: 16px;
	border-right: 1px solid var(--color-border);
}

.settings-nav-group {
	font-size: 11px;
	font-weight: 700;
	letter-spacing: 0.5px;
	text-transform: uppercase;
	color: var(--color-text-maxcontrast);
	padding: 14px 12px 6px;

	&:first-child { padding-top: 0; }
}

.settings-nav-item {
	display: flex;
	align-items: center;
	gap: 10px;
	width: 100%;
	padding: 9px 12px;
	border: none;
	background: none;
	color: var(--color-main-text);
	font: inherit;
	font-weight: 500;
	text-align: left;
	border-radius: var(--border-radius, 8px);
	cursor: pointer;
	transition: background-color 0.15s ease;

	&:hover { background: var(--color-background-hover); }

	&.active {
		background: var(--color-primary-element-light);
		color: var(--color-primary-element);
		font-weight: 600;
	}
}

/* In the nav layout each section shows on its own — drop the divider styling. */
.settings-content {
	.settings-section {
		border-bottom: none;
		margin-bottom: 16px;
		padding-bottom: 0;
	}

	.admin-section { margin-top: 0; }
}

@media (max-width: 900px) {
	.settings-layout { grid-template-columns: 1fr; }

	.settings-nav {
		flex-direction: row;
		flex-wrap: wrap;
		border-right: none;
		border-bottom: 1px solid var(--color-border);
		padding: 0 0 8px;
	}

	.settings-nav-group { width: 100%; padding: 8px 12px 4px; }
}

.reminder-link-note {
	margin-bottom: 16px;
}

.reminder-link-summary {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 4px 8px;
	border: none;
	border-radius: var(--border-radius);
	background: transparent;
	color: var(--color-text-maxcontrast);
	font-size: 0.95em;
	text-align: start;
	cursor: pointer;

	&:hover,
	&:focus-visible {
		background: var(--color-background-hover);
		color: var(--color-main-text);
	}

	&__icon {
		flex: 0 0 auto;
	}
}

.reminder-link-details {
	padding: 4px 8px 0 30px;
	font-size: 0.9em;

	p {
		margin: 0 0 8px;
	}

	.reminder-link-intro {
		color: var(--color-main-text);
	}

	.reminder-link-fix {
		color: var(--color-text-maxcontrast);
	}

	.reminder-link-command {
		display: block;
		padding: 8px 12px;
		background: var(--color-background-darker);
		border-radius: var(--border-radius);
		font-family: var(--font-face-monospace, monospace);
		white-space: pre-wrap;
		word-break: break-all;
		user-select: all;
	}
}

.reminder-link-values {
	margin: 0 0 8px;

	div {
		display: flex;
		gap: 8px;
	}

	dt {
		flex: 0 0 80px;
		color: var(--color-text-maxcontrast);
	}

	dd {
		margin: 0;
		font-family: var(--font-face-monospace, monospace);
		word-break: break-all;
	}
}

.settings-item {
	margin-bottom: 20px;
}

.settings-label {
	display: block;
	font-weight: 600;
	margin-bottom: 4px;
}

.settings-value {
	color: var(--color-text-maxcontrast);
}

.settings-description {
	margin: 4px 0 8px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.settings-input {
	max-width: 400px;
}

.calendar-feed-row {
	display: flex;
	align-items: center;
	gap: 8px;
	max-width: 520px;

	.calendar-feed-url {
		flex: 1 1 auto;
		max-width: none;
	}
}

.reminder-days {
	.reminder-inputs {
		display: flex;
		gap: 24px;
		margin-top: 12px;
	}

	.reminder-input-group {
		display: flex;
		align-items: center;
		gap: 8px;

		label {
			font-size: 14px;
			min-width: 120px;
		}

		.number-input {
			width: 80px;
		}

		.unit {
			color: var(--color-text-maxcontrast);
		}
	}
}

.category-management {
	margin-top: 16px;
}

.category-add {
	display: flex;
	gap: 8px;
	margin-bottom: 16px;

	.category-input {
		max-width: 300px;
	}
}

.category-list-edit {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.category-edit-item {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 8px 12px;
	background: var(--color-background-dark);
	border-radius: 8px;

	.category-name {
		flex: 1;
		font-size: 14px;
	}

	.category-input {
		flex: 1;
		max-width: 300px;
	}

	.category-actions {
		display: flex;
		gap: 4px;
		opacity: 0.6;
		transition: opacity 0.2s;
	}

	&:hover .category-actions {
		opacity: 1;
	}
}

.category-list {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin-top: 12px;
}

.category-item {
	padding: 6px 12px;
	background: var(--color-background-dark);
	border-radius: 16px;
	font-size: 13px;
}

.custom-field-item {
	.custom-field-label {
		margin-top: 8px;
	}
}

.settings-select {
	min-width: 260px;
	max-width: 100%;
	min-height: 36px;
	padding: 6px 10px;
	border: 1px solid var(--color-border-dark, var(--color-border));
	border-radius: var(--border-radius, 8px);
	background-color: var(--color-main-background);
	color: var(--color-main-text);
	font: inherit;
}

.settings-actions {
	margin-top: 24px;
}

.settings-subheading {
	margin-top: 32px;
}

.import-file {
	color: var(--color-text-maxcontrast);
	word-break: break-all;
}

.import-summary {
	margin: 12px 0;
	padding-inline-start: 20px;
	list-style: disc;
}

/* KI-Block: eigener Speichern-Schritt direkt am Block, nicht am Seitenende. */
.settings-actions--inline {
	display: flex;
	align-items: center;
	gap: 12px;
	flex-wrap: wrap;
	margin-top: 16px;
}

.settings-description--hint {
	margin: 0;
}

/* Statuszeile des KI-Blocks: sagt, ob die Analyse wirklich einsatzbereit ist. */
.ai-status {
	margin: 6px 0 0;
	font-size: 0.9em;
	font-weight: 500;
}

.ai-status--active {
	color: var(--color-success, #2d7d46);
}

.ai-status--inactive {
	color: var(--color-text-maxcontrast);
}

.permission-select {
	max-width: 500px;
}

.permission-option {
	display: flex;
	align-items: center;
	gap: 8px;

	.permission-option-type {
		margin-left: auto;
		color: var(--color-text-maxcontrast);
		font-size: 12px;
	}
}

.permission-tag {
	display: flex;
	align-items: center;
	gap: 4px;
}
</style>
