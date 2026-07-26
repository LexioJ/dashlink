<template>
	<Transition name="effect-tooltip">
		<div
			v-if="isHovered && link.description"
			class="effect-tooltip-bubble">
			<p class="effect-tooltip-description">{{ link.description }}</p>
			<div class="effect-tooltip-arrow" />
		</div>
	</Transition>
</template>

<script>
import { defineComponent } from 'vue'

export default defineComponent({
	name: 'EffectTooltip',
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
})
</script>

<style lang="scss" scoped>
.effect-tooltip-bubble {
	position: absolute;
	bottom: 100%;
	left: 50%;
	transform: translateX(-50%);
	margin-bottom: 8px;
	background: rgba(0, 0, 0, 0.85);
	border-radius: 8px;
	padding: 6px 12px;
	z-index: 100;
	white-space: normal;
	max-width: 250px;
	pointer-events: none;
}

.effect-tooltip-description {
	color: #fff;
	font-size: 12px;
	line-height: 1.3;
	margin: 0;
	display: -webkit-box;
	-webkit-line-clamp: 3;
	-webkit-box-orient: vertical;
	overflow: hidden;
}

.effect-tooltip-arrow {
	position: absolute;
	top: 100%;
	left: 50%;
	transform: translateX(-50%);
	width: 0;
	height: 0;
	border-left: 6px solid transparent;
	border-right: 6px solid transparent;
	border-top: 6px solid rgba(0, 0, 0, 0.85);
}

// Animation
.effect-tooltip-enter-active,
.effect-tooltip-leave-active {
	transition: all 0.2s ease;
}

.effect-tooltip-enter-from,
.effect-tooltip-leave-to {
	opacity: 0;
	transform: translateX(-50%) translateY(4px);
}

.effect-tooltip-enter-to,
.effect-tooltip-leave-from {
	opacity: 1;
	transform: translateX(-50%) translateY(0);
}
</style>
