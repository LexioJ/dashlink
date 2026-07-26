import { ref } from 'vue'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export function useLinks() {
	const links = ref([])
	const loading = ref(false)

	async function fetchLinks() {
		loading.value = true
		try {
			const response = await axios.get(generateUrl('/apps/dashlink/api/v1/admin/links'))
			// Add iconUrl to each link
			links.value = response.data.map(link => ({
				...link,
				iconPath: link.iconPath,
				iconUrl: link.iconPath
					? generateUrl('/apps/dashlink/api/v1/links/{id}/icon', { id: link.id })
					: null
			}))
		} catch (error) {
			console.error('Failed to fetch links:', error)
			throw error
		} finally {
			loading.value = false
		}
	}

	async function createLink(linkData) {
		try {
			const response = await axios.post(
				generateUrl('/apps/dashlink/api/v1/admin/links'),
				linkData
			)
			const link = {
				...response.data,
				iconPath: response.data.iconPath,
				iconUrl: response.data.iconPath
					? generateUrl('/apps/dashlink/api/v1/links/{id}/icon', { id: response.data.id })
					: null
			}
			links.value.push(link)
			return link
		} catch (error) {
			console.error('Failed to create link:', error)
			throw error
		}
	}

	async function updateLink(id, linkData) {
		try {
			const response = await axios.put(
				generateUrl('/apps/dashlink/api/v1/admin/links/{id}', { id }),
				linkData
			)
			const link = {
				...response.data,
				iconPath: response.data.iconPath,
				iconUrl: response.data.iconPath
					? generateUrl('/apps/dashlink/api/v1/links/{id}/icon', { id: response.data.id })
					: null
			}
			const index = links.value.findIndex(l => l.id === id)
			if (index !== -1) {
				links.value[index] = link
			}
			return link
		} catch (error) {
			console.error('Failed to update link:', error)
			throw error
		}
	}

	async function deleteLink(id) {
		try {
			await axios.delete(
				generateUrl('/apps/dashlink/api/v1/admin/links/{id}', { id })
			)
			const index = links.value.findIndex(l => l.id === id)
			if (index !== -1) {
				links.value.splice(index, 1)
			}
		} catch (error) {
			console.error('Failed to delete link:', error)
			throw error
		}
	}

	async function updateOrder(linkIds) {
		try {
			await axios.put(
				generateUrl('/apps/dashlink/api/v1/admin/links/order'),
				{ linkIds }
			)
		} catch (error) {
			console.error('Failed to update order:', error)
			throw error
		}
	}

	async function exportLinks() {
		try {
			const response = await axios.get(
				generateUrl('/apps/dashlink/api/v1/admin/links/export')
			)
			return response.data
		} catch (error) {
			console.error('Failed to export links:', error)
			throw error
		}
	}

	async function importLinks(file) {
		try {
			const formData = new FormData()
			formData.append('file', file)

			const response = await axios.post(
				generateUrl('/apps/dashlink/api/v1/admin/links/import'),
				formData,
				{
					headers: {
						'Content-Type': 'multipart/form-data',
					},
				}
			)
			return response.data
		} catch (error) {
			console.error('Failed to import links:', error)
			throw error
		}
	}

	return {
		links,
		loading,
		fetchLinks,
		createLink,
		updateLink,
		deleteLink,
		updateOrder,
		exportLinks,
		importLinks,
	}
}
