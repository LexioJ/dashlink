<template>
	<div class="effect-selector">
		<label class="selector-label">
			Hover Effect
			<span class="hint">
				Animation style when hovering over links (for entries with a description)
			</span>
		</label>

		<NcSelect
			v-model="selectedEffect"
			:options="effects"
			:placeholder="'Select effect...'"
			:clearable="false"
			label="name"
			@update:modelValue="onSelectionChange" />
	</div>
</template>

<script>
import { defineComponent, ref, watch, onMounted } from 'vue'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { getAvailableEffects } from '../effects'

export default defineComponent({
	name: 'EffectSelector',
	components: {
		NcSelect,
	},
	props: {
		modelValue: {
			type: String,
			default: 'blur',
		},
	},
	emits: ['update:modelValue'],
	setup(props, { emit }) {
		const effects = ref(getAvailableEffects())
		const selectedEffect = ref(null)

		// Initialize selection
		onMounted(() => {
			const effectsList = effects.value
			selectedEffect.value = effectsList.find(e => e.id === props.modelValue) || effectsList[0]
		})

		watch(() => props.modelValue, (newValue) => {
			const effectsList = effects.value
			selectedEffect.value = effectsList.find(e => e.id === newValue) || effectsList[0]
		})

		function onSelectionChange() {
			if (selectedEffect.value) {
				emit('update:modelValue', selectedEffect.value.id)
			}
		}

		return {
			effects,
			selectedEffect,
			onSelectionChange,
		}
	},
})
</script>

<style lang="scss" scoped>
.effect-selector {
	margin-bottom: 24px;
}

.selector-label {
	display: block;
	margin-bottom: 8px;
	font-weight: 500;
	cursor: default;

	.hint {
		display: block;
		font-weight: normal;
		font-size: 12px;
		color: var(--color-text-maxcontrast);
	}
}
</style>
