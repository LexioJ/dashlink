<template>
	<a
		:href="link.url"
		:target="link.target"
		class="link-card"
		:class="[`effect-${effect}`]"
		@mouseenter="isHovered = true"
		@mouseleave="isHovered = false">
		<!-- Card Content (hidden for flip effect as it goes in the slot) -->
		<div v-if="effect !== 'flip'" class="card-content">
			<div class="icon-wrapper">
				<img
					v-if="link.iconUrl"
					:src="link.iconUrl"
					:alt="link.title"
					class="link-icon">
				<div v-else class="icon-placeholder">
					<LinkIcon :size="24" />
				</div>
			</div>
			<span class="link-title">{{ link.title }}</span>
			<OpenInNew
				v-if="link.target === '_blank'"
				:size="14"
				class="external-indicator" />
		</div>

		<!-- Dynamic Effect Component -->
		<component
			:is="effectComponent"
			:link="link"
			:is-hovered="isHovered">
			<!-- Content for flip effect goes in the front slot -->
			<template v-if="effect === 'flip'" #front>
				<div class="card-content">
					<div class="icon-wrapper">
						<img
							v-if="link.iconUrl"
							:src="link.iconUrl"
							:alt="link.title"
							class="link-icon">
						<div v-else class="icon-placeholder">
							<LinkIcon :size="24" />
						</div>
					</div>
					<span class="link-title">{{ link.title }}</span>
					<OpenInNew
						v-if="link.target === '_blank'"
						:size="14"
						class="external-indicator" />
				</div>
			</template>
		</component>
	</a>
</template>

<script>
import { defineComponent, ref, computed } from 'vue'
import LinkIcon from 'vue-material-design-icons/Link.vue'
import OpenInNew from 'vue-material-design-icons/OpenInNew.vue'
import { getEffectComponent } from '../effects'

export default defineComponent({
	name: 'LinkCard',
	components: {
		LinkIcon,
		OpenInNew,
	},
	props: {
		link: {
			type: Object,
			required: true,
		},
		effect: {
			type: String,
			default: 'blur',
		},
	},
	setup(props) {
		const isHovered = ref(false)

		const effectComponent = computed(() => {
			return getEffectComponent(props.effect)
		})

		return {
			isHovered,
			effectComponent,
		}
	},
})
</script>

<style lang="scss" scoped>
.link-card {
	position: relative;
	display: flex;
	align-items: center;
	padding: 12px 8px;
	border-radius: var(--border-radius-large, 12px);
	background: var(--color-background-hover);
	text-decoration: none;
	color: var(--color-main-text);
	transition: transform 0.2s ease, box-shadow 0.2s ease;
	overflow: hidden;
	min-height: 56px;

	// Flip effect needs perspective on parent
	&.effect-flip {
		perspective: 1000px;
		padding: 0; // Content padding is inside flip-front
		background: transparent; // Allow widget background to show through

		// Disable default hover effects for flip - they're handled by the flip container
		&:hover {
			transform: none;
			box-shadow: none;
		}
	}

	&:hover {
		transform: translateY(-2px);
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
	}

	&:focus-visible {
		outline: 2px solid var(--color-primary);
		outline-offset: 2px;
	}
}

.card-content {
	display: flex;
	align-items: center;
	gap: 8px;
	width: 100%;
	position: relative;
	transition: opacity 0.3s ease;
}

// Add background and padding to card-content when inside flip effect
.effect-flip .card-content {
	background: var(--color-background-hover);
	border-radius: inherit;
	height: 100%;
	box-sizing: border-box;
	padding: 12px 8px;
}


.icon-wrapper {
	flex-shrink: 0;
	width: 32px;
	height: 32px;
	display: flex;
	align-items: center;
	justify-content: center;
}

.link-icon {
	width: 32px;
	height: 32px;
	object-fit: contain;
	border-radius: var(--border-radius);
}

.icon-placeholder {
	width: 32px;
	height: 32px;
	display: flex;
	align-items: center;
	justify-content: center;
	background: var(--color-primary-element-light);
	border-radius: var(--border-radius);
	color: var(--color-primary-element);
}

.link-title {
	flex: 1;
	font-weight: 500;
	font-size: 14px;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.external-indicator {
	flex-shrink: 0;
	color: var(--color-text-maxcontrast);
}
</style>
