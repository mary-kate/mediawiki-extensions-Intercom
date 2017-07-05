<?php

use MediaWiki\MediaWikiServices; // for LinkRenderer support in the Pager class

class Intercom {

	static function logsendhandler( $type, $action, $title = null, $skin = null,
									$params = array(), $filterWikilinks = false )
	{
		if ( $type == 'intercom' && $action = 'send' ) {
			return wfMessage( 'intercomlogsend' )->params( $title->getPrefixedText(), $params[0] == 'intercom-urgent' ? wfMessage( 'intercom-urgentlist' )->text() : $params[0] )->parse();
		}
	}

	static function loghidehandler( $type, $action, $title = null, $skin = null,
									$params = array(), $filterWikilinks = false )
	{
		if ( $type == 'intercom' && $action = 'hide' ) {
			return wfMessage( 'intercomloghide' )->params( $title->getPrefixedText() )->parse();
		}
	}

	static function logunhidehandler( $type, $action, $title = null, $skin = null,
									$params = array(), $filterWikilinks = false )
	{
		if ( $type == 'intercom' && $action = 'unhide' ) {
			return wfMessage( 'intercomlogunhide' )->params( $title->getPrefixedText() )->parse();
		}
	}

	// This is used by Special:Intercom
	static function getMessage( $messid ) {
		global $wgUser;
		$userid = $wgUser->getId();

		$dbr = wfGetDB( DB_SLAVE );
		# get the users lists
		$conds = array( 'id' => $messid );

		$res = $dbr->select(
			'intercom_message',
			array( 'id', 'summary', 'message', 'author', 'list', 'timestamp', 'parsed' ),
			$conds,
			__METHOD__,
			array( 'ORDER BY' => 'timestamp DESC', 'LIMIT' => 1 )
		);

		if ( $res ) {
			if ( $res->numRows() > 0 ) {
				$row = $res->fetchRow();
				$groupName = $row['list'] == 'intercom-urgent' ? wfMessage( 'intercom-urgentlist' )->text() : $row['list'];
				$mess = array(
					'id'       => $row['id'],
					'summary'  => $row['summary'],
					'text'     => $row['message'],
					'sender'   => User::newFromId( $row['author'] )->getName(),
					'senderid' => $row['author'],
					'group'    => $groupName,
					'time'     => $row['timestamp'],
					'parsed'   => $row['parsed'],
					'realgroup' => $row['list']
				);
			}
			$res->free();
		}

		if ( $mess ) {
			return Intercom::_rendermessage( $mess, $userid, false );
		} else {
			return false;
		}
	}

	# used by preview
	static function rendermessage( $mess, $userid, $buttons = false ) {
		$groupclass = Sanitizer::escapeClass( 'intercom-' . $mess['realgroup'] );
		return '<div id="intercommessage" class="usermessage ' .
			htmlspecialchars( $groupclass ) . '" style="text-align:left; font-weight: normal;">' .
			Intercom::_rendermessage( $mess, $userid, $buttons ) . '</div>';
	}

	// ashley: had to make public for the SiteNoticeAfter hook, it's now in IntercomHooks
	public static function _rendermessage( $mess, $userid, $buttons = true ) {
		global $wgLang;

		$mNext = wfMessage( 'intercom-next' )->escaped();
		$mPrev = wfMessage( 'intercom-prev' )->escaped();
		$mMark = wfMessage( 'intercom-markread' )->escaped();
		$mId = $mess['id'];
		$mTime = $mess['time'];
		if ( $buttons ) {
			// @todo FIXME: display prev/next buttons only if there *is* a
			// previous or a next message to display!
			$nextButton = "<span class='intercombutton'><a class='intercom-button-next' data-intercom-id='{$mId}' data-intercom-message-time='{$mTime}' href='#'>{$mNext}</a></span>";
			$prevButton = "<span class='intercombutton'><a class='intercom-button-previous' data-intercom-id='{$mId}' data-intercom-message-time='{$mTime}' href='#'>{$mPrev}</a></span>";
			$markButton = '';
			if ( $userid != 0 ) {
				$markButton = "<span class='intercombutton'><a class='intercom-button-markasread' data-intercom-id='{$mId}' data-intercom-message-time='{$mTime}' href='#'>{$mMark}</a></span>";
			}
		}

		// various parsedness states: 0 unparsed, 1 completely parsed, 2 presave parsed
		if ( $mess['parsed'] == 0 || $mess['parsed'] == 2 ) {
			global $wgTitle, $wgUser, $wgParser;
			$myParser = clone $wgParser;
			$myParserOptions = new ParserOptions();
			# $myParserOptions->initialiseFromUser($wgUser);
			$myParserOptions->enableLimitReport( false );
			if ( $mess['parsed'] == 0 ) {
				$pre = $myParser->preSaveTransform( $mess['text'], $wgTitle, $wgUser, $myParserOptions );
			} else {
				$pre = $mess['text'];
			}
			$messText = $myParser->parse( $pre, $wgTitle, $myParserOptions )->getText();
		} elseif ( $mess['parsed'] == 1 ) {
			$messText = $mess['text'];
		}

		// $messText is parsed
		// sender is raw username, can be parsed
		// summary is unparsed, parse partially
		// group is unparsed, can be parsed
		// time should not be parsed
		// buttons should not be parsed

		$senderText = $mess['sender'];
		$skin = RequestContext::getMain()->getSkin();
		if ( $skin ) {
			$senderText = Linker::userLink( $mess['senderid'], $mess['sender'] ) . ' (' .
						Linker::userTalkLink( $mess['senderid'], $mess['sender'] ) . ')';
		}

		$params = array( $mess['summary'], $messText, $senderText, $mess['group'],
						$wgLang->timeanddate( $mess['time'], true ) );
		if ( $buttons ) {
			$params[] = $nextButton;
			$params[] = $prevButton;
			$params[] = $markButton;
		}
		$text = wfMessage( $buttons ? 'intercomnotice' : 'intercommessage' )->rawParams( $params )->parse();
		return $text;
	}

	static function getList( $dbr, $userid ) {
		if ( $userid == 0 ) {
			return array( 'intercom-urgent' );
		}

		$res = $dbr->select(
			'intercom_list',
			'list',
			array( 'userid' => $userid ),
			__METHOD__
		);
		$list = array();
		$urgentFound = false;
		while ( $row = $res->fetchRow() ) {
			# switch behaviour of default list, if it's in the table, then the user has disabled it.
			if ( $row['list'] != 'intercom-urgent' ) {
				$list[] = $row['list'];
			} else {
				$urgentFound = true;
			}
		}
		$res->free();

		# switch behaviour of default list,
		if ( !$urgentFound ) {
			# if it's not in the array, then the user has not disabled it
			$list[] = 'intercom-urgent';
		}

		return $list;
	}

	// This is used by JavaScript
	static private function _getMessage( $messageid, $time, $next = false ) {
		global $wgUser;

		$userid = $wgUser->getId();
		$dbr = wfGetDB( DB_SLAVE );
		$list = Intercom::getList( $dbr, $userid );
		if ( !count( $list ) ) {
			return json_encode( false );
		}

		$res = $dbr->select(
			'intercom_read',
			'messageid',
			array( 'userid' => $userid ),
			__METHOD__
		);
		$read = array();
		while ( $row = $res->fetchRow() ) {
			$read[] = $row['messageid'];
		}
		$res->free();

		$conds = array( 'list' => $list );
		if ( count( $read ) > 0 ) {
			$readlist = $dbr->makeList( $read );
			$conds[] = "id NOT IN ($readlist)";
		}

		$order = $next ? 'ASC' : 'DESC';
		$conds[] = 'timestamp ' . ( $next ? '>' : '<' ) . $dbr->addQuotes( $time );
		$conds[] = 'expires > ' . time();
		$res = $dbr->select(
			'intercom_message',
			array( 'id', 'summary', 'message', 'author', 'list', 'timestamp', 'parsed' ),
			$conds,
			__METHOD__,
			array( 'ORDER BY' => "timestamp {$order}", 'LIMIT' => 1 )
		);
		if ( $res->numRows() > 0 ) {
			$row = $res->fetchRow();
			$groupName = $row['list'] == 'intercom-urgent' ? wfMessage( 'intercom-urgentlist' )->text() : $row['list'];
			$mess = array(
				'id'       => $row['id'],
				'summary'  => $row['summary'],
				'text'     => $row['message'],
				'sender'   => User::newFromId( $row['author'] )->getName(),
				'senderid' => $row['author'],
				'group'    => $groupName,
				'time'     => $row['timestamp'],
				'parsed'   => $row['parsed'],
				'realgroup' => $row['list']
			);
			$divclass = 'usermessage ' . Sanitizer::escapeClass( 'intercom-' . $mess['realgroup'] );
			global $wgParser;
			# initialize wgParser for _rendermessage
			$wgParser->firstCallInit();
			return json_encode( array(
				'class' => $divclass,
				'message' => Intercom::_rendermessage( $mess, $userid )
			) );
		} else {
			return json_encode( false );
		}
	}

	static function getNextMessage( $messageid, $time ) {
		return Intercom::_getMessage( $messageid, $time, true );
	}

	static function getPrevMessage( $messageid, $time ) {
		return Intercom::_getMessage( $messageid, $time, false );
	}

	static function markRead( $messageid, $userid = null ) {
		global $wgUser;
		if ( $userid === null ) {
			$userid = $wgUser->getId();
		}
		if ( $userid >= 0 && $messageid > 0 ) {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->replace(
				'intercom_read',
				array( 'userid', 'messageid' ),
				array( 'userid' => $userid, 'messageid' => $messageid ),
				__METHOD__
			);
			$dbw->commit( __METHOD__ );
			return 'true';
		} else {
			return 'false';
		}
	}

	static function markUnread( $messageid, $userid = null ) {
		global $wgUser;
		if ( $userid === null ) {
			$userid = $wgUser->getId();
		}
		if ( $userid >= 0 && $messageid > 0 ) {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->delete(
				'intercom_read',
				array( 'userid' => $userid, 'messageid' => $messageid ),
				__METHOD__
			);
			$dbw->commit( __METHOD__ );
			return 'true';
		} else {
			return 'false';
		}
	}
}

class SpecialIntercom extends SpecialPage {
	public function __construct() {
		parent::__construct( 'Intercom' );
	}

	/**
	 * Group this special page under the correct group in Special:SpecialPages
	 *
	 * @return string
	 */
	protected function getGroupName() {
		return 'users';
	}

	public function execute( $par ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		$linkRenderer = $this->getLinkRenderer();
		$titleObject = $this->getPageTitle();

		$this->setHeaders();

		$action = $request->getVal( 'intercomaction', $par );
		$expiry = $request->getVal( 'wpExpiry' );
		$expiryother = $request->getVal( 'wpExpiryOther' );
		$preview = $request->getVal( 'intercom_preview' );
		$summary = $request->getVal( 'wpSummary' );

		if ( $action == 'writenew' && $request->wasPosted() && !$preview ) {
			# check expiry
			if ( $expiry != 'other' ) {
				$expiry_input = $expiry;
			} else {
				$expiry_input = $expiryother;
			}

			$expires = strtotime( $expiry_input );
			if ( $expires < 0 || $expires === false ) {
				$out->addWikiText( '<div class="error">' . $this->msg( 'intercom-wrongexpiry' )->text() . '</div>' );
				$preview = true;
			}

			# check for valid group
			if ( !$request->getVal( 'group' ) ) {
				$out->addWikiText( '<div class="error">' . $this->msg( 'intercom-nogroup' )->text() . '</div>' );
				$preview = true;
			}

			# check if user has permission to send to urgent or message
			if ( !in_array( 'intercom-sendmessage', $user->getRights() ) ) {
				throw new PermissionsError( 'intercom-sendmessage' );
				$preview = true;
			} else {
				if ( $request->getVal( 'group' ) == 'intercom-urgent' ) {
					if ( !in_array( 'intercom-sendurgent', $user->getRights() ) ) {
						throw new PermissionsError( 'intercom-sendurgent' );
						$preview = true;
					}
				}
			}

			# check edit token and if user is blocked
			if ( !$user->matchEditToken( $request->getVal( 'wpEditToken' ) ) || $user->isBlocked() ) {
				# copied form Title.php
				$block = $user->mBlock;

				$id = $user->blockedBy();
				$reason = $user->blockedFor();
				if ( $reason == '' ) {
					$reason = $this->msg( 'blockednoreason' )->text();
				}
				$ip = $request->getIP();

				if ( is_numeric( $id ) ) {
					$name = User::whoIs( $id );
				} else {
					$name = $id;
				}

				global $wgContLang;

				$link = '[[' . $wgContLang->getNsText( NS_USER ) . ":{$name}|{$name}]]";
				$blockid = $block->mId;
				$blockExpiry = $user->mBlock->mExpiry;
				$blockTimestamp = $this->getLanguage()->timeanddate( wfTimestamp( TS_MW, $user->mBlock->mTimestamp ), true );

				if ( $blockExpiry == 'infinity' ) {
					// Entry in database (table ipblocks) is 'infinity' but 'ipboptions' uses 'infinite' or 'indefinite'
					$scBlockExpiryOptions = $this->msg( 'ipboptions' )->text();

					foreach ( explode( ',', $scBlockExpiryOptions ) as $option ) {
						if ( strpos( $option, ':' ) == false ) {
							continue;
						}

						list ( $show, $value ) = explode( ':', $option );

						if ( $value == 'infinite' || $value == 'indefinite' ) {
							$blockExpiry = $show;
							break;
						}
					}
				} else {
					$blockExpiry = $this->getLanguage()->timeanddate( wfTimestamp( TS_MW, $blockExpiry ), true );
				}

				$intended = $user->mBlock->mAddress;

				$errors[] = array(
					( $block->mAuto ? 'autoblockedtext' : 'blockedtext' ),
					$link, $reason, $ip, $name,
					$blockid, $blockExpiry, $intended, $blockTimestamp
				);

				$out->showPermissionsErrorPage( $errors );

				$preview = true;
			}

			# run hook for additional checks (e.g. vandal bin)
			$hookError = '';
			if ( !Hooks::run( 'Intercom-IsAllowedToSend', array( &$hookError ) ) ) {
				if ( $hookError != '' ) {
					$out->addHTML( $hookError );
				}
				$preview = true;
			}
		}

		if (
			( $action == 'writenew' || $action == 'selectgroups' || $action == 'cancel' || $action == 'uncancel' ) &&
			$request->wasPosted() && !$user->matchEditToken( $request->getVal( 'wpEditToken' ) )
		)
		{
			$out->addWikiMsg( 'session_fail_preview' );
		}

		if (
			( $action == 'cancel' || $action == 'uncancel' ) &&
			$request->wasPosted() &&
			$user->matchEditToken( $request->getVal( 'wpEditToken' ) )
		)
		{
			if ( wfReadOnly() ) {
				throw new ReadOnlyError;
			}

			if ( $action == 'cancel' ) {
				Intercom::markRead( $request->getVal( 'message' ), 0 );
				$log = new LogPage( 'intercom' );
				$target = SpecialPage::getTitleFor( 'Intercom', $request->getVal( 'message' ) );
				$log->addEntry( 'hide', $target, null, null, $user );
				$out->addWikiMsg( 'intercom-cancelsuccess' );
			} elseif ( $action == 'uncancel' ) {
				Intercom::markUnread( $request->getVal( 'message' ), 0 );
				$log = new LogPage( 'intercom' );
				$target = SpecialPage::getTitleFor( 'Intercom', $request->getVal( 'message' ) );
				$log->addEntry( 'unhide', $target, null, null, $user );
				$out->addWikiMsg( 'intercom-uncancelsuccess' );
			}

			$out->addHTML( $linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor( 'Intercom' ),
				$this->msg( 'intercom-return' )->escaped()
			) );
		} elseif (
			$action == 'selectgroups' && $request->wasPosted() &&
			$user->matchEditToken( $request->getVal( 'wpEditToken' ) )
		)
		{
			if ( wfReadOnly() ) {
				throw new ReadOnlyError;
			}

			if ( $user->getId() == 0 ) {
				$out->addWikiMsg( 'intercom-anon' );
			} else {
				$lists = $this->msg( 'intercom-list' )->text();
				$lists = preg_replace( "/\*/", '', $lists );
				$options = explode( "\n", $lists );
				$options = preg_replace( "/^intercom-urgent$/", '_intercom-urgent', $options );
				$dbw = wfGetDB( DB_MASTER );
				for ( $i = 0; $i < count( $options ); ++$i ) {
					if ( $request->getVal( urlencode( $options[$i] ) ) ) {
						$dbw->replace(
							'intercom_list',
							array( 'userid', 'list' ),
							array( 'userid' => $user->getId(), 'list' => $options[$i] ),
							__METHOD__
						);
					} else {
						$dbw->delete(
							'intercom_list',
							array( 'userid' => $user->getId(), 'list' => $options[$i] ),
							__METHOD__
						);
					}
				}

				if ( !$request->getVal( 'intercom-urgent' ) ) {
					# user does not want to see urgent, so place it in the list
					$dbw->replace(
						'intercom_list',
						array( 'userid', 'list' ),
						array( 'userid' => $user->getId(), 'list' => 'intercom-urgent' ),
						__METHOD__
					);
				} else {
					# user wants to see urgent, remove it from list
					$dbw->delete(
						'intercom_list',
						array( 'userid' => $user->getId(), 'list' => 'intercom-urgent' ),
						__METHOD__
					);
				}

				$dbw->commit( __METHOD__ );
				$out->redirect( $titleObject->getFullURL( '' ) );
			}
		} elseif ( $action == 'writenew' && $request->wasPosted() && !$preview ) {
			if ( wfReadOnly() ) {
				throw new ReadOnlyError;
			}

			if ( $user->getId() == 0 ) {
				$out->addWikiMsg( 'intercom-anon' );
			} else {
				if ( $request->getVal( 'intercom_sendmessage' ) && $request->getVal( 'group' ) ) {
					global $wgTitle, $wgParser;
					$myParser = clone $wgParser;
					$myParserOptions = new ParserOptions();
					# $myParserOptions->initialiseFromUser($user);
					$myParserOptions->enableLimitReport( false );
					$pre = $myParser->preSaveTransform(
						$request->getVal( 'wpTextbox1', '' ),
						$wgTitle,
						$user,
						$myParserOptions
					);
					// $result = $myParser->parse($pre, $wgTitle, $myParserOptions, false);

					$dbw = wfGetDB( DB_MASTER );
					$dbw->insert(
						'intercom_message',
						array(
							'summary' => htmlspecialchars( $summary ),
							'message' => $pre, // $result->getText(),
							'author' => $user->getId(),
							'list' => urldecode( $request->getVal( 'group' ) ),
							'timestamp' => wfTimestampNow(),
							'expires' => $expires,
							'parsed' => 2
						),
						__METHOD__
					);
					$message_id = $dbw->insertId();
					$dbw->commit( __METHOD__ );
					$log = new LogPage( 'intercom' );
					$target = SpecialPage::getTitleFor( 'Intercom', $message_id );
					$log->addEntry(
						'send', $target, htmlspecialchars( $summary ),
						array( urldecode( $request->getVal( 'group' ) ) ),
						$user
					);
				}
				$out->redirect( $titleObject->getFullURL( '' ) );
			}
		} elseif ( $action == 'selectgroups' ) {
			if ( wfReadOnly() ) {
				throw new ReadOnlyError;
			}

			if ( $user->getId() == 0 ) {
				$out->addWikiMsg( 'intercom-anon' );
			} else {
				$lists = $this->msg( 'intercom-list' )->text();
				$lists = preg_replace( "/\*/", '', $lists );
				$options = explode( "\n", $lists );
				$options = preg_replace( "/^intercom-urgent$/", '_intercom-urgent', $options );
				$dbr = wfGetDB( DB_SLAVE );
				$res = $dbr->select(
					'intercom_list',
					'list',
					array( 'userid' => $user->getId() ),
					__METHOD__
				);
				$checked = array( 'intercom-urgent' => 1 );
				while ( $row = $res->fetchRow() ) {
					if ( $row['list'] == 'intercom-urgent' ) {
						$checked['intercom-urgent'] = 0;
					} else {
						$checked[$row['list']] = 1;
					}
				}
				$res->free();
				$out->addHTML(
					Xml::openElement( 'form', array(
						'id' => 'intercomgroups',
						'method' => 'post',
						'action' => $titleObject->getLocalURL( 'intercomaction=selectgroups' ),
					) ) .
					Xml::openElement( 'fieldset' ) .
					Xml::element( 'legend', null, $this->msg( 'intercomgroups-legend' )->text() )
				);

				$out->addHTML( '<p>' .
					Xml::check( 'intercom-urgent', $checked['intercom-urgent'], array( 'id' => 'intercom-urgent' ) ) .
					Xml::label( $this->msg( 'intercom-urgentlist' )->text(), 'intercom-urgent' ) .
					'</p>'
				);

				// @todo FIXME: Fix the issue properly instead of suppressing warnings.
				// The Xml::check line causes an E_NOTICE:
				// Undefined index: General site news in ../extensions/Intercom/Intercom.body.php on line 601
				MediaWiki\suppressWarnings();
				for ( $i = 0; $i < count( $options ); ++$i ) {
					$out->addHTML(
						'<p>' .
						Xml::check( urlencode( $options[$i] ), $checked[$options[$i]], array( 'id' => $options[$i] ) ) .
						Xml::label( $options[$i], $options[$i] ) .
						'</p>'
					);
				}
				MediaWiki\restoreWarnings();

				$out->addHTML(
					Xml::submitButton(
						$this->msg( 'intercomgroups-save' )->text(),
						array(
							'name' => 'intercomgroups_save',
							'accesskey' => 's'
						)
					) .
					Html::hidden( 'wpEditToken', $user->getEditToken() ) .
					Xml::closeElement( 'fieldset' ) .
					Xml::closeElement( 'form' )
				);
			}
		} elseif ( $action == 'writenew' ) {
			if ( wfReadOnly() ) {
				throw new ReadOnlyError;
			}

			if ( $user->getId() == 0 ) {
				$out->addWikiMsg( 'intercom-anon' );
			} else {
				$dbr = wfGetDB( DB_SLAVE );
				$res = $dbr->select(
					'intercom_list',
					'list',
					array( 'userid' => $user->getId() ),
					__METHOD__
				);
				$groups = array();
				while ( $row = $res->fetchRow() ) {
					$groups[] = $row['list'];
				}
				$res->free();

				if ( $preview ) {
					global $wgTitle, $wgParser;

					$myParser = clone $wgParser;
					$myParserOptions = ParserOptions::newFromUser( $user );
					$myParserOptions->enableLimitReport( false );
					$pre = $myParser->preSaveTransform(
						$request->getVal( 'wpTextbox1', '' ),
						$wgTitle,
						$user,
						$myParserOptions
					);

					$previewList = urldecode( $request->getVal( 'group' ) );
					$groupName = $previewList == 'intercom-urgent' ? $this->msg( 'intercom-urgentlist' )->text() : $previewList;
					$mess = array(
						'id'       => 0,
						'summary'  => htmlspecialchars( $summary ),
						'text'     => $pre,
						'sender'   => $user->getName(),
						'senderid' => $user->getId(),
						'group'    => $groupName,
						'time'     => wfTimestampNow(),
						'parsed'   => 2,
						'realgroup' => $previewList,
					);
					$out->addHTML( Intercom::rendermessage( $mess, $user->getId(), false ) );
					/*$myParser = clone $wgParser;
					$myParserOptions = new ParserOptions();
					$myParserOptions->initialiseFromUser($user);
					$myParserOptions->enableLimitReport(false);
					$pre = $myParser->preSaveTransform($request->getVal('wpTextbox1',''), $wgTitle, $user, $myParserOptions);
					$result = $myParser->parse($pre, $wgTitle, $myParserOptions);
					$out->addHTML($result->getText());*/
				}

				$expiryOptionsRaw = $this->msg( 'intercom-expires' )->inContentLanguage()->text();
				$expiryOptions = Xml::option( $this->msg( 'intercom-other' )->text(), 'other' );
				foreach ( explode( ',', $expiryOptionsRaw ) as $option ) {
					if ( strpos( $option, ':' ) === false ) {
						$option = "$option:$option";
					}
					list( $show, $value ) = explode( ':', $option );
					$show = htmlspecialchars( $show );
					$value = htmlspecialchars( $value );
					$expiryOptions .= Xml::option( $show, $value, $expiry === $value ? true : false ) . "\n";
				}

				if ( $user->getOption( 'showtoolbar' ) ) {
					# prepare toolbar for edit buttons
					$toolbar = EditPage::getEditToolbar();
				} else {
					$toolbar = '';
				}

//				$out->addScriptFile( 'edit.js' );

				$out->addHTML(
					"{$toolbar}" .
					Xml::openElement( 'form', array(
						'id' => 'intercomedit',
						'method' => 'post',
						'action' => $titleObject->getLocalURL( 'intercomaction=writenew' ),
					) ) .
					Xml::textarea(
						'wpTextbox1',
						$request->getVal( 'wpTextbox1', '' ),
						80,
						25,
						array( 'id' => 'wpTextbox1', 'accesskey' => ',' )
					) .
					'<p>'
				);

				// summary input, copied from EditPage::getSummaryInput
				$inputAttrs = array(
					'id' => 'wpSummary',
					'maxlength' => '200',
					'tabindex' => '1',
					'size' => 60,
					'spellcheck' => 'true',
				);

				$spanLabelAttrs = array(
					'class' => 'mw-summary',
					'id' => 'wpSummaryLabel'
				);

				$label = Xml::element( 'label', array( 'for' => $inputAttrs['id'] ), $this->msg( 'intercom-summary' )->text() );
				$label = Xml::tags( 'span', $spanLabelAttrs, $label );

				$input = Html::input( 'wpSummary', $summary, 'text', $inputAttrs );

				$out->addModules( 'ext.intercom.special' );

				$out->addHTML( "{$label} {$input}" .
					'</p>' .
					Xml::tags(
						'select',
						array(
							'id' => 'wpExpiry',
							'name' => 'wpExpiry'
						),
						$expiryOptions
					) .
					Xml::input( 'wpExpiryOther', 45, $expiryother,
						array( 'id' => 'wpExpiryOther' ) ) . '&nbsp;' .
					'<select id="group" name="group">'
				);

				for ( $i = 0; $i < count( $groups ); ++$i ) {
					if ( $groups[$i] != 'intercom-urgent' ) {
						# Don't show the urgent group, handled by code below
						$out->addHTML(
							Xml::option(
								$groups[$i],
								urlencode( $groups[$i] ),
								$request->getVal( 'group' ) === urlencode( $groups[$i] ) ? true : false
							) . "\n"
						);
					}
				}

				if ( in_array( 'intercom-sendurgent', $user->getRights() ) ) {
					$out->addHTML(
						Xml::option(
							$this->msg( 'intercom-urgentlist' )->text(),
							'intercom-urgent',
							$request->getVal( 'group' ) === 'intercom-urgent' ? true : false
						) . "\n"
					);
				}
				$out->addHTML(
					'</select>' . '&nbsp;' .
					( ( count( $groups ) > 0 ) ? Xml::submitButton(
						$this->msg( 'intercom-sendmessage' )->text(),
						array(
							'name' => 'intercom_sendmessage',
							'accesskey' => 's'
						)
					) : '' ) .
					'&nbsp;' .
					Xml::submitButton(
						$this->msg( 'intercom-preview' )->text(),
						array(
							'name' => 'intercom_preview',
							'accesskey' => 'p'
						)
					) . '&nbsp;' .
					Html::hidden( 'wpEditToken', $user->getEditToken() ) .
					'<div class="mw-editTools">'
				);
				$out->addWikiMsgArray( 'edittools', array(), array( 'content' ) );
				$out->addHTML( '</div>' .
					Xml::closeElement( 'form' )
				);
			}
		} else {
			# show individual message
			$messid = $request->getVal( 'message', $par );
			if ( $messid ) {
				if ( $mes = Intercom::getMessage( $messid ) ) {
					if ( $action == 'cancel' ) {
						if ( in_array( 'intercom-sendurgent', $user->getRights() ) ) {
							$out->addWikiMsg( 'intercom-cancelconfirm' );
							$out->addHTML(
								Xml::openElement( 'form', array(
									'id' => 'intercomcancel',
									'method' => 'post',
									'action' => $titleObject->getLocalURL( 'intercomaction=cancel' ),
								) ) .
								Xml::submitButton(
									$this->msg( 'intercom-cancelbutton' )->text(),
									array(
										'name' => 'intercom_cancelbutton',
										'accesskey' => 's'
									)
								) .
								Html::hidden( 'message', $messid ) .
								Html::hidden( 'wpEditToken', $user->getEditToken() ) .
								Xml::closeElement( 'form' )
							);
						} else {
							throw new PermissionsError( 'intercom-sendurgent' );
						}
					} elseif ( $action == 'uncancel' ) {
						if ( in_array( 'intercom-sendurgent', $user->getRights() ) ) {
							$out->addWikiMsg( 'intercom-uncancelconfirm' );
							$out->addHTML(
								Xml::openElement( 'form', array(
									'id' => 'intercomuncancel',
									'method' => 'post',
									'action' => $titleObject->getLocalURL( 'intercomaction=uncancel' ),
								) ) .
								Xml::submitButton(
									$this->msg( 'intercom-uncancelbutton' )->text(),
									array(
										'name' => 'intercom_uncancelbutton',
										'accesskey' => 's'
									)
								) .
								Html::hidden( 'message', $messid ) .
								Html::hidden( 'wpEditToken', $user->editToken() ) .
								Xml::closeElement( 'form' )
							);
						} else {
							throw new PermissionsError( 'intercom-sendurgent' );
						}
					}
					$out->addHTML(
						Xml::fieldset(
							$this->msg( 'intercom-messageheader' )->text(),
							$mes
						)
					);
				} else {
					$out->addWikiMsg( 'intercom-nomessage' );
				}
			}

			$newLink = $linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor( 'Intercom' ),
				$this->msg( 'intercom-newlink' )->text(),
				array(),
				array( 'intercomaction' => 'writenew' )
			);
			$groupsLink = $linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor( 'Intercom' ),
				$this->msg( 'intercom-groupslink' )->text(),
				array(),
				array( 'intercomaction' => 'selectgroups' )
			);
			$out->addHTML( "$newLink<p/>$groupsLink<p/>" );

			# pager
			$dbr = wfGetDB( DB_SLAVE );
			# get the users lists
			$userid = $user->getId();
			$list = Intercom::getList( $dbr, $userid );

			if ( count( $list ) != 0 ) {
				$pager = new IntercomPager( $list );
				$out->addHTML(
					$pager->getNavigationBar() . '<ul>' .
					$pager->getBody() . '</ul>' .
					$pager->getNavigationBar()
				);
			}
		}
	}
}


class IntercomPager extends ReverseChronologicalPager {
	private $mlist;

	function __construct( $list = array() ) {
		$this->mlist = $list;
		parent::__construct();
	}

	function formatRow( $row ) {
		static $lr = null;
		if ( is_null( $lr ) ) {
			$lr = MediaWikiServices::getInstance()->getLinkRenderer();
		}
		$lang = $this->getLanguage();

		$user = User::newFromId( $row->author );
		$listName = $row->list == 'intercom-urgent' ? $this->msg( 'intercom-urgentlist' )->text() : $row->list;
		$line = $this->msg( 'intercom-pager-row',
			array(
				$user->getName(),
				$listName,
				$lang->timeanddate( $row->timestamp, true ),
				$lang->timeanddate( $row->expires, true ),
				$row->summary
			)
		)->text();
		$readLink = $lr->makeKnownLink(
			SpecialPage::getTitleFor( 'Intercom' ),
			$this->msg( 'intercom-pager-readlink' )->text(),
			array(),
			array( 'message' => $row->id )
		);
		$cancelLink = '';
		$uncancelLink = '';
		if ( in_array( 'intercom-sendurgent', $this->getUser()->getRights() ) && $row->list == 'intercom-urgent' ) {
			$dbr = wfGetDB( DB_SLAVE );
			$res = $dbr->select(
				'intercom_read',
				'messageid, userid',
				array( 'messageid' => $row->id, 'userid' => 0 ),
				__METHOD__
			);
			if ( $res->numRows() == 0 ) {
				$cancelLink = ' - ' . $lr->makeKnownLink(
					SpecialPage::getTitleFor( 'Intercom' ),
					$this->msg( 'intercom-pager-cancellink' )->text(),
					array(),
					array( 'intercomaction' => 'cancel', 'message' => $row->id )
				);
			} else {
				$uncancelLink = ' - ' . $lr->makeKnownLink(
					SpecialPage::getTitleFor( 'Intercom' ),
					$this->msg( 'intercom-pager-uncancellink' )->text(),
					array(),
					array( 'intercomaction' => 'uncancel', 'message' => $row->id )
				);
			}
		}

		return "<li>$line $readLink $cancelLink $uncancelLink</li>\n";
	}

	function getQueryInfo() {
		return array(
			'tables' => 'intercom_message',
			'fields' => 'id, summary, message, author, list, timestamp, expires',
			'conds'  => array( 'list' => $this->mlist ),
		);
	}

	function getIndexField() {
		return 'id';
	}
}
