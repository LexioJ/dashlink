<template>
	<Transition name="effect-slide-bottom">
		<div
			v-if="isHovered && link.description"
			class="effect-slide-bottom-panel">
			<div class="effect-slide-bottom-content">
				<p class="effect-slide-bottom-description">{{ link.description }}</p>
			</div>
		</div>
	</Transition>
</template>

<script>
import { defineComponent } from 'vue'

export default defineComponent({
	name: 'EffectSlideBottom',
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
.effect-slide-bottom-panel {
	position: absolute;
	left: 0;
	right: 0;
	bottom: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 0 0 var(--border-radius-large, 12px) var(--border-radius-large, 12px);
	z-index: 10;
	background: rgba(0, 0, 0, 0.75);
	padding: 4px 8px;
}

.effect-slide-bottom-content {
	position: relative;
	z-index: 1;
	padding: 2px 4px;
	text-align: center;
	max-width: 100%;
	overflow: hidden;
}

.effect-slide-bottom-description {
	color: #fff;
	font-size: 11px;
	line-height: 1.3;
	margin: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	max-width: 100%;
}

// Animation
.effect-slide-bottom-enter-active,
.effect-slide-bottom-leave-active {
	transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.effect-slide-bottom-enter-from,
.effect-slide-bottom-leave-to {
	opacity: 0;
	transform: translateY(100%);
}

.effect-slide-bottom-enter-to,
.effect-slide-bottom-leave-from {
	opacity: 1;
	transform: translateY(0);
}
</style>
