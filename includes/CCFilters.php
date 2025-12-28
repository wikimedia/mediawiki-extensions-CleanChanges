<?php

use MediaWiki\Extension\CLDR\LanguageNames;
use MediaWiki\Hook\FetchChangesListHook;
use MediaWiki\Hook\SpecialRecentChangesPanelHook;
use MediaWiki\Html\FormOptions;
use MediaWiki\Html\Html;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\MediaWikiServices;
use MediaWiki\RecentChanges\ChangesListFilterGroupContainer;
use MediaWiki\SpecialPage\Hook\ChangesListSpecialPageQueryHook;

class CCFilters implements
	FetchChangesListHook,
	ChangesListSpecialPageQueryHook,
	SpecialRecentChangesPanelHook
{

	/**
	 * @param string $name
	 * @param array &$tables
	 * @param array &$fields
	 * @param array &$conds
	 * @param array &$query_options
	 * @param array &$join_conds
	 * @param FormOptions $opts
	 */
	public function onChangesListSpecialPageQuery(
		$name, &$tables, &$fields, &$conds, &$query_options, &$join_conds, $opts
	) {
		self::user( $name, $tables, $fields, $conds, $query_options, $join_conds, $opts );
		self::trailer( $name, $tables, $fields, $conds, $query_options, $join_conds, $opts );
	}

	/**
	 * @param array &$extraOpts Array of added items, to which can be added
	 * @param FormOptions $opts FormOptions for this request
	 */
	public function onSpecialRecentChangesPanel( &$extraOpts, $opts ) {
		self::userForm( $extraOpts, $opts );
		self::trailerForm( $extraOpts, $opts );
	}

	/**
	 * Hook: ChangesListSpecialPageQuery
	 * @param string $name
	 * @param array &$tables
	 * @param array &$fields
	 * @param array &$conds
	 * @param array &$query_options
	 * @param array &$join_conds
	 * @param FormOptions $opts
	 */
	public static function user(
		$name,
		&$tables,
		&$fields,
		&$conds,
		&$query_options,
		&$join_conds,
		FormOptions $opts
	) {
		global $wgRequest, $wgCCUserFilter;

		if ( !$wgCCUserFilter ) {
			return;
		}

		$opts->add( 'users', '' );
		$users = $wgRequest->getVal( 'users' );
		if ( $users === null || $users === '' ) {
			return;
		}

		$rawUserArr = explode( '|', $users );
		$userArr = UserArray::newFromNames( $rawUserArr );
		if ( $userArr->count() ) {
			$conds['actor_name'] = iterator_to_array( $userArr );
		} else {
			// Unknown user, make the query return no result.
			$conds['actor_name'] = $rawUserArr;
		}

		$opts->setValue( 'users', $users );
	}

	/**
	 * Hook: SpecialRecentChangesPanel
	 * @param array &$items
	 * @param FormOptions $opts
	 */
	public static function userForm( &$items, FormOptions $opts ) {
		global $wgRequest, $wgCCUserFilter;

		if ( !$wgCCUserFilter ) {
			return;
		}

		$opts->consumeValue( 'users' );

		$default = $wgRequest->getVal( 'users', '' );
		$items['users'] = [
			Html::label(
				wfMessage( 'cleanchanges-users' )->text(),
				'mw-users',
			),
			Html::input(
				'users',
				$default,
				'text',
				[ 'size' => 40, 'id' => 'mw-users' ]
			)
		];
	}

	/**
	 * Hook: ChangesListSpecialPageQuery
	 * @param string $name
	 * @param array &$tables
	 * @param array &$fields
	 * @param array &$conds
	 * @param array &$query_options
	 * @param array &$join_conds
	 * @param FormOptions $opts
	 */
	public static function trailer(
		$name,
		&$tables,
		&$fields,
		&$conds,
		&$query_options,
		&$join_conds,
		FormOptions $opts
	) {
		global $wgRequest, $wgCCTrailerFilter;

		if ( !$wgCCTrailerFilter ) {
			return;
		}

		$opts->add( 'trailer', '' );
		$trailer = $wgRequest->getVal( 'trailer' );
		if ( $trailer === null ) {
			return;
		}

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$conds[] = 'rc_title ' . $dbr->buildLike( $dbr->anyString(), $trailer );
		$opts->setValue( 'trailer', $trailer );
	}

	/**
	 * Hook: SpecialRecentChangesPanel
	 * @param array &$items
	 * @param FormOptions $opts
	 */
	public static function trailerForm( &$items, FormOptions $opts ) {
		/**
		 * @var Language $wgLang
		 */
		global $wgLang, $wgRequest, $wgCCTrailerFilter;

		if ( !$wgCCTrailerFilter ) {
			return;
		}

		$opts->consumeValue( 'trailer' );

		$default = $wgRequest->getVal( 'trailer', '' );

		if ( is_callable( [ LanguageNames::class, 'getNames' ] ) ) {
			// cldr extension
			$languages = LanguageNames::getNames( $wgLang->getCode(),
				LanguageNames::FALLBACK_NORMAL,
				LanguageNames::LIST_MW
			);
		} else {
			$languages = MediaWikiServices::getInstance()->getLanguageNameUtils()
				->getLanguageNames( LanguageNameUtils::AUTONYMS, LanguageNameUtils::DEFINED );
		}
		ksort( $languages );
		$options = Html::element(
			'option',
			[ 'value' => '', 'selected' => $default === '' ],
			wfMessage( 'cleanchanges-language-na' )->text()
		);
		foreach ( $languages as $code => $name ) {
			$options .= Html::element(
				'option',
				[ 'value' => "/$code", 'selected' => $default === "/$code" ],
				"$code - $name"
			) . "\n";
		}
		$str =
		Html::openElement( 'select', [
			'name' => 'trailer',
			'class' => 'mw-language-selector',
			'id' => 'sp-rc-language',
		] ) .
		$options .
		Html::closeElement( 'select' );

		$items['tailer'] = [ wfMessage( 'cleanchanges-language' )->escaped(), $str ];
	}

	/**
	 * Hook: FetchChangesList
	 * @param User $user
	 * @param Skin $skin
	 * @param ChangesList|null &$list
	 * @param ChangesListFilterGroupContainer $groups
	 */
	public function onFetchChangesList( $user, $skin, &$list, $groups ): void {
		global $wgCCTrailerFilter;

		if ( $wgCCTrailerFilter && defined( 'ULS_VERSION' ) ) {
			$skin->getOutput()->addModules( 'ext.cleanchanges.uls' );
		}
	}
}
