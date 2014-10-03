<?php

global $wgResourceModules;

$resourcePaths = array(
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'CleanChanges'
);

$wgResourceModules['ext.cleanchanges'] = array(
	'scripts' => 'resources/cleanchanges.js',
) + $resourcePaths;

$wgResourceModules['ext.cleanchanges.uls'] = array(
	'scripts' => 'resources/cleanchanges.uls.js',
	'styles' => 'resources/cleanchanges.uls.css',
) + $resourcePaths;
