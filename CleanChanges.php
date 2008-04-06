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
