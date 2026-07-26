<template>
	<form class="link-form" @submit.prevent="handleSubmit" @keydown.enter="handleEnterKey">
		<div class="form-group">
			<label for="title">Title *</label>
			<input
				id="title"
				v-model="formData.title"
				type="text"
				required
				placeholder="My Website">
		</div>

		<div class="form-group">
			<label for="url">URL *</label>
			<input
				id="url"
				v-model="formData.url"
				type="url"
				required
				placeholder="https://example.com">
		</div>

		<div class="form-group">
			<label for="description">Description</label>
			<textarea
				id="description"
				v-model="formData.description"
				rows="3"
				placeholder="Optional description shown on hover" />
		</div>

		<div class="form-group">
			<label for="target">Open in</label>
			<select id="target" v-model="formData.target">
				<option value="_blank">New tab</option>
				<option value="_self">Same tab</option>
			</select>
		</div>

		<div class="form-group">
			<label>Icon</label>
			<IconUploader
				ref="iconUploaderRef"
				:link-id="link?.id"
				:current-icon="currentIconUrl"
				@uploaded="handleIconUploaded"
				@removed="handleIconRemoved"
				@file-selected="handleFileSelected" />
		</div>

		<div class="form-group">
			<label>Visible to Groups</label>
			<GroupPicker
				v-model="formData.groupsObjects"
				hint="Leave empty to show to all users" />
		</div>

		<div class="form-group">
			<NcCheckboxRadioSwitch
				v-model="enabledSwitch"
				type="switch">
				Enabled
			</NcCheckboxRadioSwitch>
		</div>

		<div class="form-actions">
			<NcButton type="secondary" @click="$emit('cancel')">
				Cancel
			</NcButton>
			<NcButton type="primary" native-type="submit">
				{{ link ? 'Update' : 'Create' }}
			</NcButton>
		</div>
	</form>
</template>

<script>
import { defineComponent, ref, watch, computed } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import IconUploader from './IconUploader.vue'
import GroupPicker from './GroupPicker.vue'

export default defineComponent({
	name: 'LinkForm',
	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		IconUploader,
		GroupPicker,
	},
	props: {
		link: {
			type: Object,
			default: null,
		},
		groups: {
			type: Array,
			default: () => [],
		},
	},
	emits: ['save', 'cancel', 'icon-updated'],
	setup(props, { emit }) {
		const formData = ref({
			title: '',
			url: '',
			description: '',
			target: '_blank',
			groups: [],
			groupsObjects: [],
			enabled: true,
		})

		const currentIconUrl = ref(null)
		const iconUploaderRef = ref(null)
		const pendingIconFile = ref(null)

		// Initialize form with link data if editing
		watch(() => props.link, (link) => {
			if (link) {
				formData.value = {
					title: link.title || '',
					url: link.url || '',
					description: link.description || '',
					target: link.target || '_blank',
					groups: link.groups || [],
					// Convert group IDs to group objects for GroupPicker
					groupsObjects: (link.groups || []).map(groupId => ({ id: groupId, displayname: groupId })),
					// Convert enabled to boolean (backend uses 0/1)
					enabled: Boolean(link.enabled ?? 1),
				}
				currentIconUrl.value = link.iconUrl || null
			} else {
				formData.value = {
					title: '',
					url: '',
					description: '',
					target: '_blank',
					groups: [],
					groupsObjects: [],
					enabled: true,
				}
				currentIconUrl.value = null
			}
			pendingIconFile.value = null
		}, { immediate: true })

		// Watch groupsObjects and convert to groups array of IDs
		watch(() => formData.value.groupsObjects, (newGroups) => {
			formData.value.groups = newGroups.map(g => g.id)
		})

		// Computed property for enabled switch (converts between boolean and 0/1)
		const enabledSwitch = computed({
			get: () => Boolean(formData.value.enabled),
			set: (value) => {
				formData.value.enabled = value
			}
		})

		function handleEnterKey(event) {
			// Stop propagation so NcModal does not close
			event.stopPropagation()
			// Submit form on Enter, but not from textarea (allow newlines there)
			if (event.target.tagName !== 'TEXTAREA') {
				event.preventDefault()
				handleSubmit()
			}
		}
		function handleSubmit() {
			// Submit only the necessary fields (not groupsObjects)
			const { groupsObjects, ...dataToSave } = formData.value
			// Convert enabled boolean to 0/1 for backend
			dataToSave.enabled = dataToSave.enabled ? 1 : 0
			// Include the pending icon file if one was selected for a new link
			if (pendingIconFile.value) {
				dataToSave.pendingIconFile = pendingIconFile.value
			}
			emit('save', dataToSave)
		}

		function handleIconUploaded(updatedLink) {
			// Update the current icon URL immediately for preview
			if (updatedLink && updatedLink.iconUrl) {
				currentIconUrl.value = updatedLink.iconUrl
			}
			// Notify parent to refresh links
			emit('icon-updated', updatedLink)
		}

		function handleIconRemoved(updatedLink) {
			// Clear the icon URL immediately
			currentIconUrl.value = null
			// Notify parent to refresh links
			emit('icon-updated', updatedLink)
		}

		function handleFileSelected(file) {
			pendingIconFile.value = file
		}

		return {
			formData,
			currentIconUrl,
			iconUploaderRef,
			pendingIconFile,
			enabledSwitch,
			handleSubmit,
			handleEnterKey,
			handleIconUploaded,
			handleIconRemoved,
			handleFileSelected,
		}
	},
})
</script>

<style lang="scss" scoped>
.link-form {
	display: flex;
	flex-direction: column;
	gap: 16px;
	width: 100%;
}

.form-group {
	display: flex;
	flex-direction: column;
	gap: 6px;

	label {
		font-weight: 500;
		font-size: 14px;
	}

	input[type="text"],
	input[type="url"],
	textarea,
	select {
		width: 100%;
		padding: 8px 12px;
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius);
		background: var(--color-main-background);
		color: var(--color-main-text);
		font-size: 14px;
		box-sizing: border-box;

		&:focus {
			outline: 2px solid var(--color-primary);
			outline-offset: 0;
		}
	}

	textarea {
		resize: vertical;
		font-family: inherit;
	}
}

.form-actions {
	display: flex;
	justify-content: flex-end;
	gap: 12px;
	margin-top: 8px;
}
</style>
