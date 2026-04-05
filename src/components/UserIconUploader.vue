<template>
	<div class="icon-uploader">
		<div
			class="upload-area"
			:class="{ 'is-dragging': isDragging }"
			@drop.prevent="handleDrop"
			@dragover.prevent="isDragging = true"
			@dragleave.prevent="isDragging = false"
			@click="triggerFileInput">
			<div v-if="previewUrl || currentIcon" class="icon-preview">
				<img :src="previewUrl || currentIcon" alt="Icon preview">
				<button
					v-if="linkId"
					type="button"
					class="remove-icon"
					@click.stop="removeIcon">
					<Close :size="16" />
				</button>
			</div>
			<div v-else class="upload-placeholder">
				<Upload :size="32" />
				<span>Click or drag to upload icon</span>
				<span class="hint">PNG, JPG, SVG up to 2MB</span>
			</div>
		</div>

		<input
			ref="fileInput"
			type="file"
			accept="image/*"
			style="display: none"
			@change="handleFileSelect">
	</div>
</template>

<script>
import { defineComponent, ref } from 'vue'
import Upload from 'vue-material-design-icons/Upload.vue'
import Close from 'vue-material-design-icons/Close.vue'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError } from '../utils/notifications.js'

export default defineComponent({
	name: 'UserIconUploader',
	components: {
		Upload,
		Close,
	},
	props: {
		linkId: {
			type: Number,
			default: null,
		},
		currentIcon: {
			type: String,
			default: null,
		},
	},
	emits: ['uploaded', 'removed'],
	setup(props, { emit }) {
		const fileInput = ref(null)
		const isDragging = ref(false)
		const previewUrl = ref(null)

		function triggerFileInput() {
			fileInput.value?.click()
		}

		async function handleFileSelect(event) {
			const file = event.target.files?.[0]
			if (file) {
				await uploadFile(file)
			}
		}

		async function handleDrop(event) {
			isDragging.value = false
			const file = event.dataTransfer?.files?.[0]
			if (file && file.type.startsWith('image/')) {
				await uploadFile(file)
			}
		}

		async function uploadFile(file) {
			// Show preview immediately from local file for better UX
			const reader = new FileReader()
			reader.onload = (e) => {
				previewUrl.value = e.target?.result
			}
			reader.readAsDataURL(file)

			if (!props.linkId) {
				// For new links, only show preview - no upload yet
				return
			}

			try {
				const formData = new FormData()
				formData.append('icon', file)

				const response = await axios.post(
					generateUrl('/apps/dashlink/api/v1/user/links/{id}/icon', { id: props.linkId }),
					formData,
					{
						headers: {
							'Content-Type': 'multipart/form-data',
						},
					}
				)

				// Update preview with server URL after successful upload
				const link = response.data
				if (link && link.iconUrl) {
					previewUrl.value = link.iconUrl + '?t=' + Date.now() // Cache bust
				}

				// Reset file input to allow re-uploading
				if (fileInput.value) {
					fileInput.value.value = ''
				}

				emit('uploaded', link)
			} catch (error) {
				// Clear preview on error
				previewUrl.value = null
				showError('Failed to upload icon: ' + error.message)
			}
		}

		async function removeIcon() {
			if (!props.linkId) {
				previewUrl.value = null
				return
			}

			try {
				const response = await axios.delete(
					generateUrl('/apps/dashlink/api/v1/user/links/{id}/icon', { id: props.linkId })
				)

				// Clear preview immediately
				previewUrl.value = null

				emit('removed', response.data)
			} catch (error) {
				showError('Failed to remove icon: ' + error.message)
			}
		}

		return {
			fileInput,
			isDragging,
			previewUrl,
			triggerFileInput,
			handleFileSelect,
			handleDrop,
			removeIcon,
		}
	},
})
</script>

<style lang="scss" scoped>
.upload-area {
	border: 2px dashed var(--color-border);
	border-radius: var(--border-radius);
	padding: 20px;
	text-align: center;
	cursor: pointer;
	transition: all 0.2s ease;

	&:hover,
	&.is-dragging {
		border-color: var(--color-primary);
		background: var(--color-primary-element-light);
	}
}

.icon-preview {
	position: relative;
	display: inline-block;

	img {
		width: 80px;
		height: 80px;
		object-fit: contain;
		border-radius: var(--border-radius);
	}

	.remove-icon {
		position: absolute;
		top: -10px;
		right: -10px;
		background: var(--color-error);
		color: white;
		border: 2px solid var(--color-main-background);
		border-radius: 50%;
		width: 28px;
		height: 28px;
		min-width: 28px;
		min-height: 28px;
		padding: 0;
		display: flex;
		align-items: center;
		justify-content: center;
		cursor: pointer;
		box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
		transition: all 0.2s ease;
		outline: none;

		&:hover {
			background: #c9302c;
			border-color: transparent;
			box-shadow: 0 2px 8px rgba(201, 48, 44, 0.5);
			transform: scale(1.1);
		}

		&:active {
			transform: scale(0.95);
		}
	}
}

.upload-placeholder {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;
	color: var(--color-text-maxcontrast);

	span {
		font-size: 14px;
	}

	.hint {
		font-size: 12px;
	}
}
</style>
