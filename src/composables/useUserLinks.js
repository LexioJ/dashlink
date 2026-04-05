import { ref } from 'vue'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export function useUserLinks() {
	const links = ref([])
	const loading = ref(false)
	const linkCount = ref(0)
	const linkLimit = ref(10)

	async function fetchLinks() {
		loading.value = true
		try {
			const response = await axios.get(generateUrl('/apps/dashlink/api/v1/user/links'))
			links.value = response.data.links.map(link => ({
				...link,
				iconUrl: link.iconPath
					? generateUrl('/apps/dashlink/api/v1/user/links/{id}/icon', { id: link.id })
					: null
			}))
			linkCount.value = response.data.count
			linkLimit.value = response.data.limit
		} catch (error) {
			console.error('Failed to fetch user links:', error)
			throw error
		} finally {
			loading.value = false
		}
	}

	async function createLink(linkData) {
		try {
			const response = await axios.post(
				generateUrl('/apps/dashlink/api/v1/user/links'),
				linkData
			)
			const link = {
				...response.data,
				iconUrl: response.data.iconPath
					? generateUrl('/apps/dashlink/api/v1/user/links/{id}/icon', { id: response.data.id })
					: null
			}
			links.value.push(link)
			linkCount.value++
			return link
		} catch (error) {
			console.error('Failed to create user link:', error)
			throw error
		}
	}

	async function updateLink(id, linkData) {
		try {
			const response = await axios.put(
				generateUrl('/apps/dashlink/api/v1/user/links/{id}', { id }),
				linkData
			)
			const link = {
				...response.data,
				iconUrl: response.data.iconPath
					? generateUrl('/apps/dashlink/api/v1/user/links/{id}/icon', { id: response.data.id })
					: null
			}
			const index = links.value.findIndex(l => l.id === id)
			if (index !== -1) {
				links.value[index] = link
			}
			return link
		} catch (error) {
			console.error('Failed to update user link:', error)
			throw error
		}
	}

	async function deleteLink(id) {
		try {
			await axios.delete(
				generateUrl('/apps/dashlink/api/v1/user/links/{id}', { id })
			)
			const index = links.value.findIndex(l => l.id === id)
			if (index !== -1) {
				links.value.splice(index, 1)
				linkCount.value--
			}
		} catch (error) {
			console.error('Failed to delete user link:', error)
			throw error
		}
	}

	async function updateOrder(linkIds) {
		try {
			await axios.put(
				generateUrl('/apps/dashlink/api/v1/user/links/order'),
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
				generateUrl('/apps/dashlink/api/v1/user/links/export')
			)
			return response.data
		} catch (error) {
			console.error('Failed to export user links:', error)
			throw error
		}
	}

	async function importLinks(file) {
		try {
			const formData = new FormData()
			formData.append('file', file)

			const response = await axios.post(
				generateUrl('/apps/dashlink/api/v1/user/links/import'),
				formData,
				{
					headers: {
						'Content-Type': 'multipart/form-data',
					},
				}
			)
			return response.data
		} catch (error) {
			console.error('Failed to import user links:', error)
			throw error
		}
	}

	async function uploadIcon(id, file) {
		try {
			const formData = new FormData()
			formData.append('icon', file)

			const response = await axios.post(
				generateUrl('/apps/dashlink/api/v1/user/links/{id}/icon', { id }),
				formData,
				{
					headers: {
						'Content-Type': 'multipart/form-data',
					},
				}
			)

			const link = {
				...response.data,
				iconUrl: response.data.iconPath
					? generateUrl('/apps/dashlink/api/v1/user/links/{id}/icon', { id: response.data.id })
					: null
			}

			const index = links.value.findIndex(l => l.id === id)
			if (index !== -1) {
				links.value[index] = link
			}

			return link
		} catch (error) {
			console.error('Failed to upload icon:', error)
			throw error
		}
	}

	async function deleteIcon(id) {
		try {
			const response = await axios.delete(
				generateUrl('/apps/dashlink/api/v1/user/links/{id}/icon', { id })
			)

			const link = {
				...response.data,
				iconUrl: null
			}

			const index = links.value.findIndex(l => l.id === id)
			if (index !== -1) {
				links.value[index] = link
			}

			return link
		} catch (error) {
			console.error('Failed to delete icon:', error)
			throw error
		}
	}

	return {
		links,
		loading,
		linkCount,
		linkLimit,
		fetchLinks,
		createLink,
		updateLink,
		deleteLink,
		updateOrder,
		exportLinks,
		importLinks,
		uploadIcon,
		deleteIcon,
	}
}
