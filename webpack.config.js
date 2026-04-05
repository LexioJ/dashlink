const path = require('path')
const webpack = require('webpack')
const webpackConfig = require('@nextcloud/webpack-vue-config')

const buildMode = process.env.NODE_ENV
const isDev = buildMode === 'development'

webpackConfig.devtool = isDev ? 'cheap-source-map' : 'source-map'

webpackConfig.stats = {
	colors: true,
	modules: false,
}

webpackConfig.entry = {
	dashboard: path.join(__dirname, 'src', 'dashboard.js'),
	admin: path.join(__dirname, 'src', 'admin.js'),
	personal: path.join(__dirname, 'src', 'personal.js'),
}

webpackConfig.output.path = path.resolve(__dirname, './js')
webpackConfig.output.publicPath = '/js/'

// Configure resolve
webpackConfig.resolve = webpackConfig.resolve || {}
webpackConfig.resolve.extensions = ['.js', '.ts', '.vue', '.json']
webpackConfig.resolve.fullySpecified = false

// Add fallback for Node.js modules
webpackConfig.resolve.fallback = {
	...(webpackConfig.resolve.fallback || {}),
	'process/browser': require.resolve('process/browser.js'),
	process: require.resolve('process/browser.js'),
	buffer: require.resolve('buffer/'),
}

// Provide global process and Buffer
webpackConfig.plugins = webpackConfig.plugins || []
webpackConfig.plugins.push(
	new webpack.ProvidePlugin({
		process: 'process/browser.js',
		Buffer: ['buffer', 'Buffer'],
	})
)

module.exports = webpackConfig
