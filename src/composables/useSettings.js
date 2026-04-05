import { ref } from 'vue'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export function useSettings() {
	const hoverEffect = ref('blur')
	const widgetTitle = ref('DashLink')
	const availableEffects = ref([])
	const userLinksEnabled = ref(true)
	const userLinkLimit = ref(10)
	const loading = ref(false)

	async function fetchSettings() {
		loading.value = true
		try {
			const response = await axios.get(generateUrl('/apps/dashlink/api/v1/admin/settings'))
			hoverEffect.value = response.data.hoverEffect
			widgetTitle.value = response.data.widgetTitle
			availableEffects.value = response.data.availableEffects
			userLinksEnabled.value = response.data.userLinksEnabled ?? true
			userLinkLimit.value = response.data.userLinkLimit ?? 10
		} catch (error) {
			console.error('Failed to fetch settings:', error)
			throw error
		} finally {
			loading.value = false
		}
	}

	async function saveSettings() {
		try {
			await axios.put(
				generateUrl('/apps/dashlink/api/v1/admin/settings'),
				{
					hoverEffect: hoverEffect.value,
					widgetTitle: widgetTitle.value,
					userLinksEnabled: userLinksEnabled.value,
					userLinkLimit: userLinkLimit.value
				}
			)
		} catch (error) {
			console.error('Failed to save settings:', error)
			throw error
		}
	}

	// Keep for backwards compatibility
	async function saveHoverEffect() {
		return saveSettings()
	}

	return {
		hoverEffect,
		widgetTitle,
		availableEffects,
		userLinksEnabled,
		userLinkLimit,
		loading,
		fetchSettings,
		saveSettings,
		saveHoverEffect,
	}
}
