<template>
	<div class="group-picker">
		<NcSelect
			v-model="selectedGroups"
			:input-label="label"
			:options="availableGroups"
			:placeholder="placeholder || t('dashlink', 'Select groups...')"
			:disabled="disabled"
			:multiple="true"
			:searchable="true"
			:tag-width="60"
			:loading="loadingGroups"
			:show-no-options="false"
			keep-open
			track-by="id"
			label="displayname"
			no-wrap
			@search-change="debounceSearchGroup" />
		<p v-if="hint" class="hint">{{ hint }}</p>
	</div>
</template>

<script>
import { defineComponent, ref, watch, onMounted, onBeforeUnmount } from 'vue'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import debounce from 'debounce'

export default defineComponent({
	name: 'GroupPicker',
	components: {
		NcSelect,
	},
	props: {
		modelValue: {
			type: Array,
			default: () => [],
		},
		label: {
			type: String,
			default: '',
		},
		placeholder: {
			type: String,
			default: '',
		},
		hint: {
			type: String,
			default: '',
		},
		disabled: {
			type: Boolean,
			default: false,
		},
	},
	emits: ['update:modelValue'],
	setup(props, { emit }) {
		const availableGroups = ref([])
		const selectedGroups = ref([])
		const loadingGroups = ref(false)
		const debounceSearchGroup = ref(() => {})
		const isInternalUpdate = ref(false)

		// Initialize selected groups from modelValue
		watch(() => props.modelValue, (newValue) => {
			if (isInternalUpdate.value) {
				isInternalUpdate.value = false
				return
			}
			if (Array.isArray(newValue)) {
				// Handle both string IDs and full group objects
				selectedGroups.value = newValue.map(group => {
					if (typeof group === 'object' && group.id) {
						return group
					}
					// If it's a string ID, find the full group object
					const foundGroup = availableGroups.value.find(g => g.id === group)
					return foundGroup || { id: group, displayname: group }
				})
			}
		}, { immediate: true })

		// Emit changes back to parent
		watch(selectedGroups, (newValue) => {
			// Emit array of group objects
			isInternalUpdate.value = true
			emit('update:modelValue', newValue)
		})

		async function searchGroup(query) {
			loadingGroups.value = true
			try {
				const response = await axios.get(generateOcsUrl('cloud/groups/details'), {
					params: {
						search: query || '',
						limit: 20,
						offset: 0,
					},
				})

				// Check if response has the expected structure
				if (response.data?.ocs?.data?.groups) {
					availableGroups.value = response.data.ocs.data.groups.sort((a, b) => {
						return a.displayname.localeCompare(b.displayname)
					})

					// Repopulate selected groups with full group objects
					const selectedGroupIds = selectedGroups.value.map((group) =>
						typeof group === 'object' ? group.id : group
					)
					const updatedSelectedGroups = availableGroups.value.filter((group) =>
						selectedGroupIds.includes(group.id)
					)

					// Preserve any groups that weren't found in the search
					const foundIds = updatedSelectedGroups.map(g => g.id)
					const preservedGroups = selectedGroups.value.filter(g =>
						!foundIds.includes(typeof g === 'object' ? g.id : g)
					)

					selectedGroups.value = [...updatedSelectedGroups, ...preservedGroups]
				} else {
					console.error('Unexpected API response structure', response.data)
					availableGroups.value = []
				}
			} catch (err) {
				console.error('Could not fetch groups', err)
				// Set empty array on error to prevent infinite loading
				availableGroups.value = []
			} finally {
				loadingGroups.value = false
			}
		}

		onMounted(() => {
			debounceSearchGroup.value = debounce(searchGroup, 500)
			debounceSearchGroup.value('') // Initial load
		})

		onBeforeUnmount(() => {
			debounceSearchGroup.value.clear?.()
		})

		return {
			availableGroups,
			selectedGroups,
			loadingGroups,
			debounceSearchGroup,
		}
	},
})
</script>

<style lang="scss" scoped>
.group-picker {
	display: flex;
	flex-direction: column;
	gap: 8px;

	.hint {
		margin: 0;
		font-size: 12px;
		color: var(--color-text-maxcontrast);
	}
}
</style>
