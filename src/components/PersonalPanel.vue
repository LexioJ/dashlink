<template>
	<div class="dashlink-personal">
		<div class="personal-header">
			<h2>My Links</h2>
			<p class="subtitle">Manage your personal dashboard links</p>
		</div>

		<div class="personal-layout">
			<div class="personal-main">
				<!-- Link Count and Limit -->
				<div class="link-count-section">
					<span class="link-count">{{ linkCount }} / {{ linkLimit }} links used</span>
					<div class="progress-bar">
						<div class="progress-fill" :style="{ width: progressPercent + '%' }" />
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
								Import
							</NcButton>
							<NcButton
								type="secondary"
								@click="handleExport">
								<template #icon>
									<Download :size="20" />
								</template>
								Export
							</NcButton>
							<NcButton
								type="primary"
								:disabled="linkCount >= linkLimit"
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
						<p>No personal links yet. Click "Add Link" to create one.</p>
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
						<UserLinkForm
							:link="editingLink"
							@save="handleSaveLink"
							@cancel="closeLinkForm"
							@icon-updated="handleIconUpdated" />
					</div>
				</NcModal>
			</div>

			<div class="personal-sidebar">
				<div class="preview-info">
					<h3>Preview</h3>
					<p class="hint">Your links will appear in the dashboard widget after admin links.</p>
				</div>
				<WidgetPreview :links="previewLinks" :effect="hoverEffect" title="DashLink" />
			</div>
		</div>
	</div>
</template>

<script>
import { defineComponent, ref, computed, onMounted } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcModal from '@nextcloud/vue/components/NcModal'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Eye from 'vue-material-design-icons/Eye.vue'
import EyeOff from 'vue-material-design-icons/EyeOff.vue'
import Download from 'vue-material-design-icons/Download.vue'
import Upload from 'vue-material-design-icons/Upload.vue'
import { showSuccess, showError } from '../utils/notifications.js'
import WidgetPreview from './WidgetPreview.vue'
import UserLinkForm from './UserLinkForm.vue'
import { useUserLinks } from '../composables/useUserLinks'

export default defineComponent({
	name: 'PersonalPanel',
	components: {
		NcButton,
		NcModal,
		Plus,
		Pencil,
		Delete,
		Eye,
		EyeOff,
		Download,
		Upload,
		WidgetPreview,
		UserLinkForm,
	},
	setup() {
		const { links, linkCount, linkLimit, fetchLinks, createLink, updateLink, deleteLink, updateOrder, exportLinks, importLinks } = useUserLinks()

		const showLinkForm = ref(false)
		const editingLink = ref(null)
		const draggingId = ref(null)
		const dragOverId = ref(null)
		const fileInput = ref(null)

		// Get hover effect from app config (users use global effect)
		const hoverEffect = ref('blur')
		try {
			hoverEffect.value = loadState('dashlink', 'hoverEffect', 'blur')
		} catch (e) {
			// Fallback to blur if initial state not available
		}

		const sortedLinks = computed(() => {
			return [...links.value].sort((a, b) => a.position - b.position)
		})

		const progressPercent = computed(() => {
			if (linkLimit.value === 0) return 0
			return Math.min((linkCount.value / linkLimit.value) * 100, 100)
		})

		// Preview only enabled links, limited to 10
		const previewLinks = computed(() => {
			return sortedLinks.value.filter(l => l.enabled).slice(0, 10)
		})

		onMounted(async () => {
			await fetchLinks()
		})

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
				if (editingLink.value) {
					await updateLink(editingLink.value.id, linkData)
					showSuccess('Link updated successfully')
				} else {
					await createLink(linkData)
					showSuccess('Link created successfully')
				}
				closeLinkForm()
			} catch (error) {
				showError('Failed to save link: ' + error.message)
			}
		}

		async function handleIconUpdated(updatedLink) {
			await fetchLinks()
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

			const sorted = sortedLinks.value
			const draggedIndex = sorted.findIndex(l => l.id === draggedId)
			const targetIndex = sorted.findIndex(l => l.id === targetLink.id)

			if (draggedIndex === -1 || targetIndex === -1) {
				handleDragEnd()
				return
			}

			const newOrder = [...sorted]
			const [draggedItem] = newOrder.splice(draggedIndex, 1)
			newOrder.splice(targetIndex, 0, draggedItem)

			newOrder.forEach((link, index) => {
				const linkInArray = links.value.find(l => l.id === link.id)
				if (linkInArray) {
					linkInArray.position = index
				}
			})

			try {
				const linkIds = newOrder.map(l => l.id)
				await updateOrder(linkIds)
				showSuccess('Link order updated')
			} catch (error) {
				showError('Failed to update order: ' + error.message)
				await fetchLinks()
			}

			handleDragEnd()
		}

		async function handleExport() {
			try {
				const data = await exportLinks()
				const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
				const url = URL.createObjectURL(blob)
				const link = document.createElement('a')
				link.href = url
				link.download = `my-dashlinks-${new Date().toISOString().split('T')[0]}.json`
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
				event.target.value = ''
			}
		}

		return {
			links,
			linkCount,
			linkLimit,
			hoverEffect,
			sortedLinks,
			previewLinks,
			progressPercent,
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
.dashlink-personal {
	padding: 20px;
	max-width: 1400px;
	margin: 0 auto;
}

.personal-header {
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

.personal-layout {
	display: grid;
	grid-template-columns: 1fr;
	gap: 30px;

	@media (min-width: 1024px) {
		grid-template-columns: 1fr 400px;
	}
}

.link-count-section {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 16px 20px;
	margin-bottom: 20px;

	.link-count {
		display: block;
		font-weight: 500;
		margin-bottom: 8px;
		font-size: 14px;
	}

	.progress-bar {
		height: 8px;
		background: var(--color-background-dark);
		border-radius: 4px;
		overflow: hidden;

		.progress-fill {
			height: 100%;
			background: var(--color-primary-element);
			border-radius: 4px;
			transition: width 0.3s ease;
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
	flex-wrap: wrap;
	gap: 12px;

	h3 {
		margin: 0;
		font-size: 18px;
		font-weight: 600;
	}
}

.section-actions {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
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

.preview-info {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 16px;
	margin-bottom: 16px;

	h3 {
		margin: 0 0 8px 0;
		font-size: 16px;
		font-weight: 600;
	}

	.hint {
		margin: 0;
		font-size: 13px;
		color: var(--color-text-maxcontrast);
	}
}
</style>
