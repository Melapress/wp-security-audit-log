var gulp = require( 'gulp' );
var copy = require( 'gulp-copy' );

gulp.task(
	'default',
	function() {
		return gulp
			.src( 'node_modules/select2/dist/**/*' )
			.pipe( copy( 'assets', {
				prefix: 3
			} ) );
	}
);
