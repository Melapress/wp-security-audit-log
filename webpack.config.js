/**
 * WSAL Webpack Configuration
 *
 * @since 3.2.3
 */
const path = require( 'path' );
const autoprefixer = require( 'autoprefixer' );
const ExtractTextPlugin = require( 'extract-text-webpack-plugin' );

// Extract style.css for both editor and frontend styles.
const wizardCSSPlugin = new ExtractTextPlugin({
	filename: '../../css/dist/wsal-wizard.build.css'
});

// Configuration for the ExtractTextPlugin — DRY rule.
const extractConfig = {
	use: [

		// "postcss" loader applies autoprefixer to our CSS.
		{ loader: 'raw-loader' },
		{
			loader: 'postcss-loader',
			options: {
				ident: 'postcss',
				plugins: [
					autoprefixer({
						browsers: [
							'>1%',
							'last 4 versions',
							'Firefox ESR',
							'not ie < 9' // React doesn't support IE8 anyway
						],
						flexbox: 'no-2009'
					})
				]
			}
		},

		// "sass" loader converst SCSS to CSS.
		{
			loader: 'sass-loader',
			options: {

				// Add common CSS file for variables and mixins.
				data: '@import "./css/src/common.scss";\n',
				outputStyle: 'nested'
			}
		}
	]
};

/**
 * Webpack configuration object.
 */
let config = {

	/**
	 * Entry points
	 *
	 * These are multiple entry points for Webpack.
	 * If you want to add more files then simple add
	 * it as an index to the following array.
	 */
	entry: {
		'wsal-wizard': './js/src/wsal-wizard.js'
	},

	// Output object.
	output: {
		path: path.resolve( __dirname, 'js/dist' ),
		filename: '[name].js'
	},

	// Modules to run with Webpack.
	module: {
		rules: [
			{
				test: /\.js$/,
				exclude: /node_modules/,
				use: {
					loader: 'babel-loader',
					options: {

						/**
						 * Compiles ES 2015, ES 2016 and ES 2017.
						 *
						 * @see https://babeljs.io/docs/plugins/preset-env/
						 */
						presets: [ 'env' ]
					}
				}
			},

			// Wizard CSS test rule.
			{
				test: /wsal-wizard\.s?css$/,
				exclude: /(node_modules|bower_components)/,
				use: wizardCSSPlugin.extract( extractConfig )
			}
		]
	},

	// Add plugins.
	plugins: [ wizardCSSPlugin ]
};

// Export config.
module.exports = ( env, argv ) => {

	// Development mode.
	if ( 'development' === argv.mode ) {
		config.watch = true;
		config.devtool = 'source-map';
	}

	// Production mode.
	if ( 'production' === argv.mode ) {
		config.watch = false;
		config.output = {
			path: path.resolve( __dirname, 'js/dist' ),
			filename: '[name].min.js'
		};
	}

	// Return config to Webpack.
	return config;
};
