<template>
	<div class="effect-flip-container" :class="{ 'is-flipped': isHovered && link.description }">
		<!-- Front Side (default card content is handled by LinkCard) -->
		<div class="effect-flip-front">
			<slot name="front" />
		</div>

		<!-- Back Side -->
		<div class="effect-flip-back">
			<img
				v-if="link.iconUrl"
				:src="link.iconUrl"
				:alt="link.title"
				class="flip-back-icon">
			<div v-else class="flip-back-icon-placeholder">
				<LinkIcon :size="48" />
			</div>
			<div class="flip-back-content">
				<p v-if="link.description" class="effect-flip-description">
					{{ link.description }}
				</p>
			</div>
		</div>
	</div>
</template>

<script>
import { defineComponent } from 'vue'
import LinkIcon from 'vue-material-design-icons/Link.vue'

export default defineComponent({
	name: 'EffectFlip',
	components: { LinkIcon },
	props: {
		link: {
			type: Object,
			required: true,
		},
		isHovered: {
			type: Boolean,
			default: false,
		},
	},
	setup() {
		return {}
	},
})
</script>

<style lang="scss" scoped>
.effect-flip-container {
	position: absolute;
	inset: 0;
	width: 100%;
	height: 100%;
	transform-style: preserve-3d;
	transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s ease;
	z-index: 10;
	border-radius: inherit;

	&.is-flipped {
		transform: rotateY(180deg);
	}
}

.effect-flip-front,
.effect-flip-back {
	position: absolute;
	inset: 0;
	backface-visibility: hidden;
	-webkit-backface-visibility: hidden;
	-moz-backface-visibility: hidden;
	border-radius: inherit;
	overflow: hidden;
}

.effect-flip-front {
	z-index: 2;
	background: transparent;
	display: flex;
	align-items: stretch;
	transform: rotateY(0deg);
}

.effect-flip-back {
	transform: rotateY(180deg);
	z-index: 1;
	background: var(--color-background-hover);
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 8px;
	padding: 12px;
}

.flip-back-icon {
	max-width: 80%;
	max-height: 80%;
	object-fit: contain;
	position: absolute;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
	opacity: 0.15;
	border-radius: 8px;
}

.flip-back-icon-placeholder {
	position: absolute;
	inset: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	opacity: 0.15;
	color: var(--color-main-text);
}

// Firefox-specific fix: ensure back face is on top when flipped
.effect-flip-container.is-flipped .effect-flip-back {
	z-index: 3;
}

.effect-flip-container.is-flipped .effect-flip-front {
	z-index: 1;
}

.flip-back-content {
	position: relative;
	z-index: 1;
	padding: 12px 16px;
	text-align: center;
}

.effect-flip-description {
	color: var(--color-main-text);
	font-size: 13px;
	line-height: 1.4;
	margin: 0;
	display: -webkit-box;
	-webkit-line-clamp: 3;
	-webkit-box-orient: vertical;
	overflow: hidden;
}
</style>
