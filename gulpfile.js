/**
 * Gulpfile
 *
 * @since 1.0.0
 */


/**
 * Load Plugins.
 *
 * Load gulp plugins and assing them semantic names.
 */
var gulp 	= require('gulp');
var zip 	= require('gulp-zip');
var notify 	= require('gulp-notify');
var wpPot   = require('gulp-wp-pot'); // For generating the .pot file.
var sort    = require('gulp-sort'); // Recommended to prevent unnecessary changes in pot-file.

var projectPHPWatchFiles    = './**/*.php'; // Path to all PHP files.
var translatePath           = './languages/' // Where to save the translation files.
var text_domain             = 'wsal-premium'; // Your textdomain here.
var destFile                = 'wsal-premium.pot'; // Name of the transalation file.
var packageName             = 'wsal-premium'; // Package name.
var bugReport               = 'http://www.wpwhitesecurity.com'; // Where can users report bugs.
var lastTranslator          = 'WP White Security <info@wpwhitesecurity.com>'; // Last translator Email ID.
var team                    = 'WP White Security <info@wpwhitesecurity.com>'; // Team's Email ID.

/**
 * Build Plugin Zip
 */
gulp.task( 'zip', function () {
    return gulp.src( [
        // Include
        './**/*',

        // Exclude
        '!./prepros.cfg',
        '!./**/.DS_Store',
        '!./sass/**/*.scss',
        '!./sass',
        '!./node_modules/**',
        '!./node_modules',
        '!./package.json',
        '!./gulpfile.js',
        '!./*.sublime-project',
        '!./*.sublime-workspace'
    ])
    .pipe ( zip ( 'wp-security-audit-log.zip' ) )
    .pipe ( gulp.dest ( '../' ) )
    .pipe ( notify ( {
        message : 'WSAL Premium plugin zip is ready.',
        onLast : true
    } ) );
} );

/**
 * WP POT Translation File Generator.
 *
 * * This task does the following:
 *     1. Gets the source of all the PHP files
 *     2. Sort files in stream by path or any custom sort comparator
 *     3. Applies wpPot with the variable set at the top of this file
 *     4. Generate a .pot file of i18n that can be used for l10n to build .mo file
 */
gulp.task( 'translate', function () {
    return gulp.src( projectPHPWatchFiles )
        .pipe( sort() )
        .pipe( wpPot( {
            domain        : text_domain,
            destFile      : destFile,
            package       : packageName,
            bugReport     : bugReport,
            lastTranslator: lastTranslator,
            team          : team
        } ) )
        .pipe( gulp.dest( translatePath + destFile ) )
        .pipe( notify( { message: 'TASK: "translate" Completed! 💯', onLast: true } ) )

} );
