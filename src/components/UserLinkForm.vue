<template>
	<form class="link-form" @submit.prevent="handleSubmit">
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
			<UserIconUploader
				:link-id="link?.id"
				:current-icon="currentIconUrl"
				@uploaded="handleIconUploaded"
				@removed="handleIconRemoved" />
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
import UserIconUploader from './UserIconUploader.vue'

export default defineComponent({
	name: 'UserLinkForm',
	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		UserIconUploader,
	},
	props: {
		link: {
			type: Object,
			default: null,
		},
	},
	emits: ['save', 'cancel', 'icon-updated'],
	setup(props, { emit }) {
		const formData = ref({
			title: '',
			url: '',
			description: '',
			target: '_blank',
			enabled: true,
		})

		const currentIconUrl = ref(null)

		// Initialize form with link data if editing
		watch(() => props.link, (link) => {
			if (link) {
				formData.value = {
					title: link.title || '',
					url: link.url || '',
					description: link.description || '',
					target: link.target || '_blank',
					enabled: Boolean(link.enabled ?? 1),
				}
				currentIconUrl.value = link.iconUrl || null
			} else {
				formData.value = {
					title: '',
					url: '',
					description: '',
					target: '_blank',
					enabled: true,
				}
				currentIconUrl.value = null
			}
		}, { immediate: true })

		// Computed property for enabled switch
		const enabledSwitch = computed({
			get: () => Boolean(formData.value.enabled),
			set: (value) => {
				formData.value.enabled = value
			}
		})

		function handleSubmit() {
			const dataToSave = { ...formData.value }
			dataToSave.enabled = dataToSave.enabled ? 1 : 0
			emit('save', dataToSave)
		}

		function handleIconUploaded(updatedLink) {
			if (updatedLink && updatedLink.iconUrl) {
				currentIconUrl.value = updatedLink.iconUrl
			}
			emit('icon-updated', updatedLink)
		}

		function handleIconRemoved(updatedLink) {
			currentIconUrl.value = null
			emit('icon-updated', updatedLink)
		}

		return {
			formData,
			currentIconUrl,
			enabledSwitch,
			handleSubmit,
			handleIconUploaded,
			handleIconRemoved,
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
