import { translate as t } from '@nextcloud/l10n'

/** Must match Contract::PAYMENT_METHODS in lib/Db/Contract.php (fds2610/contractmanager#7). */
export type PaymentMethod = 'debit' | 'transfer'

export function paymentMethodOptions(): Array<{ value: PaymentMethod | null, label: string }> {
	return [
		{ value: null, label: '—' },
		{ value: 'debit', label: t('nc_vertragswerk', 'wird abgebucht') },
		{ value: 'transfer', label: t('nc_vertragswerk', 'überweisen') },
	]
}

export function paymentMethodLabel(value: string | null | undefined): string {
	return paymentMethodOptions().find(o => o.value === value && value !== null)?.label ?? ''
}
