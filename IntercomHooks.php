<?php

class IntercomHooks {
	/**
	 * Add the JavaScript ResourceLoader module needed to all page loads.
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		$out->addModules( 'ext.intercom' );
	}

	// XXX FILTHY HACK
	// @todo FIXME: Get rid of this whole function, rewrite these three as API
	// module(s) and tweak Intercom.js accordingly to call the new API module(s)
	// as appropriate
	public static function onRegistration() {
		global $wgAjaxExportList;
		$wgAjaxExportList[] = 'Intercom::getNextMessage';
		$wgAjaxExportList[] = 'Intercom::getPrevMessage';
		$wgAjaxExportList[] = 'Intercom::markRead';
	}

	/**
	 * If there are any Intercom messages we should display in the site notice
	 * area, this hook handler takes care of that.
	 *
	 * @param string $siteNotice Existing site notice HTML, if any
	 * @param Skin $skin
	 */
	public static function onSiteNoticeAfter( &$siteNotice, $skin ) {
		$userid = $skin->getUser()->getId();

		$dbr = wfGetDB( DB_SLAVE );
		# get the users lists
		$list = Intercom::getList( $dbr, $userid );

		if ( count( $list ) == 0 ) {
			return false;
		}

#		if ($userid != 0) {
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
#		}

		$conds = array( 'list' => $list );
		if ( count( $read ) > 0 ) {
			$readlist = $dbr->makeList( $read );
			$conds[] = "id NOT IN ($readlist)";
		}

		$conds[] = 'expires > ' . time();

		$res = $dbr->select(
			'intercom_message',
			array( 'id', 'summary', 'message', 'author', 'list', 'timestamp', 'parsed' ),
			$conds,
			__METHOD__,
			array( 'ORDER BY' => 'timestamp DESC', 'LIMIT' => 1 )
		);

		$mess = array();
		if ( $res ) {
			while ( $row = $res->fetchRow() ) {
				$groupname = $row['list'] == 'intercom-urgent' ? $skin->msg( 'intercom-urgentlist' )->text() : $row['list'];
				$mess[] = array(
					'id'       => $row['id'],
					'summary'  => $row['summary'],
					'text'     => $row['message'],
					'sender'   => User::newFromId( $row['author'] )->getName(),
					'senderid' => $row['author'],
					'group'    => $groupname,
					'time'     => $row['timestamp'],
					'parsed'   => $row['parsed'],
					'realgroup' => $row['list']
				);
			}
			$res->free();
		}

		if ( count( $mess ) == 0 ) {
			return false;
		}

		# if there's a new intercom message, disable the cache to be able to show it.
		$skin->getOutput()->enableClientCache( false );

		$groupclass = Sanitizer::escapeClass( 'intercom-' . $mess[0]['realgroup'] );
		$siteNotice .= '<div id="intercommessage" class="usermessage ' .
			htmlspecialchars( $groupclass ) . '" style="text-align:left; font-weight: normal;">' .
			Intercom::_rendermessage( $mess[0], $userid ) . '</div>';
		return true;
	}

	/**
	 * Adds the new required database tables into the database when the user
	 * runs /maintenance/update.php (the core database updater script).
	 *
	 * @param DatabaseUpdater $updater
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$dir = __DIR__ . '/sql';

		// For non-MySQL/MariaDB/SQLite DBMSes, use the correct files from the
		// correct subdirectory
		// Commented out until PostgreSQL etc. are supported by this extension
		/*
		if ( !in_array( $dbType, array( 'mysql', 'sqlite' ) ) ) {
			$dbType = $updater->getDB()->getType();
			$dir = $dir . '/' . $dbType;
		}
		*/

		$tables = array(
			'intercom_list',
			'intercom_message',
			'intercom_read'
		);
		foreach ( $tables as $table ) {
			$updater->addExtensionUpdate( array( 'addTable', $table, "{$dir}/{$table}.sql", true ) );
		}

		return true;
	}

}