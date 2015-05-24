<?php
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'CleanChanges' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['CleanChanges'] = __DIR__ . '/i18n';
	/* wfWarn(
		'Deprecated PHP entry point used for CleanChanges extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
}
/**
 * Setup for pre-1.25 wikis. Make sure this is kept in sync with extension.json
 */

/**
 * An extension to show a nice compact changes list and few extra filters for
 * Special:RecentChanges.php
 *
 * @file
 * @ingroup Extensions
 *
 * @author Niklas Laxström
 * @copyright Copyright © 2008-2012, Niklas Laxström
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

/* Extension information */
$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'Clean Changes',
	'version' => '2014-12-29',
	'author' => 'Niklas Laxström',
	'descriptionmsg' => 'cleanchanges-desc',
	'url' => 'https://www.mediawiki.org/wiki/Extension:CleanChanges',
	'license-name' => 'GPL-2.0+',
);

/* Set up messages and includes */
$wgMessagesDirs['CleanChanges'] = __DIR__ . '/i18n';
$wgAutoloadClasses['NCL'] =  __DIR__ . "/CleanChanges_body.php";
$wgAutoloadClasses['CCFilters'] = __DIR__ . "/Filters.php";

require_once __DIR__ . '/Resources.php';

/* Hook into code */
$wgHooks['FetchChangesList'][] = 'NCL::hook';
$wgHooks['MakeGlobalVariablesScript'][] = 'NCL::addScriptVariables';
$wgHooks['SpecialRecentChangesQuery'][] = 'CCFilters::user';
$wgHooks['SpecialRecentChangesPanel'][] = 'CCFilters::userForm';
$wgHooks['SpecialRecentChangesQuery'][] = 'CCFilters::trailer';
$wgHooks['SpecialRecentChangesPanel'][] = 'CCFilters::trailerForm';

$wgCCUserFilter = true;
$wgCCTrailerFilter = false;
