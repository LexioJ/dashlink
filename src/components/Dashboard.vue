<template>
	<div class="dashlink-dashboard">
		<div v-if="showGear" class="gear-menu-wrapper">
			<button class="gear-button" @click="toggleGearMenu">
				<Cog :size="16" />
			</button>
			<div v-if="gearMenuOpen" class="gear-dropdown">
				<a v-if="userLinksEnabled" :href="userSettingsUrl" class="gear-item">
					{{ t('dashlink', 'Edit my links') }}
				</a>
				<a v-if="isAdmin" :href="adminSettingsUrl" class="gear-item">
					{{ t('dashlink', 'Edit (all users)') }}
				</a>
			</div>
		</div>
		<div v-if="displayLinks.length === 0" class="empty-state">
			<p>{{ t('dashlink', 'No links available') }}</p>
		</div>
		<div
			v-else
			class="links-grid"
			:class="{ 'two-column': useTwoColumns }"
			:style="gridStyle">
			<LinkCard
				v-for="(link, index) in displayLinks"
				:key="link.id"
				:link="link"
				:effect="hoverEffect"
				:class="{ 'full-width': isFullWidth(index) }"
				:style="{ height: rowHeight + 'px' }" />
		</div>
	</div>
</template>

<script>
import { defineComponent, computed, ref, onMounted, onUnmounted } from 'vue'
import { generateUrl } from '@nextcloud/router'
import Cog from 'vue-material-design-icons/Cog.vue'
import LinkCard from './LinkCard.vue'

export default defineComponent({
	name: 'Dashboard',
	components: {
		LinkCard,
		Cog,
	},
	props: {
		initialLinks: {
			type: Array,
			default: () => [],
		},
		hoverEffect: {
			type: String,
			default: 'blur',
		},
		isAdmin: {
			type: Boolean,
			default: false,
		},
		userLinksEnabled: {
			type: Boolean,
			default: false,
		},
	},
	setup(props) {
		const gearMenuOpen = ref(false)

		const showGear = computed(() => {
			return props.isAdmin || props.userLinksEnabled
		})

		const userSettingsUrl = generateUrl('/settings/user/dashlink')
		const adminSettingsUrl = generateUrl('/settings/admin/dashlink')

		function toggleGearMenu() {
			gearMenuOpen.value = !gearMenuOpen.value
		}

		function closeGearMenu(event) {
			if (!event.target.closest('.gear-menu-wrapper')) {
				gearMenuOpen.value = false
			}
		}

		onMounted(() => {
			document.addEventListener('click', closeGearMenu)
		})

		onUnmounted(() => {
			document.removeEventListener('click', closeGearMenu)
		})

		const EFFECTIVE_HEIGHT = 400 // 504px - padding
		const MIN_ROW_HEIGHT = 70
		const MAX_LINKS = 10
		const GAP = 4 // Gap between grid items in pixels

		// Limit to 10 links
		const displayLinks = computed(() => {
			return props.initialLinks.slice(0, MAX_LINKS)
		})

		const linkCount = computed(() => displayLinks.value.length)

		// Determine if we should use 2-column layout
		const useTwoColumns = computed(() => {
			if (linkCount.value <= 5) return false
			const singleColumnHeight = EFFECTIVE_HEIGHT / linkCount.value
			return singleColumnHeight <= MIN_ROW_HEIGHT
		})

		// Calculate how many links should be full-width (span 2 columns)
		const fullWidthCount = computed(() => {
			if (!useTwoColumns.value) return 0

			const count = linkCount.value
			// For odd numbers: 1 full-width
			if (count % 2 === 1) return 1
			// For even numbers: (10 - count) full-width
			return Math.max(0, 10 - count)
		})

		// Calculate total number of rows
		const totalRows = computed(() => {
			if (!useTwoColumns.value) return linkCount.value

			const remaining = linkCount.value - fullWidthCount.value
			const twoColumnRows = Math.ceil(remaining / 2)
			return fullWidthCount.value + twoColumnRows
		})

		// Calculate height per row (accounting for gaps)
		const rowHeight = computed(() => {
			const totalGapHeight = (totalRows.value - 1) * GAP
			const availableHeight = EFFECTIVE_HEIGHT - totalGapHeight
			return Math.floor(availableHeight / totalRows.value)
		})

		// Grid style
		const gridStyle = computed(() => {
			if (!useTwoColumns.value) {
				return {
					gridTemplateColumns: '1fr',
					gap: GAP + 'px'
				}
			}
			return {
				gridTemplateColumns: 'repeat(2, 1fr)',
				gap: GAP + 'px'
			}
		})

		// Check if a link at given index should be full-width
		function isFullWidth(index) {
			return useTwoColumns.value && index < fullWidthCount.value
		}

		return {
			displayLinks,
			links: displayLinks,
			useTwoColumns,
			rowHeight,
			gridStyle,
			isFullWidth,
			showGear,
			gearMenuOpen,
			toggleGearMenu,
			userSettingsUrl,
			adminSettingsUrl,
		}
	},
})
</script>

<style lang="scss" scoped>
.dashlink-dashboard {
	position: relative;
	padding: 0;
	width: 288px;
	height: 400px;
}

.links-grid {
	display: grid;
	width: 100%;
	height: 100%;

	&.two-column {
		.full-width {
			grid-column: span 2;
		}
	}
}

.gear-menu-wrapper {
	position: absolute;
	top: -84px;
	right: -8px;
	z-index: 10;
}

.gear-button {
	background: none;
	border: none;
	cursor: pointer;
	padding: 4px;
	border-radius: var(--border-radius);
	color: var(--color-text-maxcontrast);
	opacity: 0.5;
	transition: opacity 0.2s;
	display: flex;
	align-items: center;

	&:hover {
		opacity: 1;
		background: none;
	}
}

.gear-dropdown {
	position: absolute;
	top: 100%;
	right: 0;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
	min-width: 160px;
	overflow: hidden;
}

.gear-item {
	display: block;
	padding: 8px 12px;
	color: var(--color-main-text);
	text-decoration: none;
	font-size: 13px;
	white-space: nowrap;

	&:hover {
		background: var(--color-background-hover);
	}
}

.empty-state {
	text-align: center;
	padding: 40px 20px;
	color: var(--color-text-maxcontrast);
}
</style>
