<?php

/**
 * Generate a list of changes using an Enhanced system (use javascript).
 */
class NCL extends EnhancedChangesList {

	/**
	 * Determines which version of changes list to provide, or none.
	 */
	public static function hook( &$user, &$skin, &$list ) {
		$list = null;

		/* allow override */
		global $wgRequest;
		if ( $wgRequest->getBool('cleanrc') ) {
			$list = new NCL( $skin );
		}
		if ( $wgRequest->getBool('newrc') ) {
			$list = new EnhancedChangesList( $skin );
		}
		if ( $wgRequest->getBool('oldrc') ) {
			$list = new OldChangesList( $skin );
		}

		if ( !$list && $user->getOption( 'usenewrc' ) ) {
			$list = new NCL( $skin );
		}

		if ( $list instanceof NCL ) {
			global $wgOut, $wgScriptPath, $wgJsMimeType, $wgStyleVersion;
			$wgOut->addScript(
				Xml::openElement( 'script', array( 'type' => $wgJsMimeType, 'src' =>
				"$wgScriptPath/extensions/CleanChanges/cleanchanges.js?$wgStyleVersion" )
				) . '</script>'
			);
		}

		/* If some list was specified, stop processing */
		return $list === null;

	}


	/**
	 * String that comes between page details and the user details. By default
	 * only larger space.
	 */
	protected $userSeparator = "\xc2\xa0 \xc2\xa0";
	protected $userinfo = array();

	/**
	 * Text direction, true for ltr and false for rtl
	 */
	protected $direction = true;

	/**
	 * Style for flags.
	 */
	private $infoStyle = array( 'style' => 'letter-spacing: 0.5em;' );

	public function __construct( $skin ) {
		wfLoadExtensionMessages( 'CleanChanges' );
		global $wgLang;
		parent::__construct( $skin );
		$this->direction = !$wgLang->isRTL();
		$this->dir = $wgLang->getDirMark();
	}


	function beginRecentChangesList() {
		parent::beginRecentChangesList();
		$dir = $this->direction ? 'ltr' : 'rtl';
		return
			Xml::openElement(
				'div',
				array( 'style' => "direction: $dir" )
			);
	}

	/*
	 * Have to output the accumulated javascript stuff before any output is send.
	 */
	function endRecentChangesList() {
		global $wgOut;
		$wgOut->addScript( Skin::makeVariablesScript( $this->userinfo ) );
		return parent::endRecentChangesList() . '</div>';
	}

	/**
	 * Format a line for enhanced recentchange (aka with javascript and block of lines).
	 */
	function recentChangesLine( &$baseRC, $watched = false ) {
		global $wgLang, $wgContLang;

		# Create a specialised object
		$rc = RCCacheEntry::newFromParent( $baseRC );

		// Extract most used variables
		$timestamp = $rc->getAttribute( 'rc_timestamp' );
		$titleObj = $rc->getTitle();
		$rc_id = $rc->getAttribute( 'rc_id' );

		$date = $wgLang->date( $timestamp, /* adj */ true, /* format */ true );
		$time = $wgLang->time( $timestamp, /* adj */ true, /* format */ true );

		# Should patrol-related stuff be shown?
		$rc->unpatrolled =  $this->usePatrol() ? !$rc->getAttribute( 'rc_patrolled' ) : false;

		if( $rc->getAttribute( 'rc_namespace' ) == NS_SPECIAL ) {
			list( $specialName, $logtype ) = SpecialPage::resolveAliasWithSubpage(
				$rc->getAttribute( 'rc_title' )
			);
			if ( $specialName === 'Log' ) {
				# Log updates, etc
				$logname = LogPage::logName( $logtype );
				$clink = '(' . $this->skin->makeKnownLinkObj( $titleObj, $logname ) . ')';
			} else {
				wfDebug( "Unknown special page name $specialName, Log expected" );
				return '';
			}
		} elseif( $rc->unpatrolled && $rc->getAttribute( 'rc_type' ) == RC_NEW ) {
			# Unpatrolled new page, give rc_id in query
			$clink = $this->skin->makeKnownLinkObj( $titleObj, '', "rcid={$rc_id}" );
		} else {
			$clink = $this->skin->makeKnownLinkObj( $titleObj, '' );
		}

		$rc->watched   = $watched;
		$rc->link      = $clink;
		$rc->timestamp = $time;
		$rc->numberofWatchingusers = $baseRC->numberofWatchingusers;

		$rc->_reqCurId = array( 'curid' => $rc->getAttribute( 'rc_cur_id' ) );
		$rc->_reqOldId = array( 'oldid' => $rc->getAttribute( 'rc_this_oldid' ) );
		$this->makeLinks( $rc );

		$stuff = $this->userToolLinks( $rc->getAttribute( 'rc_user' ),
			$rc->getAttribute( 'rc_user_text' ) );
		$this->userinfo += $stuff[1];

		$rc->_user = $this->skin->userLink( $rc->getAttribute( 'rc_user' ),
			$rc->getAttribute( 'rc_user_text' ) );
		$rc->_userInfo = $stuff[0];

		$rc->_comment = $this->skin->commentBlock(
			$rc->getAttribute( 'rc_comment' ), $titleObj );

		$rc->_watching = $this->numberofWatchingusers( $baseRC->numberofWatchingusers );


		# If it's a new day, add the headline and flush the cache
		$ret = '';
		if ( $date !== $this->lastdate ) {
			# Process current cache
			$ret = $this->recentChangesBlock();
			$this->rc_cache = array();
			$ret .= Xml::element('h4', null, $date) . "\n";
			$this->lastdate = $date;
		}

		# Put accumulated information into the cache, for later display
		# Page moves go on their own line
		$secureName = $titleObj->getPrefixedDBkey();
		$this->rc_cache[$secureName][] = $rc;

		return $ret;
	}

	protected function makeLinks( $rc ) {
		/* These will be overriden with actual links below, if applicable */
		$rc->_curLink  = $this->message['cur'];
		$rc->_diffLink = $this->message['diff'];
		$rc->_lastLink = $this->message['last'];
		$rc->_histLink = $this->message['hist'];

		/* Logs link only to Special:Log/type */
		if( $rc->getAttribute( 'rc_type' ) != RC_LOG ) {
			# Make cur, diff and last links
			$querycur = wfArrayToCGI( array( 'diff' => 0 ) + $rc->_reqCurId + $rc->_reqOldId );
			$querydiff = wfArrayToCGI( array(
				'diff'  => $rc->getAttribute( 'rc_this_oldid' ),
				'oldid' => $rc->getAttribute( 'rc_last_oldid' ),
				'rcid'  => $rc->unpatrolled ? $rc->getAttribute( 'rc_id' ) : '',
			) + $rc->_reqCurId );

			$rc->_curLink = $this->skin->makeKnownLinkObj( $rc->getTitle(),
					$this->message['cur'], $querycur );

			if ( $rc->getAttribute( 'rc_type' ) != RC_NEW ) {
				$rc->_diffLink = $this->skin->makeKnownLinkObj( $rc->getTitle(),
					$this->message['diff'], $querydiff );
			}

			if ( $rc->getAttribute( 'rc_last_oldid' ) != 0 ) {
				// This is not the first revision
				$rc->_lastLink = $this->skin->makeKnownLinkObj( $rc->getTitle(),
					$this->message['last'], $querydiff );
			}

			$rc->_histLink = $this->skin->makeKnownLinkObj( $rc->getTitle(),
				$this->message['hist'],
				wfArrayToCGI( $rc->_reqCurId, array( 'action' => 'history' ) ) );

		}
	}

	/**
	 * Enhanced RC group
	 */
	function recentChangesBlockGroup( $block ) {
		global $wgLang, $wgRCShowChangedSize;

		# Collate list of users
		$isnew = false;
		$unpatrolled = false;
		$userlinks = array();
		foreach( $block as $rcObj ) {
			$oldid = $rcObj->mAttribs['rc_last_oldid'];
			if( $rcObj->mAttribs['rc_new'] ) {
				$isnew = true;
			}
			$u = $rcObj->_user;
			if( !isset( $userlinks[$u] ) ) {
				$userlinks[$u] = 0;
			}
			if( $rcObj->unpatrolled ) {
				$unpatrolled = true;
			}
			$bot = $rcObj->mAttribs['rc_bot'];
			$userlinks[$u]++;
		}

		# Main line, flags and timestamp

		$info = Xml::openElement( 'tt' ) .
			$this->getFlags( $block[0], $isnew, false, false, $unpatrolled ) .
			' ' . $block[0]->timestamp . '</tt>';
		$rci = 'RCI' . $this->rcCacheIndex;
		$rcl = 'RCL' . $this->rcCacheIndex;
		$rcm = 'RCM' . $this->rcCacheIndex;
		$toggleLink = "javascript:toggleVisibilityE('$rci', '$rcm', '$rcl', 'block')";
		$tl =
		Xml::tags( 'span', array( 'id' => $rcm ),
			Xml::tags('a', array( 'href' => $toggleLink ), $this->arrow($this->direction ? 'r' : 'l') ) ) .
		Xml::tags( 'span', array( 'id' => $rcl, 'style' => 'display: none;' ),
			Xml::tags('a', array( 'href' => $toggleLink ), $this->downArrow() ) );

		$items[] = $tl . $info;

		# Article link
		$items[] = $this->maybeWatchedLink( $block[0]->link, $block[0]->watched );

		$curIdEq = 'curid=' . $block[0]->mAttribs['rc_cur_id'];
		$currentRevision = $block[0]->mAttribs['rc_this_oldid'];
		if( $block[0]->mAttribs['rc_type'] != RC_LOG ) {
			# Changes
			$n = count($block);
			static $nchanges = array();
			if ( !isset( $nchanges[$n] ) ) {
				$nchanges[$n] = wfMsgExt( 'nchanges', array( 'parsemag', 'escape'),
					$wgLang->formatNum( $n ) );
			}

			if ( !$isnew ) {
				$changes = $this->skin->makeKnownLinkObj( $block[0]->getTitle(),
					$nchanges[$n],
					$curIdEq."&diff=$currentRevision&oldid=$oldid" );
			} else {
				$changes = $nchanges[$n];
			}

			# Character difference
			$size = $rcObj->getCharacterDifference( $block[ count( $block ) - 1 ]->mAttribs['rc_old_len'],
					$block[0]->mAttribs['rc_new_len'] );

			# History link
			$hist = $block[0]->_histLink;

			if ( $size ) {
				$items[] = "($changes; $hist $size)";
			} else {
				$items[] = "($changes; $hist)";
			}

		}

		$items[] = $this->userSeparator;

		# Sort the list and convert to text
		$items[] = $this->makeUserlinks( $userlinks );
		$items[] = $block[0]->_watching;

		$lines = '<div>' . implode( " {$this->dir}", $items ) . "</div>\n";

		# Sub-entries
		$lines .= Xml::tags( 'div',
			array( 'id' => $rci, 'style' => 'display: none;' ),
			$this->subEntries( $block )
		) . "\n";

		$this->rcCacheIndex++;
		return $lines;
	}

	function subEntries( $block ) {
		global $wgRCShowChangedSize;

		$lines = '';
		foreach( $block as $rcObj ) {
			$items = array();

			$time = $rcObj->timestamp;
			if( $rcObj->getAttribute( 'rc_type' ) != RC_LOG ) {
				$time = $this->skin->makeKnownLinkObj( $rcObj->getTitle(),
					$rcObj->timestamp, wfArrayToCGI( $rcObj->_reqOldId, $rcObj->_reqCurId ) );
			}

			$info = $this->getFlags( $rcObj ) . ' ' . $time;
			$items[] = $this->spacerArrow() . Xml::tags( 'tt', null, $info );

			if ( $rcObj->getAttribute( 'rc_type' ) != RC_LOG ) {
				$cur  = $rcObj->_curLink;
				$last = $rcObj->_lastLink;

				if ( $block[0] === $rcObj ) {
					// no point diffing first to first
					$cur = $this->message['cur'];
				}

				if ( $wgRCShowChangedSize && $rcObj->getCharacterDifference() != '' ) {
					$size = $rcObj->getCharacterDifference();
					$items[] = "($cur; $last $size)";
				} else {
					$items[] = "($cur; $last)";
				}
			}

			$items[] = $this->userSeparator;
			$items[] = $rcObj->_user;
			$items[] = $rcObj->_userInfo;
			$items[] = $rcObj->_comment;

			$lines .= '<div>' . implode( " {$this->dir}", $items ) . "</div>\n";
		}
		return $lines;
	}

	/**
	 * Enhanced RC ungrouped line.
	 * @return string a HTML formated line
	 */
	function recentChangesBlockLine( $rcObj ) {
		global $wgContLang, $wgRCShowChangedSize;

		# Flag and Timestamp
		$info = $this->getFlags( $rcObj ) . ' ' . $rcObj->timestamp;
		$items[] = $this->spacerArrow() . Xml::tags( 'tt', null, $info );

		# Article link
		$items[] = $this->maybeWatchedLink( $rcObj->link, $rcObj->watched );

		if ( $rcObj->getAttribute( 'rc_type' ) != RC_LOG) {
			$diff = $rcObj->_diffLink;
			$hist = $rcObj->_histLink;

			# Character diff
			if ( $wgRCShowChangedSize ) {
				$size = $rcObj->getCharacterDifference();
				$items[] = "($diff; $hist $size)";
			} else {
				$items[] = "($diff; $hist)";
			}
		}

		$items[] = $this->userSeparator;
		$items[] = $rcObj->_user;
		$items[] = $rcObj->_userInfo;
		$items[] = $rcObj->_comment;
		$items[] = $rcObj->_watching;

		return '<div>' . implode( " {$this->dir}", $items ) . "</div>\n";

	}

	/**
	 * Enhanced user tool links, with javascript functionality.
	 */
	public function userToolLinks( $userId, $userText ) {
		global $wgUser, $wgDisableAnonTalk, $wgSysopUserBans;
		$talkable = !( $wgDisableAnonTalk && 0 == $userId );
		$blockable = ( $wgSysopUserBans || 0 == $userId );

		/*
		 * Assign each different user a running id. This is used to show user tool
		 * links on demand with javascript, to reduce page size when one user has
		 * multiple changes.
		 *
		 * $linkindex is the running id, and $users contain username -> html snippet
		 * for javascript.
		 */

		static $linkindex = 0;
		$linkindex++;

		static $users = array();
		$userindex = array_search( $userText, $users, true );
		if ( $userindex === false ) {
			$users[] = $userText;
			$userindex = count( $users ) -1;
		}


		$rci = 'RCUI' . $userindex;
		$rcl = 'RCUL' . $linkindex;
		$rcm = 'RCUM' . $linkindex;
		$toggleLink = "javascript:showUserInfo('wgUserInfo$rci', '$rcl' )";
		$tl  = Xml::tags('span', array( 'id' => $rcm ),
			Xml::tags( 'a', array( 'href' => $toggleLink ), $this->arrow($this->direction ? 'r' : 'l') ) );
		$tl .= Xml::element('span', array( 'id' => $rcl ), ' ' );

		$items = array();
		if( $talkable ) {
			$items[] = $this->skin->userTalkLink( $userId, $userText );
		}
		if( $userId ) {
			$targetPage = SpecialPage::getTitleFor( 'Contributions', $userText );
			$items[] = $this->skin->makeKnownLinkObj( $targetPage,
				wfMsgHtml( 'contribslink' ) );
		}
		if( $blockable && $wgUser->isAllowed( 'block' ) ) {
			$items[] = $this->skin->blockLink( $userId, $userText );
		}
		if( $userId && $wgUser->isAllowed( 'userrights' ) ) {
			$targetPage = SpecialPage::getTitleFor( 'Userrights', $userText );
			$items[] = $this->skin->makeKnownLinkObj( $targetPage,
				wfMsgHtml( 'cleanchanges-changerightslink' ) );
		}

		if( $items ) {
			$data = array( "wgUserInfo$rci" => '(' . implode( ' | ', $items ) . ')' );

			return array($tl, $data);
		} else {
			return '';
		}
	}

	/**
	 * Makes aggregated list of contributors for a changes group.
	 * Example: [Usera; AnotherUser; ActiveUser ‎(2×); Userabc ‎(6×)]
	 */
	function makeUserlinks( $userlinks ) {
		global $wgLang;

		/*
		 * User with least changes first, and fallback to alphabetical sorting if
		 * multiple users have same number of changes.
		 */
		krsort( $userlinks );
		asort( $userlinks );

		$users = array();
		foreach( $userlinks as $userlink => $count) {
			$text = $userlink;
			if( $count > 1 ) {
				$count = $wgLang->formatNum( $count );
				$text .= " {$wgLang->getDirMark()}({$count}×)";
			}
			array_push( $users, $text );
		}
		$text = implode('; ', $users);
		$enclosure =
			Xml::openElement( 'span', array( 'class' => 'changedby' ) ) . "[$text]" .
			Xml::closeElement( 'span' );
		return $enclosure;
	}

	function getFlags( $object, $_new = null, $_minor = null, $_bot = null, $_unpatrolled = null ) {
		// TODO: we assume all characters are of equal width, which they may be not
		$nothing = "\xc2\xa0";

		$new = is_null( $_new ) ? $object->getAttribute( 'rc_new' ) : $_new;
		$minor = is_null( $_minor ) ? $object->getAttribute( 'rc_minor' ) : $_minor;
		$bot = $object->getAttribute( 'rc_bot' );
		$patrolled = !$object->getAttribute( 'rc_patrolled' ) && $this->usePatrol();

		$f = $new ? Xml::element( 'span', array( 'class' => 'newpage' ), $this->message['newpageletter'] )
				: $nothing;
		$f .= $minor ? Xml::element( 'span', array( 'class' => 'minor' ), $this->message['minoreditletter'] )
				: $nothing;
		$f .= $bot ? Xml::element( 'span', array( 'class' => 'bot' ), $this->message['boteditletter'] ) : $nothing;
		$f .= $patrolled ? Xml::element( 'span', array( 'class' => 'unpatrolled' ), '!' ) : $nothing;
		return $nothing.  Xml::tags( 'span', $this->infoStyle, $f );
	}
}
