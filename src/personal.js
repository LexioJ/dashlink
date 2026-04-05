import { translate, translatePlural } from '@nextcloud/l10n'
import { createApp } from 'vue'
import PersonalPanel from './components/PersonalPanel.vue'
import '../css/admin.scss'

const app = createApp(PersonalPanel)

// Add translation methods globally
app.mixin({
	methods: {
		t: translate,
		n: translatePlural,
	},
})

app.mount('#dashlink-personal-root')
