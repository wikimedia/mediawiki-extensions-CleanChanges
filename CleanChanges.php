<?php

/* Set up messages and includes */
$dir = dirname(__FILE__) . '/';
$wgExtensionMessagesFiles['CleanChanges'] = $dir . 'CleanChanges.i18n.php';
$wgAutoloadClasses['NCL'] =  $dir . 'CleanChanges_body.php';

/* Hook into code */
$wgHooks['FetchChangesList'][] = 'NCL::hook' ;

/* Extension information */
$wgExtensionCredits['other'][] = array(
	'name' => 'Clean Changes',
	'version' => '2008-04-06',
	'author' => 'Niklas Laxström',
	'descriptionmsg' => 'cleanchanges-desc',
	'url' => 'http://www.mediawiki.org/wiki/Extension:CleanChanges',
);

$wgCCUserFilter = filter;
$wgCCTrailerFilter = filter;

$wgExtensionFunctions[] = 'ccSetupFilters';
$wgAutoloadClasses['CCFilters'] = $dir . 'Filters.php';

function ccSetupFilters() {
	global $wgCCUserFilter, $wgCCTrailerFilter, $wgHooks;

	if ( $wgCCUserFilter ) {
		$wgHooks['SpecialRecentChangesQuery'][] = 'CCFilters::user';
		$wgHooks['SpecialRecentChangesPanel'][] = 'CCFilters::userForm';
	}
	if ( $wgCCTrailerFilter ) {
		$wgHooks['SpecialRecentChangesQuery'][] = 'CCFilters::trailer';
		$wgHooks['SpecialRecentChangesPanel'][] = 'CCFilters::trailerForm';
	}
}
