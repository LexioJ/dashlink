<template>
	<Transition name="effect-blur">
		<div
			v-if="isHovered && link.description"
			class="effect-blur-overlay">
			<img
				v-if="link.iconUrl"
				:src="link.iconUrl"
				:alt="link.title"
				class="effect-bg-icon">
			<div v-else class="effect-bg-icon-placeholder">
				<LinkIcon :size="48" />
			</div>
			<div class="effect-blur-content">
				<p class="effect-blur-description">{{ link.description }}</p>
			</div>
		</div>
	</Transition>
</template>

<script>
import { defineComponent } from 'vue'
import LinkIcon from 'vue-material-design-icons/Link.vue'

export default defineComponent({
	name: 'EffectBlur',
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
})
</script>

<style lang="scss" scoped>
.effect-blur-overlay {
	position: absolute;
	inset: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: inherit;
	z-index: 10;
	background: var(--color-background-hover);
	overflow: hidden;
}

.effect-bg-icon {
	position: absolute;
	inset: 0;
	width: 100%;
	height: 100%;
	object-fit: contain;
	padding: 8px;
	opacity: 0.15;
	border-radius: 16px;
}

.effect-bg-icon-placeholder {
	position: absolute;
	inset: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	opacity: 0.15;
	color: var(--color-main-text);
}

.effect-blur-content {
	position: relative;
	z-index: 1;
	padding: 12px 16px;
	text-align: center;
}

.effect-blur-description {
	color: var(--color-main-text);
	font-size: 13px;
	line-height: 1.4;
	margin: 0;
	display: -webkit-box;
	-webkit-line-clamp: 3;
	-webkit-box-orient: vertical;
	overflow: hidden;
}

// Animation
.effect-blur-enter-active,
.effect-blur-leave-active {
	transition: opacity 0.5s ease-in-out;
}

.effect-blur-enter-from,
.effect-blur-leave-to {
	opacity: 0;
}
</style>
