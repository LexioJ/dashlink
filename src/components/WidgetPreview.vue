<template>
	<div class="widget-preview">
		<div class="preview-container">
			<div class="preview-header">
				<LinkIcon :size="16" />
				<span>{{ title }}</span>
			</div>
			<div class="preview-body">
				<div v-if="displayLinks.length === 0" class="empty-state">
					{{ t('dashlink', 'No links to preview') }}
				</div>
				<div
					v-else
					class="preview-grid"
					:class="{ 'two-column': useTwoColumns }"
					:style="gridStyle">
					<LinkCard
						v-for="(link, index) in displayLinks"
						:key="link.id"
						:link="link"
						:effect="effect"
						:class="{ 'full-width': isFullWidth(index) }"
						:style="{ height: rowHeight + 'px' }" />
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { defineComponent, computed } from 'vue'
import LinkIcon from 'vue-material-design-icons/Link.vue'
import LinkCard from './LinkCard.vue'

export default defineComponent({
	name: 'WidgetPreview',
	components: {
		LinkIcon,
		LinkCard,
	},
	props: {
		links: {
			type: Array,
			default: () => [],
		},
		effect: {
			type: String,
			default: 'blur',
		},
		title: {
			type: String,
			default: 'DashLink',
		},
	},
	setup(props) {
		const EFFECTIVE_HEIGHT = 400 // 504px - padding
		const MIN_ROW_HEIGHT = 70
		const MAX_LINKS = 10
		const GAP = 4 // Gap between grid items in pixels

		// Limit to 10 links
		const displayLinks = computed(() => {
			return props.links.slice(0, MAX_LINKS)
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
			useTwoColumns,
			rowHeight,
			gridStyle,
			isFullWidth,
		}
	},
})
</script>

<style lang="scss" scoped>
.widget-preview {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	overflow: hidden;
	width: 320px;
	height: 504px;
}

.preview-container {
	width: 100%;
	height: 100%;
	display: flex;
	flex-direction: column;
}

.preview-header {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 12px 16px;
	border-bottom: 1px solid var(--color-border);
	background: var(--color-background-dark);
	font-weight: 600;
	font-size: 14px;
	height: 48px;
	flex-shrink: 0;
}

.preview-body {
	padding: 16px;
	flex: 1;
	overflow: hidden;
}

.preview-grid {
	display: grid;
	width: 100%;
	height: 100%;

	&.two-column {
		.full-width {
			grid-column: span 2;
		}
	}
}

.empty-state {
	text-align: center;
	padding: 40px 20px;
	color: var(--color-text-maxcontrast);
	font-size: 14px;
}
</style>
