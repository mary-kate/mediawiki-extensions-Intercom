<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	exit;
}

# change varbinary to varchar depending on wiki settings
# create table /*$wgDBprefix*/intercom_message (id int NOT NULL auto_increment, summary tinyblob, message mediumblob, author int unsigned NOT NULL, list varbinary(255) NOT NULL, timestamp varbinary(14), expires varbinary(14), parsed boolean NOT NULL default true, PRIMARY KEY id (id)) /*$wgDBTableOptions*/;
# create table /*$wgDBprefix*/intercom_list (userid int unsigned NOT NULL, list varbinary(255) NOT NULL, PRIMARY KEY (userid,list)) /*$wgDBTableOptions*/;
# create table /*$wgDBprefix*/intercom_read (userid int unsigned NOT NULL, messageid int unsigned NOT NULL, PRIMARY KEY (userid,messageid)) /*$wgDBTableOptions*/;

# for rwbeta
# create table rbintercom_message (id int NOT NULL auto_increment, summary tinyblob, message mediumblob, author int unsigned NOT NULL, list varbinary(255) NOT NULL, timestamp varbinary(14), expires varbinary(14), parsed boolean NOT NULL default true, PRIMARY KEY id (id)) ENGINE=InnoDB, DEFAULT CHARSET=binary;
# create table rbintercom_list (userid int unsigned NOT NULL, list varbinary(255) NOT NULL, PRIMARY KEY (userid,list)) ENGINE=InnoDB, DEFAULT CHARSET=binary;
# create table rbintercom_read (userid int unsigned NOT NULL, messageid int unsigned NOT NULL, PRIMARY KEY (userid,messageid)) ENGINE=InnoDB, DEFAULT CHARSET=binary;


# give crats the right to send urgent messages
$wgGroupPermissions['tech']['intercom-sendurgent'] = true;

# give autoconfirmed users the right to send urgent messages
$wgGroupPermissions['autoconfirmed']['intercom-sendmessage'] = true;

$wgExtensionCredits['other'][] = array(
	'name' => 'RationalWiki Intercom',
	'author' => '[http://rationalwiki.com/wiki/User:Tmtoulouse Trent Toulouse]',
	'url' => 'http://rationalwiki.com/',
	'description' => 'Creates a sitewide message to all users'
);

$wgHooks['SiteNoticeAfter'][] = 'Intercom::DisplayMessages';

$wgSpecialPages['Intercom'] = 'SpecialIntercom';

## Create new log type
$wgLogTypes[] = 'intercom';
$wgLogNames['intercom'] = 'intercomlogname';
$wgLogHeaders['intercom'] = 'intercomlogheader';
$wgLogActionsHandlers['intercom/send'] = 'Intercom::logsendhandler';
$wgLogActionsHandlers['intercom/hide'] = 'Intercom::loghidehandler';
$wgLogActionsHandlers['intercom/unhide'] = 'Intercom::logunhidehandler';

global $wgUseAjax;
if ( $wgUseAjax ) {
	$wgAjaxExportList[] = 'Intercom::getNextMessage';
	$wgAjaxExportList[] = 'Intercom::getPrevMessage';
	$wgAjaxExportList[] = 'Intercom::markRead';
}

$wgHooks['AjaxAddScript'][] = 'Intercomaddjs';

function Intercomaddjs( $out ) {
	global $wgJsMimeType, $wgScriptPath;
	$out->addScript( "<script type=\"{$wgJsMimeType}\" src=\"{$wgScriptPath}/extensions/Intercom/js/Intercom.js\"></script>" );
	return true;
}

## include path
$wgIntercomIP = dirname( __FILE__ );
$wgExtensionMessagesFiles['Intercom'] = "$wgIntercomIP/Intercom.i18n.php";

## Load classes
$wgAutoloadClasses['Intercom'] = "$wgIntercomIP/Intercom.body.php";
$wgAutoloadClasses['SpecialIntercom'] = "$wgIntercomIP/Intercom.body.php";
