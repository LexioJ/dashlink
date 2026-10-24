<template>
	<div class="dashlink-personal">
		<div class="personal-header">
			<h2>{{ t('dashlink', 'My Links') }}</h2>
			<p class="subtitle">{{ t('dashlink', 'Manage your personal dashboard links') }}</p>
		</div>

		<div class="personal-layout">
			<div class="personal-main">
				<!-- Link Count and Limit -->
				<div class="link-count-section">
					<span class="link-count">{{ t('dashlink', '{linkCount} / {linkLimit} links used', { linkCount, linkLimit }) }}</span>
					<div class="progress-bar">
						<div class="progress-fill" :style="{ width: progressPercent + '%' }" />
					</div>
				</div>

				<!-- Links Management -->
				<div class="links-section">
					<div class="section-header">
						<h3>{{ t('dashlink', 'Links') }}</h3>
						<div class="section-actions">
							<NcButton
								type="secondary"
								@click="handleImport">
								<template #icon>
									<Upload :size="20" />
								</template>
								{{ t('dashlink', 'Import') }}
							</NcButton>
							<NcButton
								type="secondary"
								@click="handleExport">
								<template #icon>
									<Download :size="20" />
								</template>
								{{ t('dashlink', 'Export') }}
							</NcButton>
							<NcButton
								type="primary"
								:disabled="atLinkLimit"
								:title="atLinkLimit ? t('dashlink', 'Personal link limit reached') : null"
								@click="showLinkForm = true">
								<template #icon>
									<Plus :size="20" />
								</template>
								{{ t('dashlink', 'Add Link') }}
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
						<p>{{ t('dashlink', 'No personal links yet. Click "Add Link" to create one.') }}</p>
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
								<span
									class="health-badge"
									:class="`health-badge--${link.healthStatus || 'unknown'}`"
									:title="healthTooltip(link)">
									{{ healthLabel(link.healthStatus) }}
								</span>
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

				<!-- Suggestions -->
				<div v-if="suggestions.length > 0" class="suggestions-section">
					<h3>{{ t('dashlink', 'Suggested by your organisation') }}</h3>
					<ul class="suggestions-list">
						<li v-for="suggestion in suggestions" :key="suggestion.id" class="suggestion-row">
							<img v-if="suggestion.iconPath"
								:src="suggestionIconUrl(suggestion)"
								class="suggestion-icon" alt="">
							<div class="suggestion-text">
								<strong>{{ suggestion.title }}</strong>
								<span v-if="suggestion.description" class="suggestion-description">{{ suggestion.description }}</span>
							</div>
							<NcButton
								v-if="!suggestion.adopted"
								:disabled="atLinkLimit"
								:title="atLinkLimit ? t('dashlink', 'Personal link limit reached') : null"
								@click="onAdopt(suggestion)">
								{{ t('dashlink', 'Add') }}
							</NcButton>
							<span v-else class="suggestion-adopted">{{ t('dashlink', 'Added') }}</span>
						</li>
					</ul>
				</div>

				<!-- Link Form Modal -->
				<NcModal
					v-if="showLinkForm"
					@close="closeLinkForm">
					<div class="modal-content">
						<h2>{{ editingLink ? t('dashlink', 'Edit Link') : t('dashlink', 'Add Link') }}</h2>
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
					<h3>{{ t('dashlink', 'Preview') }}</h3>
					<p class="hint">{{ t('dashlink', 'Your links will appear in the dashboard widget after admin links.') }}</p>
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
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
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
		const { links, linkCount, linkLimit, suggestions, fetchLinks, createLink, updateLink, deleteLink, updateOrder, exportLinks, importLinks, fetchSuggestions, adoptSuggestion } = useUserLinks()

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

		const atLinkLimit = computed(() => linkCount.value >= linkLimit.value)

		// Preview only enabled links, limited to 10
		const previewLinks = computed(() => {
			return sortedLinks.value.filter(l => l.enabled).slice(0, 10)
		})

		onMounted(async () => {
			await fetchLinks()
			await fetchSuggestions()
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
					showSuccess(t('dashlink', 'Link updated successfully'))
				} else {
					await createLink(linkData)
					showSuccess(t('dashlink', 'Link created successfully'))
				}
				closeLinkForm()
			} catch (error) {
				showError(t('dashlink', 'Failed to save link: {message}', { message: error.message }))
			}
		}

		async function handleIconUpdated(updatedLink) {
			await fetchLinks()
			if (editingLink.value && updatedLink) {
				editingLink.value = updatedLink
			}
		}

		async function confirmDelete(link) {
			if (confirm(t('dashlink', 'Are you sure you want to delete "{title}"?', { title: link.title }))) {
				try {
					await deleteLink(link.id)
					showSuccess(t('dashlink', 'Link deleted successfully'))
				} catch (error) {
					showError(t('dashlink', 'Failed to delete link: {message}', { message: error.message }))
				}
			}
		}

		async function toggleEnabled(link) {
			try {
				await updateLink(link.id, { enabled: !link.enabled })
				showSuccess(link.enabled ? t('dashlink', 'Link disabled') : t('dashlink', 'Link enabled'))
			} catch (error) {
				showError(t('dashlink', 'Failed to update link: {message}', { message: error.message }))
			}
		}

		function healthLabel(status) {
			return {
				ok: t('dashlink', 'OK'),
				redirect: t('dashlink', 'Redirect'),
				broken: t('dashlink', 'Broken'),
				unknown: t('dashlink', 'Unknown'),
			}[status || 'unknown']
		}

		function healthTooltip(link) {
			if (!link.healthCheckedAt) {
				return t('dashlink', 'Not checked yet')
			}
			const when = new Date(link.healthCheckedAt).toLocaleString()
			return link.healthDetail ? `${link.healthDetail} — ${when}` : when
		}

		function suggestionIconUrl(suggestion) {
			return generateUrl('/apps/dashlink/api/v1/links/{id}/icon', { id: suggestion.id })
		}

		async function onAdopt(suggestion) {
			try {
				await adoptSuggestion(suggestion.id)
				showSuccess(t('dashlink', 'Link added to your personal links'))
			} catch (error) {
				if (error.response?.data?.error === 'limit_reached') {
					showError(t('dashlink', 'Personal link limit reached'))
				} else {
					showError(t('dashlink', 'Could not add link'))
				}
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
				showSuccess(t('dashlink', 'Link order updated'))
			} catch (error) {
				showError(t('dashlink', 'Failed to update order: {message}', { message: error.message }))
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
				showSuccess(t('dashlink', 'Links exported successfully'))
			} catch (error) {
				showError(t('dashlink', 'Failed to export links: {message}', { message: error.message }))
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
				if (result.errors && result.errors.length > 0) {
					showError(t('dashlink', 'Import complete: {imported} imported, {skipped} skipped. Errors: {errors}', { imported: result.imported, skipped: result.skipped, errors: result.errors.join(', ') }))
				} else {
					showSuccess(t('dashlink', 'Import complete: {imported} imported, {skipped} skipped', { imported: result.imported, skipped: result.skipped }))
				}
				await fetchLinks()
			} catch (error) {
				showError(t('dashlink', 'Failed to import links: {message}', { message: error.message }))
			} finally {
				event.target.value = ''
			}
		}

		return {
			t,
			links,
			linkCount,
			linkLimit,
			suggestions,
			atLinkLimit,
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
			healthLabel,
			healthTooltip,
			suggestionIconUrl,
			onAdopt,
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
	align-items: center;
	gap: 4px;
}

.health-badge {
	border-radius: var(--border-radius-pill);
	padding: 2px 8px;
	font-size: 0.8em;
	white-space: nowrap;
}

.health-badge--ok {
	background: var(--color-success);
	color: var(--color-primary-element-text);
}

.health-badge--redirect {
	background: var(--color-warning);
	color: var(--color-primary-element-text);
}

.health-badge--broken {
	background: var(--color-error);
	color: var(--color-primary-element-text);
}

.health-badge--unknown {
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
}

.suggestions-section {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 20px;
	margin-top: 20px;

	h3 {
		margin: 0 0 16px 0;
		font-size: 18px;
		font-weight: 600;
	}
}

.suggestions-list {
	display: flex;
	flex-direction: column;
	gap: 12px;
	list-style: none;
	margin: 0;
	padding: 0;
}

.suggestion-row {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 12px;
	background: var(--color-background-hover);
	border-radius: var(--border-radius);
}

.suggestion-icon {
	width: 32px;
	height: 32px;
	border-radius: var(--border-radius);
	flex-shrink: 0;
}

.suggestion-text {
	display: flex;
	flex-direction: column;
	flex: 1;
	min-width: 0;

	strong {
		font-weight: 500;
	}

	.suggestion-description {
		font-size: 12px;
		color: var(--color-text-maxcontrast);
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
}

.suggestion-adopted {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
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
