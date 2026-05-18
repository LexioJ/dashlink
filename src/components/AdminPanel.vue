<template>
	<div class="dashlink-admin">
		<div class="admin-header">
			<h2>DashLink Settings</h2>
			<p class="subtitle">Manage external links displayed on the dashboard</p>
		</div>

		<div class="admin-layout">
			<div class="admin-main">
				<!-- Widget Title -->
				<div class="setting-section">
					<label for="widget-title">Widget Title</label>
					<input
						id="widget-title"
						v-model="widgetTitle"
						type="text"
						placeholder="DashLink"
						@input="debouncedSaveSettings" />
					<p class="setting-hint">The title displayed in the dashboard widget</p>
				</div>

				<!-- Effect Selector -->
				<div class="setting-section">
					<EffectSelector v-model="hoverEffect" @update:modelValue="saveSettings" />
				</div>

				<!-- User Links Settings -->
				<div class="setting-section">
					<h4>User Links</h4>
					<p class="setting-hint">Allow users to create their own personal links visible only to them.</p>
					<div class="user-links-settings">
						<NcCheckboxRadioSwitch
							v-model="userLinksEnabled"
							type="switch"
							@update:modelValue="saveSettings">
							Allow users to create personal links
						</NcCheckboxRadioSwitch>
						<div v-if="userLinksEnabled" class="user-limit-setting">
							<label for="user-link-limit">Maximum links per user</label>
							<input
								id="user-link-limit"
								v-model.number="userLinkLimit"
								type="number"
								min="1"
								max="50"
								@input="handleLinkLimitInput">
							<span class="limit-hint">(1 - 50)</span>
						</div>
					</div>
				</div>

				<!-- Links Management -->
				<div class="links-section">
					<div class="section-header">
						<h3>Links</h3>
						<div class="section-actions">
							<NcButton
								type="secondary"
								@click="handleImport">
								<template #icon>
									<Upload :size="20" />
								</template>
								Import List
							</NcButton>
							<NcButton
								type="secondary"
								@click="handleExport">
								<template #icon>
									<Download :size="20" />
								</template>
								Export List
							</NcButton>
							<NcButton
								type="primary"
								@click="showLinkForm = true">
								<template #icon>
									<Plus :size="20" />
								</template>
								Add Link
							</NcButton>
						</div>
					</div>
					<input
						ref="fileInput"
						type="file"
						accept=".json"
						style="display: none"
						@change="handleFileSelected">

					<div v-if="links.length === 0" class="empty-state">
						<p>No links yet. Click "Add Link" to create one.</p>
					</div>

					<div v-else class="links-list">
						<div
							v-for="(link, index) in sortedLinks"
							:key="link.id"
							class="link-item"
							:class="{ 'dragging': draggingId === link.id, 'drag-over': dragOverId === link.id }"
							draggable="true"
							@dragstart="handleDragStart(link, $event)"
							@dragend="handleDragEnd"
							@dragover.prevent="handleDragOver(link, $event)"
							@dragleave="handleDragLeave"
							@drop.prevent="handleDrop(link, $event)">
							<div class="link-position">
								{{ index + 1 }}
							</div>
							<div class="link-info">
								<img
									v-if="link.iconUrl"
									:src="link.iconUrl"
									:alt="link.title"
									class="link-icon">
								<div class="link-details">
									<strong>{{ link.title }}</strong>
									<span class="link-url">{{ link.url }}</span>
								</div>
							</div>
							<div class="link-actions">
								<NcButton
									:type="link.enabled ? 'success' : 'secondary'"
									@click="toggleEnabled(link)">
									<template #icon>
										<Eye v-if="link.enabled" :size="20" />
										<EyeOff v-else :size="20" />
									</template>
								</NcButton>
								<NcButton
									type="tertiary"
									@click="duplicateLink(link)">
									<template #icon>
										<ContentCopy :size="20" />
									</template>
								</NcButton>
								<NcButton
									type="tertiary"
									@click="editLink(link)">
									<template #icon>
										<Pencil :size="20" />
									</template>
								</NcButton>
								<NcButton
									type="error"
									@click="confirmDelete(link)">
									<template #icon>
										<Delete :size="20" />
									</template>
								</NcButton>
							</div>
						</div>
					</div>
				</div>

				<!-- Link Form Modal -->
				<NcModal
					v-if="showLinkForm"
					@close="closeLinkForm">
					<div class="modal-content">
						<h2>{{ editingLink ? 'Edit Link' : 'Add Link' }}</h2>
						<LinkForm
							:link="editingLink"
							:groups="groups"
							@save="handleSaveLink"
							@cancel="closeLinkForm"
							@icon-updated="handleIconUpdated" />
					</div>
				</NcModal>
			</div>

			<div class="admin-sidebar">
				<div class="preview-controls">
					<h3>Preview</h3>
					<div class="group-filter-section">
						<label>Filter by Group (Simulation)</label>
						<GroupPicker
							v-model="previewFilterGroups"
							placeholder="All users (no filter)"
							hint="Simulate how the widget appears to specific groups" />
					</div>
				</div>
				<WidgetPreview :links="filteredPreviewLinks" :effect="hoverEffect" :title="widgetTitle" />
			</div>
		</div>
	</div>
</template>

<script>
import { defineComponent, ref, computed, onMounted, onUnmounted, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Eye from 'vue-material-design-icons/Eye.vue'
import EyeOff from 'vue-material-design-icons/EyeOff.vue'
import Download from 'vue-material-design-icons/Download.vue'
import Upload from 'vue-material-design-icons/Upload.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '../utils/notifications.js'
import EffectSelector from './EffectSelector.vue'
import WidgetPreview from './WidgetPreview.vue'
import LinkForm from './LinkForm.vue'
import GroupPicker from './GroupPicker.vue'
import { useLinks } from '../composables/useLinks'
import { useGroups } from '../composables/useGroups'
import { useSettings } from '../composables/useSettings'

export default defineComponent({
	name: 'AdminPanel',
	components: {
		NcButton,
		NcModal,
		NcCheckboxRadioSwitch,
		Plus,
		Pencil,
		Delete,
		Eye,
		EyeOff,
		Download,
		Upload,
		ContentCopy,
		EffectSelector,
		WidgetPreview,
		LinkForm,
		GroupPicker,
	},
	setup() {
		const { links, fetchLinks, createLink, updateLink, deleteLink, updateOrder, exportLinks, importLinks } = useLinks()
		const { groups, fetchGroups } = useGroups()
		const { hoverEffect, widgetTitle, userLinksEnabled, userLinkLimit, fetchSettings, saveSettings: saveSettingsComposable } = useSettings()

		const showLinkForm = ref(false)
		const editingLink = ref(null)
		const saveTimeout = ref(null)
		const previewFilterGroups = ref([])
		const draggingId = ref(null)
		const dragOverId = ref(null)
		const fileInput = ref(null)

		const sortedLinks = computed(() => {
			return [...links.value].sort((a, b) => a.position - b.position)
		})

		// Clamp userLinkLimit to 1-50
		watch(userLinkLimit, (val) => {
			if (val !== null && val !== undefined && val !== '') {
				if (val > 50) {
					userLinkLimit.value = 50
				} else if (val < 1) {
					userLinkLimit.value = 1
				}
			}
		})

		// Filter preview links by selected groups (simulation), then limit to 10
		const filteredPreviewLinks = computed(() => {
			// Start with enabled links only
			let filtered = sortedLinks.value.filter(l => l.enabled)

			// Apply group filter
			if (previewFilterGroups.value.length === 0) {
				// No filter - show only links with no group restrictions (available to all users)
				filtered = filtered.filter(link => !link.groups || link.groups.length === 0)
			} else {
				// Filter links that match the selected preview groups
				const selectedGroupIds = previewFilterGroups.value.map(g => g.id)
				filtered = filtered.filter(link => {
					// If link has no groups restriction, show it
					if (!link.groups || link.groups.length === 0) {
						return true
					}
					// Check if any of the link's groups match the preview filter groups
					return link.groups.some(groupId => selectedGroupIds.includes(groupId))
				})
			}

			// Limit to maximum 10 links for preview
			return filtered.slice(0, 10)
		})

		onMounted(async () => {
			await Promise.all([
				fetchLinks(),
				fetchGroups(),
				fetchSettings(),
			])
			document.addEventListener('keydown', handleGlobalKeydown)
		})

		onUnmounted(() => {
			document.removeEventListener('keydown', handleGlobalKeydown)
		})

		function handleGlobalKeydown(event) {
			if (event.key === 'Escape' && showLinkForm.value) {
				closeLinkForm()
			}
		}

		function handleLinkLimitInput() {
			// Clamp immediately on input as well
			if (userLinkLimit.value > 50) {
				userLinkLimit.value = 50
			} else if (userLinkLimit.value < 1 && userLinkLimit.value !== '' && userLinkLimit.value !== null) {
				userLinkLimit.value = 1
			}
			debouncedSaveSettings()
		}

		function debouncedSaveSettings() {
			if (saveTimeout.value) {
				clearTimeout(saveTimeout.value)
			}
			saveTimeout.value = setTimeout(() => {
				saveSettings()
			}, 2000)
		}

		function editLink(link) {
			editingLink.value = link
			showLinkForm.value = true
		}

		function closeLinkForm() {
			showLinkForm.value = false
			editingLink.value = null
		}

		async function handleSaveLink(linkData) {
			try {
				// Extract the pending icon file before sending link data
				const pendingIconFile = linkData.pendingIconFile
				delete linkData.pendingIconFile

				if (editingLink.value) {
					await updateLink(editingLink.value.id, linkData)
					showSuccess('Link updated successfully')
				} else {
					const newLink = await createLink(linkData)
					showSuccess('Link created successfully')

					// If there was a pending icon file, upload it now that we have the link ID
					if (pendingIconFile && newLink && newLink.id) {
						try {
							const formData = new FormData()
							formData.append('icon', pendingIconFile)

							await axios.post(
								generateUrl('/apps/dashlink/api/v1/admin/links/{id}/icon', { id: newLink.id }),
								formData,
								{
									headers: {
										'Content-Type': 'multipart/form-data',
									},
								}
							)
							// Refresh links to show the icon
							await fetchLinks()
						} catch (iconError) {
							showError('Link created but icon upload failed: ' + iconError.message)
						}
					}
				}
				closeLinkForm()
			} catch (error) {
				showError('Failed to save link: ' + error.message)
			}
		}

		async function handleIconUpdated(updatedLink) {
			// Refresh the links list to show the updated icon
			await fetchLinks()

			// Update the editing link with the new icon URL
			if (editingLink.value && updatedLink) {
				editingLink.value = updatedLink
			}
		}

		async function confirmDelete(link) {
			if (confirm(`Are you sure you want to delete "${link.title}"?`)) {
				try {
					await deleteLink(link.id)
					showSuccess('Link deleted successfully')
				} catch (error) {
					showError('Failed to delete link: ' + error.message)
				}
			}
		}

		async function toggleEnabled(link) {
			try {
				await updateLink(link.id, { enabled: !link.enabled })
				showSuccess(link.enabled ? 'Link disabled' : 'Link enabled')
			} catch (error) {
				showError('Failed to update link: ' + error.message)
			}
		}

		async function duplicateLink(link) {
			try {
				const response = await axios.post(
					generateUrl('/apps/dashlink/api/v1/admin/duplicate/{id}', { id: link.id })
				)
				await fetchLinks()
				showSuccess('Link duplicated successfully')
				const newLink = links.value.find(l => l.id === response.data.id) || response.data
				editLink(newLink)
			} catch (error) {
				showError('Failed to duplicate link: ' + error.message)
			}
		}

		async function saveSettings() {
			try {
				await saveSettingsComposable()
				showSuccess('Settings saved')
			} catch (error) {
				showError('Failed to save settings: ' + error.message)
			}
		}

		// Drag and drop handlers
		function handleDragStart(link, event) {
			draggingId.value = link.id
			event.dataTransfer.effectAllowed = 'move'
			event.dataTransfer.setData('text/plain', link.id)
		}

		function handleDragEnd() {
			draggingId.value = null
			dragOverId.value = null
		}

		function handleDragOver(link, event) {
			if (draggingId.value && draggingId.value !== link.id) {
				dragOverId.value = link.id
			}
		}

		function handleDragLeave() {
			dragOverId.value = null
		}

		async function handleDrop(targetLink, event) {
			event.preventDefault()

			const draggedId = draggingId.value
			if (!draggedId || draggedId === targetLink.id) {
				handleDragEnd()
				return
			}

			// Find indices
			const sorted = sortedLinks.value
			const draggedIndex = sorted.findIndex(l => l.id === draggedId)
			const targetIndex = sorted.findIndex(l => l.id === targetLink.id)

			if (draggedIndex === -1 || targetIndex === -1) {
				handleDragEnd()
				return
			}

			// Create new order
			const newOrder = [...sorted]
			const [draggedItem] = newOrder.splice(draggedIndex, 1)
			newOrder.splice(targetIndex, 0, draggedItem)

			// Update positions in links array
			newOrder.forEach((link, index) => {
				const linkInArray = links.value.find(l => l.id === link.id)
				if (linkInArray) {
					linkInArray.position = index
				}
			})

			// Save to server
			try {
				const linkIds = newOrder.map(l => l.id)
				await updateOrder(linkIds)
				showSuccess('Link order updated')
			} catch (error) {
				showError('Failed to update order: ' + error.message)
				// Revert on error
				await fetchLinks()
			}

			handleDragEnd()
		}

		// Export/Import handlers
		async function handleExport() {
			try {
				const data = await exportLinks()
				const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
				const url = URL.createObjectURL(blob)
				const link = document.createElement('a')
				link.href = url
				link.download = `dashlink-export-${new Date().toISOString().split('T')[0]}.json`
				document.body.appendChild(link)
				link.click()
				document.body.removeChild(link)
				URL.revokeObjectURL(url)
				showSuccess('Links exported successfully')
			} catch (error) {
				showError('Failed to export links: ' + error.message)
			}
		}

		function handleImport() {
			fileInput.value?.click()
		}

		async function handleFileSelected(event) {
			const file = event.target.files[0]
			if (!file) return

			try {
				const result = await importLinks(file)
				const message = `Import complete: ${result.imported} imported, ${result.skipped} skipped`
				if (result.errors && result.errors.length > 0) {
					showError(message + `. Errors: ${result.errors.join(', ')}`)
				} else {
					showSuccess(message)
				}
				await fetchLinks()
			} catch (error) {
				showError('Failed to import links: ' + error.message)
			} finally {
				// Reset file input
				event.target.value = ''
			}
		}

		return {
			links,
			groups,
			hoverEffect,
			widgetTitle,
			userLinksEnabled,
			userLinkLimit,
			sortedLinks,
			filteredPreviewLinks,
			previewFilterGroups,
			showLinkForm,
			editingLink,
			draggingId,
			dragOverId,
			fileInput,
			editLink,
			closeLinkForm,
			handleSaveLink,
			handleIconUpdated,
			confirmDelete,
			toggleEnabled,
			duplicateLink,
			saveSettings,
			debouncedSaveSettings,
			handleLinkLimitInput,
			handleDragStart,
			handleDragEnd,
			handleDragOver,
			handleDragLeave,
			handleDrop,
			handleExport,
			handleImport,
			handleFileSelected,
		}
	},
})
</script>

<style lang="scss" scoped>
.dashlink-admin {
	padding: 20px;
	max-width: 1400px;
	margin: 0 auto;
}

.admin-header {
	margin-bottom: 30px;

	h2 {
		margin: 0 0 8px 0;
		font-size: 24px;
		font-weight: 600;
	}

	.subtitle {
		margin: 0;
		color: var(--color-text-maxcontrast);
		font-size: 14px;
	}
}

.admin-layout {
	display: grid;
	grid-template-columns: 1fr;
	gap: 30px;

	@media (min-width: 1024px) {
		grid-template-columns: 1fr 400px;
	}
}

.setting-section {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 20px;
	margin-bottom: 20px;
	overflow: visible;

	h4 {
		margin: 0 0 8px 0;
		font-size: 16px;
		font-weight: 600;
	}

	label {
		display: block;
		font-weight: 600;
		margin-bottom: 8px;
		font-size: 14px;
	}

	input[type="text"] {
		width: 100%;
		padding: 10px 12px;
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius);
		background: var(--color-main-background);
		color: var(--color-main-text);
		font-size: 14px;

		&:focus {
			outline: none;
			border-color: var(--color-primary-element);
		}
	}

	input[type="number"] {
		width: 80px;
		padding: 8px 12px;
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius);
		background: var(--color-main-background);
		color: var(--color-main-text);
		font-size: 14px;

		&:focus {
			outline: none;
			border-color: var(--color-primary-element);
		}
	}

	.setting-hint {
		margin: 8px 0 0 0;
		font-size: 12px;
		color: var(--color-text-maxcontrast);
	}
}

.user-links-settings {
	margin-top: 12px;

	.user-limit-setting {
		display: flex;
		align-items: center;
		gap: 12px;
		margin-top: 12px;
		padding-top: 12px;
		border-top: 1px solid var(--color-border);

		label {
			margin: 0;
			font-weight: 500;
		}

		.limit-hint {
			font-size: 12px;
			color: var(--color-text-maxcontrast);
		}
	}
}

.links-section {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 20px;
}

.section-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 20px;

	h3 {
		margin: 0;
		font-size: 18px;
		font-weight: 600;
	}
}

.section-actions {
	display: flex;
	gap: 8px;
}

.links-list {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.link-item {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 12px;
	background: var(--color-background-hover);
	border-radius: var(--border-radius);
	transition: all 0.2s ease;
	cursor: move;

	&:hover {
		background: var(--color-background-dark);
	}

	&.dragging {
		opacity: 0.5;
		background: var(--color-primary-element-light);
	}

	&.drag-over {
		border: 2px dashed var(--color-primary-element);
		background: var(--color-primary-element-light);
	}
}

.link-position {
	display: flex;
	align-items: center;
	justify-content: center;
	min-width: 32px;
	height: 32px;
	margin-right: 12px;
	font-weight: 600;
	font-size: 14px;
	color: var(--color-text-maxcontrast);
	background: var(--color-background-dark);
	border-radius: var(--border-radius);
	flex-shrink: 0;
}

.link-info {
	display: flex;
	align-items: center;
	gap: 12px;
	flex: 1;
	min-width: 0;
}

.link-icon {
	width: 32px;
	height: 32px;
	border-radius: var(--border-radius);
	flex-shrink: 0;
}

.link-details {
	display: flex;
	flex-direction: column;
	min-width: 0;

	strong {
		font-weight: 500;
	}

	.link-url {
		font-size: 12px;
		color: var(--color-text-maxcontrast);
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
}

.link-actions {
	display: flex;
	gap: 4px;
}

.empty-state {
	text-align: center;
	padding: 40px 20px;
	color: var(--color-text-maxcontrast);
}

.modal-content {
	padding: 20px;

	h2 {
		margin: 0 0 20px 0;
		font-size: 20px;
		font-weight: 600;
	}
}

.preview-controls {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 16px;
	margin-bottom: 16px;

	h3 {
		margin: 0 0 16px 0;
		font-size: 16px;
		font-weight: 600;
	}
}

.group-filter-section {
	label {
		display: block;
		font-weight: 500;
		margin-bottom: 8px;
		font-size: 13px;
	}
}
</style>
