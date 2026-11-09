import axios from '@nextcloud/axios'
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'

/**
 * Detect whether a mainDocument value is a URL or a legacy file path
 *
 * @param {string} value mainDocument field value
 * @return {boolean}
 */
export function isUrl(value) {
	return !!value && (value.startsWith('http://') || value.startsWith('https://'))
}

/**
 * Detect whether a URL points to the current Nextcloud instance
 *
 * @param {string} value URL string
 * @return {boolean}
 */
export function isInternalUrl(value) {
	if (!isUrl(value)) return false
	return value.startsWith(window.location.origin)
}

/**
 * Extract a human-readable display name from a mainDocument value
 *
 * - Legacy path (/Documents/file.pdf) → basename ("file.pdf")
 * - Internal URL (https://nc.example.com/f/123) → "Nextcloud Datei"
 * - External URL (https://provider.com/contract.pdf) → hostname ("provider.com")
 *
 * @param {string} value mainDocument field value
 * @return {string}
 */
export function getDisplayName(value) {
	if (!value) return ''

	if (!isUrl(value)) {
		// Legacy path
		return value.split('/').filter(s => s).pop() || value
	}

	try {
		const url = new URL(value)
		if (isInternalUrl(value)) {
			const parts = url.pathname.split('/').filter(s => s)
			const lastPart = parts[parts.length - 1]
			if (lastPart && lastPart.includes('.')) return decodeURIComponent(lastPart)
			return 'Nextcloud Datei'
		}
		return url.hostname
	} catch {
		return value
	}
}

/**
 * Fetch the file ID of a path in the user's files via WebDAV.
 *
 * @param {string} path Path relative to the user's files, e.g. /Verträge/x.pdf
 * @return {Promise<string|null>}
 */
async function resolveFileId(path) {
	const user = getCurrentUser()?.uid
	const response = await axios({
		method: 'PROPFIND',
		url: `/remote.php/dav/files/${encodeURIComponent(user)}${path.split('/').map(encodeURIComponent).join('/')}`,
		headers: { Depth: '0' },
		data: `<?xml version="1.0"?>
			<d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">
				<d:prop><oc:fileid/></d:prop>
			</d:propfind>`,
	})
	const xml = new DOMParser().parseFromString(response.data, 'application/xml')
	return xml.getElementsByTagNameNS('http://owncloud.org/ns', 'fileid')[0]?.textContent || null
}

/**
 * Open a contract document in a new tab — external links and Nextcloud files
 * alike (fds2610/contractmanager#4), so the workflow does not depend on where
 * the document lives.
 *
 * The tab is opened synchronously inside the click: browsers block
 * window.open after an await. For a file path its address is set once the
 * file ID is known (/f/{id} shows the file in Files), otherwise the folder is
 * shown with the file highlighted.
 *
 * @param {string} value mainDocument field value (URL or path)
 */
export async function openDocumentInNewTab(value) {
	if (!value) return
	if (isUrl(value)) {
		window.open(value, '_blank', 'noopener,noreferrer')
		return
	}
	const tab = window.open('', '_blank')
	if (tab) tab.opener = null
	let url
	try {
		const fileId = await resolveFileId(value)
		if (fileId) url = generateUrl('/f/{fileId}', { fileId })
	} catch (e) {
		console.warn('[VertragsWerk] Could not resolve file ID:', e)
	}
	if (!url) {
		const parentDir = value.substring(0, value.lastIndexOf('/')) || '/'
		const fileName = value.substring(value.lastIndexOf('/') + 1)
		url = generateUrl('/apps/files/?dir={dir}&scrollto={file}', { dir: parentDir, file: fileName })
	}
	if (tab) {
		tab.location.href = url
	} else {
		window.open(url, '_blank', 'noopener,noreferrer')
	}
}
