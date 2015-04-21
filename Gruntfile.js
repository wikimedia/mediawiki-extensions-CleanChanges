/*!
 * Grunt file
 *
 * @package CleanChanges
 */

/*jshint node:true */
module.exports = function ( grunt ) {
	'use strict';
	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.initConfig( {
		banana: {
			all: ['i18n/']
		}
	} );

	grunt.registerTask( 'test', [ 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
