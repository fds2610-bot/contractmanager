/**
 * Renders the „Vertragsspiegel" (#489) as a PDF: A4 landscape, small
 * margins, table without frame and vertical lines, only horizontal rules
 * between entries. The table head repeats on every page and a contract row is
 * never split across pages.
 *
 * jsPDF is loaded on first use, so it is not evaluated on every page load.
 */
import { translate as t } from '@nextcloud/l10n'
import { PLAIN, type MirrorGroup } from './contractMirror'

export interface MirrorPdfOptions {
	/** Name shown next to the title. */
	userName: string
	/** Date line „Stand: …", already formatted. */
	date: string
}

// Characters the 14 standard PDF fonts can show (WinAnsiEncoding) beyond Latin-1.
const WIN_ANSI_EXTRA = '€‚ƒ„…†‡ˆ‰Š‹ŒŽ‘’“”•–—˜™š›œžŸ'

/**
 * Make text safe for the built-in Helvetica (WinAnsiEncoding): special spaces
 * become normal ones, accents outside the code page are dropped where possible
 * (ł → l does not decompose, so that becomes „?"). Without this jsPDF writes
 * unreadable bytes instead of the character.
 */
export function toWinAnsi(text: string): string {
	let out = ''
	for (const ch of text.replace(/[\u2000-\u200B\u202F\u205F\u3000]/g, ' ')) {
		const code = ch.codePointAt(0) ?? 0
		if ((code >= 0x20 && code < 0x7F) || (code >= 0xA0 && code <= 0xFF) || ch === '\n' || WIN_ANSI_EXTRA.includes(ch)) {
			out += ch
			continue
		}
		const base = ch.normalize('NFKD').replace(/[̀-ͯ]/g, '')
		out += /^[\x20-\x7E]+$/.test(base) ? base : '?'
	}
	return out
}

const MARGIN = 8 // mm
const HEADER_HEIGHT = 17 // mm above the table, below the top margin
const RULE_COLOR: [number, number, number] = [150, 150, 150]

export async function renderContractMirrorPdf(groups: MirrorGroup[], options: MirrorPdfOptions): Promise<Blob> {
	const [{ jsPDF: JsPdf }, { autoTable }] = await Promise.all([import('jspdf'), import('jspdf-autotable')])

	const doc = new JsPdf({ orientation: 'landscape', unit: 'mm', format: 'a4' })
	const title = t('nc_vertragswerk', 'Vertragsspiegel')
	doc.setProperties({ title: `${title} – ${options.userName}` })
	doc.viewerPreferences({ DisplayDocTitle: true })

	const pageWidth = doc.internal.pageSize.getWidth()
	const headerTitle = toWinAnsi(`${title} – ${options.userName}`)
	const standLine = toWinAnsi(t('nc_vertragswerk', 'Stand: {date}', { date: options.date }, undefined, PLAIN))
	const text = (value: string[] | string) => toWinAnsi(Array.isArray(value) ? value.join('\n') : value)

	const columns = [
		{ title: t('nc_vertragswerk', 'Vertrag'), halign: 'left', width: 50 },
		{ title: t('nc_vertragswerk', 'bei'), halign: 'center', width: 48 },
		{ title: t('nc_vertragswerk', 'Höhe / Umfang'), halign: 'left', width: 'auto' },
		{ title: t('nc_vertragswerk', 'läuft bis'), halign: 'center', width: 22 },
		{ title: t('nc_vertragswerk', 'Status'), halign: 'center', width: 22 },
		{ title: t('nc_vertragswerk', 'Zahlungsweise'), halign: 'center', width: 31 },
		{ title: t('nc_vertragswerk', 'Beitrag'), halign: 'right', width: 25 },
	] as const

	const body: unknown[] = []
	const pushRows = (rows: MirrorGroup['rows']) => {
		for (const row of rows) {
			body.push([
				text(row.contract),
				text(row.vendor),
				text(row.scope),
				text(row.endDate),
				text(row.status),
				text(row.interval),
				text(row.amount),
			])
		}
	}
	for (const group of groups) {
		body.push([{
			content: text(group.title),
			colSpan: columns.length,
			styles: { fontStyle: 'bold', fontSize: 10.5, fillColor: [225, 225, 225], halign: 'left' },
		}])
		pushRows(group.rows)
		for (const sub of group.subgroups ?? []) {
			// Second level (Vertragsart below a Kategorie, #7)
			body.push([{
				content: text(sub.title),
				colSpan: columns.length,
				styles: { fontStyle: 'bold', fontSize: 9, fillColor: [246, 246, 246], halign: 'left', cellPadding: { top: 1.4, right: 1.8, bottom: 1.4, left: 4 } },
			}])
			pushRows(sub.rows)
		}
	}

	autoTable(doc, {
		head: [columns.map(c => ({ content: text(c.title), styles: { halign: c.halign } }))],
		body: body as never,
		theme: 'plain',
		startY: MARGIN + HEADER_HEIGHT,
		margin: { top: MARGIN + HEADER_HEIGHT, right: MARGIN, bottom: MARGIN, left: MARGIN },
		showHead: 'everyPage',
		rowPageBreak: 'avoid',
		styles: {
			font: 'helvetica',
			fontSize: 8.5,
			cellPadding: { top: 1.6, right: 1.8, bottom: 1.6, left: 1.8 },
			valign: 'top',
			overflow: 'linebreak',
			textColor: 20,
			lineColor: RULE_COLOR,
			lineWidth: { bottom: 0.2 },
		},
		headStyles: {
			fontStyle: 'bolditalic',
			fontSize: 9.5,
			lineColor: 0,
			lineWidth: { bottom: 0.4 },
		},
		columnStyles: Object.fromEntries(columns.map((c, i) => [i, { halign: c.halign, cellWidth: c.width }])),
	})

	// Header in a second pass, when the page count is known: a placeholder
	// replaced afterwards would leave the right-aligned page number off by the
	// width difference.
	const pageCount = doc.getNumberOfPages()
	const lineY = MARGIN + 12.5
	for (let page = 1; page <= pageCount; page++) {
		doc.setPage(page)
		doc.setTextColor(0)
		doc.setFont('helvetica', 'bold')
		doc.setFontSize(15)
		doc.text(headerTitle, pageWidth / 2, MARGIN + 6, { align: 'center' })
		doc.setFont('helvetica', 'normal')
		doc.setFontSize(9)
		doc.text(standLine, MARGIN, lineY)
		doc.text(
			toWinAnsi(t('nc_vertragswerk', 'Seite {page} von {total}', { page: String(page), total: String(pageCount) }, undefined, PLAIN)),
			pageWidth - MARGIN,
			lineY,
			{ align: 'right' },
		)
	}

	return doc.output('blob')
}
