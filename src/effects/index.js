/**
 * DashLink Effect Registry
 *
 * Central registry for all hover effects.
 * To add a new effect:
 * 1. Create a new folder: effect_yourname/
 * 2. Create index.js with metadata and EffectYourname.vue
 * 3. Import and register here
 */

import EffectBlur from './effect_blur'
import EffectFlip from './effect_flip'
import EffectSlide from './effect_slide'
import EffectSlideBottom from './effect_slide_bottom'
import EffectTooltip from './effect_tooltip'

// Effect registry - add new effects here
const effects = {
	blur: EffectBlur,
	flip: EffectFlip,
	slide: EffectSlide,
	slide_bottom: EffectSlideBottom,
	tooltip: EffectTooltip,
}

/**
 * Get all available effects for dropdown
 * @returns {Array<{id: string, name: string, description: string}>}
 */
export function getAvailableEffects() {
	return Object.entries(effects).map(([id, effect]) => ({
		id,
		name: effect.name,
		description: effect.description,
	}))
}

/**
 * Get effect component by ID
 * @param {string} effectId - Effect identifier
 * @returns {Component} Vue component
 */
export function getEffectComponent(effectId) {
	const effect = effects[effectId]
	if (!effect) {
		console.warn(`Unknown effect: ${effectId}, falling back to blur`)
		return effects.blur.component
	}
	return effect.component
}

/**
 * Get effect metadata by ID
 * @param {string} effectId
 * @returns {Object} Effect metadata
 */
export function getEffect(effectId) {
	return effects[effectId] || effects.blur
}

export default effects
