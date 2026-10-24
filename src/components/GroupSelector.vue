<template>
	<div class="group-selector">
		<div class="hint">
			{{ t('dashlink', 'Leave empty to show to all users') }}
		</div>
		<div v-if="groups.length > 0" class="groups-list">
			<label
				v-for="group in groups"
				:key="group.id"
				class="group-item">
				<input
					v-model="selectedGroups"
					type="checkbox"
					:value="group.id"
					@change="handleChange">
				<span>{{ group.displayName }}</span>
			</label>
		</div>
		<div v-else class="no-groups">
			{{ t('dashlink', 'No groups available') }}
		</div>
	</div>
</template>

<script>
import { defineComponent, ref, watch } from 'vue'

export default defineComponent({
	name: 'GroupSelector',
	props: {
		modelValue: {
			type: Array,
			default: () => [],
		},
		groups: {
			type: Array,
			default: () => [],
		},
	},
	emits: ['update:modelValue'],
	setup(props, { emit }) {
		const selectedGroups = ref([...props.modelValue])

		watch(() => props.modelValue, (newValue) => {
			selectedGroups.value = [...newValue]
		})

		function handleChange() {
			emit('update:modelValue', selectedGroups.value)
		}

		return {
			selectedGroups,
			handleChange,
		}
	},
})
</script>

<style lang="scss" scoped>
.group-selector {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.hint {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	margin-bottom: 4px;
}

.groups-list {
	display: flex;
	flex-direction: column;
	gap: 8px;
	max-height: 200px;
	overflow-y: auto;
	padding: 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
}

.group-item {
	display: flex;
	align-items: center;
	gap: 8px;
	cursor: pointer;
	padding: 4px 8px;
	border-radius: var(--border-radius);
	transition: background 0.2s ease;

	&:hover {
		background: var(--color-background-hover);
	}

	input[type="checkbox"] {
		cursor: pointer;
	}

	span {
		font-size: 14px;
	}
}

.no-groups {
	padding: 12px;
	text-align: center;
	color: var(--color-text-maxcontrast);
	font-size: 14px;
}
</style>
