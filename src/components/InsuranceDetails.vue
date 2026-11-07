<template>
	<div class="insurance-details">
		<!-- Persons -->
		<div class="ins-block">
			<span class="ins-label">{{ t('contractmanager', 'Personen') }}</span>
			<template v-if="readOnly">
				<ul v-if="filledPersons.length" class="ins-readonly">
					<li v-for="(person, index) in filledPersons" :key="index">
						<span class="ins-readonly__type">{{ personTypeLabel(person.type) }}:</span>
						<NcChip v-if="person.userId"
							:text="person.name"
							no-close>
							<template #icon>
								<AccountIcon :size="18" />
							</template>
						</NcChip>
						<span v-else>{{ person.name }}</span>
					</li>
				</ul>
				<span v-else class="ins-empty">—</span>
			</template>
			<template v-else>
				<div v-for="(person, index) in persons"
					:key="'p' + index"
					class="ins-row"
					data-test="insurance-person-row">
					<select :value="person.type"
						class="ins-input ins-input--type"
						:aria-label="t('contractmanager', 'Personentyp')"
						@change="updatePerson(index, { type: $event.target.value })">
						<option v-for="opt in personTypeOptions" :key="opt.value" :value="opt.value">
							{{ opt.label }}
						</option>
					</select>
					<div class="ins-person">
						<div class="ins-input ins-person__box" :class="{ 'ins-person__box--chip': person.userId }">
							<NcChip v-if="person.userId"
								:text="person.name"
								:aria-label-close="t('contractmanager', 'Benutzer entfernen')"
								@close="clearUser(index)">
								<template #icon>
									<AccountIcon :size="18" />
								</template>
							</NcChip>
							<input v-else
								:value="person.name"
								class="ins-person__text"
								:placeholder="t('contractmanager', 'Name oder Benutzer suchen...')"
								:aria-label="t('contractmanager', 'Name oder Benutzer')"
								autocomplete="off"
								@input="onPersonInput(index, $event.target.value)"
								@keydown="onPersonKeydown(index, $event)"
								@blur="closeSuggestions(index)">
						</div>
						<ul v-if="activeRow === index && suggestions.length"
							class="ins-suggestions"
							role="listbox">
							<li v-for="(user, uIndex) in suggestions"
								:key="user.uid"
								role="option"
								:aria-selected="uIndex === highlighted"
								:class="{ 'ins-suggestions__item--active': uIndex === highlighted }"
								class="ins-suggestions__item"
								@mousedown.prevent="selectUser(index, user)">
								<AccountIcon :size="18" />
								<span>{{ user.displayName }}</span>
								<small v-if="user.displayName !== user.uid">{{ user.uid }}</small>
							</li>
						</ul>
					</div>
					<button type="button"
						class="ins-btn ins-btn--add"
						:aria-label="t('contractmanager', 'Weitere Person hinzufügen')"
						:title="t('contractmanager', 'Weitere Person hinzufügen')"
						data-test="insurance-person-add"
						@click="addPerson(index)">
						<PlusCircleIcon :size="26" />
					</button>
					<button v-if="persons.length > 1"
						type="button"
						class="ins-btn ins-btn--remove"
						:aria-label="t('contractmanager', 'Person entfernen')"
						:title="t('contractmanager', 'Person entfernen')"
						@click="removePerson(index)">
						<MinusCircleIcon :size="26" />
					</button>
				</div>
			</template>
		</div>

		<!-- Benefits -->
		<div class="ins-block">
			<span class="ins-label">{{ t('contractmanager', 'Versicherungsleistungen') }}</span>
			<template v-if="readOnly">
				<ul v-if="filledBenefits.length" class="ins-readonly ins-readonly--bullets">
					<li v-for="(benefit, index) in filledBenefits" :key="index">
						{{ benefit }}
					</li>
				</ul>
				<span v-else class="ins-empty">—</span>
			</template>
			<template v-else>
				<div v-for="(benefit, index) in benefits"
					:key="'b' + index"
					class="ins-row ins-row--benefit"
					data-test="insurance-benefit-row">
					<input :value="benefit"
						class="ins-input"
						maxlength="1000"
						:placeholder="t('contractmanager', 'z.B. Glasbruch bis 1.000 €')"
						:aria-label="t('contractmanager', 'Versicherungsleistung')"
						@input="updateBenefit(index, $event.target.value)">
					<button type="button"
						class="ins-btn ins-btn--add"
						:aria-label="t('contractmanager', 'Weitere Leistung hinzufügen')"
						:title="t('contractmanager', 'Weitere Leistung hinzufügen')"
						data-test="insurance-benefit-add"
						@click="addBenefit(index)">
						<PlusCircleIcon :size="26" />
					</button>
					<button v-if="benefits.length > 1"
						type="button"
						class="ins-btn ins-btn--remove"
						:aria-label="t('contractmanager', 'Leistung entfernen')"
						:title="t('contractmanager', 'Leistung entfernen')"
						@click="removeBenefit(index)">
						<MinusCircleIcon :size="26" />
					</button>
				</div>
			</template>
		</div>
	</div>
</template>

<script>
import NcChip from '@nextcloud/vue/components/NcChip'
import AccountIcon from 'vue-material-design-icons/Account.vue'
import PlusCircleIcon from 'vue-material-design-icons/PlusCircle.vue'
import MinusCircleIcon from 'vue-material-design-icons/MinusCircle.vue'
import ContractService from '../services/ContractService'
import { emptyPerson, personTypeOptions } from '../utils/insurance'

export default {
	name: 'InsuranceDetails',
	components: {
		NcChip,
		AccountIcon,
		PlusCircleIcon,
		MinusCircleIcon,
	},
	props: {
		persons: {
			type: Array,
			required: true,
		},
		benefits: {
			type: Array,
			required: true,
		},
		readOnly: {
			type: Boolean,
			default: false,
		},
	},
	emits: ['update:persons', 'update:benefits'],
	data() {
		return {
			activeRow: null,
			suggestions: [],
			highlighted: 0,
			searchTimer: null,
			searchSeq: 0,
		}
	},
	computed: {
		personTypeOptions() {
			return personTypeOptions()
		},
		filledPersons() {
			return this.persons.filter(p => (p.name || '').trim() !== '')
		},
		filledBenefits() {
			return this.benefits.filter(b => (b || '').trim() !== '')
		},
	},
	beforeUnmount() {
		clearTimeout(this.searchTimer)
	},
	methods: {
		personTypeLabel(type) {
			const opt = this.personTypeOptions.find(o => o.value === type)
			return opt ? opt.label : type
		},
		updatePerson(index, patch) {
			const persons = this.persons.map((p, i) => (i === index ? { ...p, ...patch } : p))
			this.$emit('update:persons', persons)
		},
		addPerson(index) {
			const persons = [...this.persons]
			persons.splice(index + 1, 0, emptyPerson())
			this.$emit('update:persons', persons)
		},
		removePerson(index) {
			this.$emit('update:persons', this.persons.filter((_, i) => i !== index))
			this.closeSuggestions()
		},
		clearUser(index) {
			this.updatePerson(index, { userId: null, name: '' })
		},
		onPersonInput(index, value) {
			// Typing always means a free-text name; a user is only linked by picking one
			this.updatePerson(index, { name: value, userId: null })
			this.activeRow = index
			this.highlighted = 0
			clearTimeout(this.searchTimer)
			const query = value.trim()
			if (query.length < 2) {
				this.suggestions = []
				return
			}
			this.searchTimer = setTimeout(() => this.search(query), 250)
		},
		async search(query) {
			const seq = ++this.searchSeq
			try {
				const users = await ContractService.searchUsers(query)
				// A slower answer for an older query must not overwrite the current one
				if (seq === this.searchSeq) {
					this.suggestions = Array.isArray(users) ? users.slice(0, 10) : []
				}
			} catch (e) {
				if (seq === this.searchSeq) {
					this.suggestions = []
				}
			}
		},
		selectUser(index, user) {
			this.updatePerson(index, { userId: user.uid, name: user.displayName || user.uid })
			this.closeSuggestions()
		},
		closeSuggestions() {
			clearTimeout(this.searchTimer)
			this.searchSeq++
			this.activeRow = null
			this.suggestions = []
		},
		onPersonKeydown(index, event) {
			if (this.activeRow !== index || !this.suggestions.length) {
				return
			}
			switch (event.key) {
			case 'ArrowDown':
				event.preventDefault()
				this.highlighted = (this.highlighted + 1) % this.suggestions.length
				break
			case 'ArrowUp':
				event.preventDefault()
				this.highlighted = (this.highlighted - 1 + this.suggestions.length) % this.suggestions.length
				break
			case 'Enter':
				event.preventDefault()
				this.selectUser(index, this.suggestions[this.highlighted])
				break
			case 'Escape':
				// Only close the suggestions, not the whole modal
				event.stopPropagation()
				this.closeSuggestions()
				break
			}
		},
		updateBenefit(index, value) {
			this.$emit('update:benefits', this.benefits.map((b, i) => (i === index ? value : b)))
		},
		addBenefit(index) {
			const benefits = [...this.benefits]
			benefits.splice(index + 1, 0, '')
			this.$emit('update:benefits', benefits)
		},
		removeBenefit(index) {
			this.$emit('update:benefits', this.benefits.filter((_, i) => i !== index))
		},
	},
}
</script>

<style scoped lang="scss">
.insurance-details {
	display: flex;
	flex-direction: column;
	gap: 14px;
	margin-bottom: 14px;
}

.ins-label {
	display: block;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	margin-bottom: 4px;
}

.ins-row {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 8px;

	&:last-child { margin-bottom: 0; }
}

// Same frame as .cm-input in ContractForm (scoped styles do not reach in here)
.ins-input {
	width: 100%;
	min-height: 36px;
	padding: 6px 10px;
	border: 1px solid var(--color-border-dark, var(--color-border));
	border-radius: var(--border-radius, 8px);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font: inherit;
	box-sizing: border-box;
	margin: 0;

	&:hover { border-color: var(--color-border-maxcontrast, var(--color-border-dark)); }

	&:focus,
	&:focus-visible,
	&:focus-within {
		outline: none;
		border-color: var(--color-primary-element);
		box-shadow: 0 0 0 2px var(--color-primary-element-light);
	}
}

select.ins-input {
	cursor: pointer;
	padding-right: 32px;
	background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%238b8b8b'%3E%3Cpath d='M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z'/%3E%3C/svg%3E");
	background-repeat: no-repeat;
	background-position: right 8px center;
	background-size: 18px;
}

.ins-input--type {
	flex: 0 0 210px;
}

.ins-person {
	position: relative;
	flex: 1 1 auto;
	min-width: 0;

	&__box {
		display: flex;
		align-items: center;
		padding: 0 10px;

		&--chip { padding: 2px 6px; }
	}

	&__text {
		flex: 1 1 auto;
		min-width: 0;
		min-height: 34px;
		border: none !important;
		box-shadow: none !important;
		outline: none;
		background: transparent;
		color: inherit;
		font: inherit;
		padding: 0;
		margin: 0;
	}
}

.ins-suggestions {
	position: absolute;
	z-index: 10;
	top: calc(100% + 2px);
	left: 0;
	right: 0;
	max-height: 240px;
	overflow-y: auto;
	margin: 0;
	padding: 4px 0;
	list-style: none;
	background: var(--color-main-background);
	border: 1px solid var(--color-border-dark, var(--color-border));
	border-radius: var(--border-radius, 8px);
	box-shadow: 0 2px 8px var(--color-box-shadow, rgba(0, 0, 0, .2));

	&__item {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 6px 10px;
		cursor: pointer;

		small { color: var(--color-text-maxcontrast); }

		&:hover,
		&--active { background: var(--color-background-hover); }
	}
}

.ins-btn {
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

	// NC 32+: --color-success/--color-error are light backgrounds, the
	// --color-element-* variants are the saturated ones for icons
	&--add { color: var(--color-element-success, #099f05); }
	&--remove { color: var(--color-element-error, #c90000); }
}

.ins-readonly {
	margin: 0;
	padding: 0;
	list-style: none;

	li {
		display: flex;
		align-items: center;
		gap: 6px;
		margin-bottom: 4px;
	}

	&__type { color: var(--color-text-maxcontrast); }

	&--bullets {
		list-style: disc;
		padding-left: 20px;

		li { display: list-item; }
	}
}

.ins-empty { color: var(--color-text-maxcontrast); }

@media (max-width: 600px) {
	.ins-row:not(.ins-row--benefit) { flex-wrap: wrap; }
	.ins-input--type { flex: 1 1 100%; }
}
</style>
