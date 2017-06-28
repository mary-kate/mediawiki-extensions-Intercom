<?php

class Intercom {

  static function logsendhandler( $type, $action, $title = NULL, $skin = NULL, 
                                  $params = array(), $filterWikilinks = false )
  {
    if ($type == 'intercom' && $action = 'send')
    {
      return wfMessage('intercomlogsend')->params($title->getPrefixedText(), $params[0] == 'intercom-urgent' ? wfMessage('intercom-urgentlist')->text() : $params[0])->parseInline();
    }
  }

  static function loghidehandler( $type, $action, $title = NULL, $skin = NULL, 
                                  $params = array(), $filterWikilinks = false )
  {
    if ($type == 'intercom' && $action = 'hide')
    {
      return wfMessage('intercomloghide')->params($title->getPrefixedText())->parseInline();
    }
  }

  static function logunhidehandler( $type, $action, $title = NULL, $skin = NULL, 
                                    $params = array(), $filterWikilinks = false )
  {
    if ($type == 'intercom' && $action = 'unhide')
    {
      return wfMessage('intercomlogunhide')->params($title->getPrefixedText())->parseInline();
    }
  }
  static function DisplayMessages(&$siteNotice) {
    global $wgUser;
    $userid = $wgUser->getId();
    
    $dbr = wfGetDB(DB_SLAVE);
    # get the users lists
    $list = Intercom::getList($dbr,$userid);
    
    if (count($list) == 0)
    {
      return false;
    }

#    if ($userid != 0)
#    {    
      $res = $dbr->select('intercom_read', 'messageid', array('userid' => $userid),'Intercom::DisplayMessages');
      $read = array();
      while ($row = $res->fetchRow())
      {
        $read[] = $row['messageid'];
      }
      $res->free();
#    }
    
    $conds = array('list' => $list);
    if (count($read) > 0)
    {
      $readlist = $dbr->makeList($read);
      $conds[] = "id NOT IN ($readlist)";
    }
    
    $conds[] = "expires > " . time();
    
    $res = $dbr->select('intercom_message','id, summary, message, author, list, timestamp, parsed',$conds,'Intercom::DisplayMessages',array('ORDER BY' => 'timestamp desc', 'LIMIT' => 1));
    
    $mess = array();
    if ($res)
    {
      while ($row = $res->fetchRow())
      {
        $groupname = $row['list'] == 'intercom-urgent' ? wfMessage('intercom-urgentlist')->text() : $row['list'];
        $mess[] = array('id'       => $row['id'],
                        'summary'  => $row['summary'],
                        'text'     => $row['message'],
                        'sender'   => User::newFromId($row['author'])->getName(),
                        'senderid' => $row['author'],
                        'group'    => $groupname,
                        'time'     => $row['timestamp'],
                        'parsed'   => $row['parsed'],
                        'realgroup'=> $row['list']);
      }
      $res->free();
    }
    
    if (count($mess) == 0)
    {
      return false;
    }
    
    # if there's a new intercom message, disable the cache to be able to show it.
    global $wgOut;
    $wgOut->enableClientCache(false);
        
    $groupclass = Sanitizer::escapeClass( 'intercom-'.$mess[0]['realgroup'] );
    $siteNotice .= "<div id=\"intercommessage\" class=\"usermessage " . htmlspecialchars( $groupclass ) . "\" style=\"text-align:left; font-weight: normal;\">" . Intercom::_rendermessage($mess[0],$userid) . '</div>';
    return true;
  }
  
  //This is used by Special:Intercom
  static function getMessage($messid)
  {
    global $wgUser;
    $userid = $wgUser->getId();
    
    $dbr = wfGetDB(DB_SLAVE);
    # get the users lists
    $conds = array('id' => $messid);
    
    $res = $dbr->select('intercom_message','id, summary, message, author, list, timestamp, parsed',$conds,'Intercom::DisplayMessages',array('ORDER BY' => 'timestamp desc', 'LIMIT' => 1));
    
    if ($res)
    {
      if ($res->numRows() > 0)
      {
        $row = $res->fetchRow();
        $groupname = $row['list'] == 'intercom-urgent' ? wfMessage('intercom-urgentlist')->text() : $row['list'];
        $mess = array('id'       => $row['id'],
                        'summary'  => $row['summary'],
                        'text'     => $row['message'],
                        'sender'   => User::newFromId($row['author'])->getName(),
                        'senderid' => $row['author'],
                        'group'    => $groupname,
                        'time'     => $row['timestamp'],
                        'parsed'   => $row['parsed'],
                        'realgroup'=> $row['list']);
      }
      $res->free();
    }
    
    if ($mess)
    {
      return Intercom::_rendermessage($mess,$userid, false);
    } else {
      return false;
    }
  }
  
  #used by preview
  static function rendermessage($mess, $userid, $buttons = false)
  {
    $groupclass = Sanitizer::escapeClass( 'intercom-'.$mess['realgroup'] );
    return "<div id=\"intercommessage\" class=\"usermessage " . htmlspecialchars( $groupclass ) . "\" style=\"text-align:left; font-weight: normal;\">" . Intercom::_rendermessage($mess,$userid,$buttons) . '</div>';
  }
  
  static private function _rendermessage($mess, $userid, $buttons = true)
  {
    global $wgLang;
    $mNext = wfMessage('intercom-next')->escaped();
    $mPrev = wfMessage('intercom-prev')->escaped();
    $mMark = wfMessage('intercom-markread')->escaped();
    $mId = $mess['id'];
    $mTime = $mess['time'];
    if ($buttons)
    {
      $nextButton = "<span class='intercombutton'><a href='javascript:nextMessage({$mId},{$mTime})'>{$mNext}</a></span>";
      $prevButton = "<span class='intercombutton'><a href='javascript:prevMessage({$mId},{$mTime})'>{$mPrev}</a></span>";
      $markButton = "";
      if ($userid != 0)
      {
        $markButton = "<span class='intercombutton'><a href='javascript:markRead({$mId},{$mTime})'>{$mMark}</a></span>";
      }
    }
    
    
    //various parsedness states: 0 unparsed, 1 completely parsed, 2 presave parsed
    if ($mess['parsed'] == 0 || $mess['parsed'] == 2) {
      global $wgTitle;
      global $wgUser;
      global $wgParser;
      $myParser = clone $wgParser;
      $myParserOptions = new ParserOptions();
#      $myParserOptions->initialiseFromUser($wgUser);
      $myParserOptions->enableLimitReport(false);
      if ($mess['parsed'] == 0) {
        $pre = $myParser->preSaveTransform($mess['text'], $wgTitle, $wgUser , $myParserOptions);
      } else {
        $pre = $mess['text'];
      }
      $messtext = $myParser->parse($pre, $wgTitle, $myParserOptions)->getText();
    } elseif ($mess['parsed'] == 1) {
      $messtext = $mess['text'];
    }

    
    //$messtext is parsed
    //sender is raw username, can be parsed
    //summary is unparsed, parse partially
    //group is unparsed, can be parsed
    //time should not be parsed
    //buttons should not be parsed
    
    $sendertext = $mess['sender'];
    $skin = RequestContext::getMain()->getSkin();
    if ($skin) {
      $sendertext = $skin->userLink( $mess['senderid'], $mess['sender'] ) . ' (' .
                    $skin->userTalkLink( $mess['senderid'], $mess['sender'] ) . ')';
    }
    
    $params = array( $mess['summary'], $messtext, $sendertext, $mess['group'], 
                     $wgLang->timeanddate($mess['time'],true));
    if ($buttons)
    {
      $params[] = $nextButton;
      $params[] = $prevButton;
      $params[] = $markButton;
    }
    $text = wfMessage($buttons ? 'intercomnotice' : 'intercommessage')->rawParams($params)->parse();
    return $text;
  }
  
  static function getList($dbr, $userid)
  {
    if ($userid == 0)
    {
      return array('intercom-urgent');
    }
    
    $res = $dbr->select('intercom_list', 'list', array('userid' => $userid),'Intercom::getList');
    $list = array();
    $urgentfound = false;
    while ($row = $res->fetchRow())
    {
      # switch behaviour of default list, if it's in the table, then the user has disabled it.
      if ($row['list'] != 'intercom-urgent') {
        $list[] = $row['list'];
      } else {
        $urgentfound = true;
      }
    }
    $res->free();
    
    # switch behaviour of default list, 
    if (!$urgentfound)
    {
      # if it's not in the array, then the user has not disabled it
      $list[] = 'intercom-urgent';
    }
    
    return $list;
  }
  
  //This is used by Javascript
  static private function _getMessage($messageid,$time,$next = false)
  {
    global $wgUser;
    $userid = $wgUser->getId();
    $dbr = wfGetDB(DB_SLAVE);
    $list = Intercom::getList($dbr,$userid);
    if (!count($list)) 
    {
      return json_encode(false);
    }
    
    $res = $dbr->select('intercom_read', 'messageid', array('userid' => $userid),'Intercom::DisplayMessages');
    $read = array();
    while ($row = $res->fetchRow())
    {
      $read[] = $row['messageid'];
    }
    $res->free();
    
    $conds = array('list' => $list);
    if (count($read) > 0)
    {
      $readlist = $dbr->makeList($read);
      $conds[] = "id NOT IN ($readlist)";
    }
    
    $order = $next ? 'asc' : 'desc';
    $conds[] = 'timestamp ' . ($next ? '>' : '<') .  $dbr->addQuotes( $time );
    $conds[] = "expires > " . time();
    $res = $dbr->select('intercom_message','id, summary, message, author, list, timestamp, parsed',$conds,'Intercom::_getMessage',array('ORDER BY' => "timestamp {$order}", 'LIMIT' => 1));
    if ($res->numRows() > 0)
    {
      $row = $res->fetchRow();
      $groupname = $row['list'] == 'intercom-urgent' ? wfMessage('intercom-urgentlist')->text() : $row['list'];
      $mess = array('id'       => $row['id'],
                      'summary'  => $row['summary'],
                      'text'     => $row['message'],
                      'sender'   => User::newFromId($row['author'])->getName(),
                      'senderid' => $row['author'],
                      'group'    => $groupname,
                      'time'     => $row['timestamp'],
                      'parsed'   => $row['parsed'],
                      'realgroup'=> $row['list']);
      $divclass = 'usermessage ' . Sanitizer::escapeClass( 'intercom-'.$mess['realgroup'] );
      global $wgParser;
      # initialize wgParser for _rendermessage
      $wgParser->firstCallInit();
      return json_encode(array('class' => $divclass, 'message' => Intercom::_rendermessage($mess, $userid)));
    } else {
      return json_encode(false);
    }
  }
  
  static function getNextMessage($messageid, $time)
  {
    return Intercom::_getMessage($messageid,$time,true);
  }
  static function getPrevMessage($messageid, $time)
  {
    return Intercom::_getMessage($messageid,$time,false);
  }  
  static function markRead($messageid, $userid = null)
  {
    global $wgUser;
    if ($userid === null) $userid = $wgUser->getId();
    if ($userid>=0 && $messageid>0)
    {
      $dbw = wfGetDB(DB_MASTER);
      $dbw->replace('intercom_read',array('userid','messageid'),
                          array('userid' => $userid, 'messageid' => $messageid),
                          __METHOD__
                         );
      $dbw->commit();
      return 'true';
    } else {
      return 'false';
    }
  }
  static function markUnread($messageid, $userid = null)
  {
    global $wgUser;
    if ($userid === null) $userid = $wgUser->getId();
    if ($userid>=0 && $messageid>0)
    {
      $dbw = wfGetDB(DB_MASTER);
      $dbw->delete('intercom_read', array('userid' => $userid, 'messageid' => $messageid),
                          __METHOD__
                         );
      $dbw->commit();
      return 'true';
    } else {
      return 'false';
    }
  }
}

class SpecialIntercom extends SpecialPage {
  function __construct() {
    parent::__construct('Intercom');
    #SpecialPage::setGroup('Intercom','users');
    global $wgSpecialPageGroups;
    $wgSpecialPageGroups['Intercom']='users';
  }
  function execute( $par ) {
    global $wgOut, $wgRequest, $wgUser;
    
    $this->setHeaders();
    $titleObject = $this->getTitle();
    $action = $wgRequest->getVal( 'intercomaction', $par);
    $expiry = $wgRequest->getVal( 'wpExpiry');
    $expiryother = $wgRequest->getVal( 'wpExpiryOther');
    $preview = $wgRequest->getVal('intercom_preview');
    $summary = $wgRequest->getVal('wpSummary');
		
    if ($action == 'writenew' && $wgRequest->wasPosted() && !$preview )
    {
      # check expiry
      if ($expiry != 'other')
      {
        $expiry_input = $expiry;
      } else {
        $expiry_input = $expiryother;
      }
      $expires = strtotime( $expiry_input );
      if ($expires < 0 || $expires === false)
      {
        $wgOut->addWikiText('<div class="error">' . wfMessage('intercom-wrongexpiry')->text() . '</div>');
        $preview = true;
      }
      # check for valid group
      if (!$wgRequest->getVal('group')) {
        $wgOut->addWikiText('<div class="error">' . wfMessage('intercom-nogroup')->text() . '</div>');
        $preview = true;
      }
      # check if user has permission to send to urgent or message
      if (!in_array('intercom-sendmessage',$wgUser->getRights()))
      {
        $wgOut->permissionRequired( 'intercom-sendmessage' );
        $preview = true;
      } else {
        if ($wgRequest->getVal('group') == 'intercom-urgent')
        {
          if (!in_array('intercom-sendurgent',$wgUser->getRights()))
          {
            $wgOut->permissionRequired( 'intercom-sendurgent' );
            $preview = true;
          }
        }
      }
      # check edit token and if user is blocked
      if (!$wgUser->matchEditToken( $wgRequest->getVal( 'wpEditToken' ) ) || $wgUser->isBlocked() )
      {
        # copied form Title.php
      
        $block = $wgUser->mBlock;

        $id = $wgUser->blockedBy();
        $reason = $wgUser->blockedFor();
        if( $reason == '' ) {
          $reason = wfMessage( 'blockednoreason' )->text();
        }
        $ip = $wgRequest->getIP();

        if ( is_numeric( $id ) ) {
          $name = User::whoIs( $id );
        } else {
          $name = $id;
        }
        
        global $wgContLang, $wgLang;

        $link = '[[' . $wgContLang->getNsText( NS_USER ) . ":{$name}|{$name}]]";
        $blockid = $block->mId;
        $blockExpiry = $wgUser->mBlock->mExpiry;
        $blockTimestamp = $wgLang->timeanddate( wfTimestamp( TS_MW, $wgUser->mBlock->mTimestamp ), true );

        if ( $blockExpiry == 'infinity' ) {
          // Entry in database (table ipblocks) is 'infinity' but 'ipboptions' uses 'infinite' or 'indefinite'
          $scBlockExpiryOptions = wfMessage( 'ipboptions' )->text();

          foreach ( explode( ',', $scBlockExpiryOptions ) as $option ) {
            if ( strpos( $option, ':' ) == false )
              continue;

            list ($show, $value) = explode( ":", $option );

            if ( $value == 'infinite' || $value == 'indefinite' ) {
              $blockExpiry = $show;
              break;
            }
          }
        } else {
          $blockExpiry = $wgLang->timeanddate( wfTimestamp( TS_MW, $blockExpiry ), true );
        }

        $intended = $wgUser->mBlock->mAddress;

        $errors[] = array( ($block->mAuto ? 'autoblockedtext' : 'blockedtext'), $link, $reason, $ip, $name, 
                            $blockid, $blockExpiry, $intended, $blockTimestamp );
                            
        $wgOut->showPermissionsErrorPage($errors);
        
      
        $preview = true;
      }
      # run hook for additional checks (e.g. vandal bin)
      $hookError = '';
      if ( !wfRunHooks('Intercom-IsAllowedToSend',array(&$hookError) ) )
      {
        if ($hookError != '' ) {
          $wgOut->addHTML( $hookError );
        }
        $preview = true;
      }

    }
    
    if (($action == 'writenew' || $action == 'selectgroups' || $action == 'cancel' || $action == 'uncancel') && 
         $wgRequest->wasPosted() && !$wgUser->matchEditToken( $wgRequest->getVal( 'wpEditToken' ) ) )
    {
      $wgOut->addWikiText(wfMessage('session_fail_preview')->text());
    }
		
		if (($action == 'cancel' || $action == 'uncancel' ) && $wgRequest->wasPosted() && $wgUser->matchEditToken( $wgRequest->getVal( 'wpEditToken' ) ) )
		{
      
      if( wfReadOnly() ) {
        $wgOut->readOnlyPage();
        return;
      }
		  
      if ( $action == 'cancel' ) {
		    Intercom::markRead($wgRequest->getVal('message'),0);
				$log = new LogPage('intercom');
				$target = SpecialPage::getTitleFor('intercom',$wgRequest->getVal('message'));
				$log->addEntry('hide',$target,null,null,$wgUser);
		    $wgOut->addWikiText(wfMessage('intercom-cancelsuccess')->text());
      } elseif ($action == 'uncancel') {
		    Intercom::markUnread($wgRequest->getVal('message'),0);
				$log = new LogPage('intercom');
				$target = SpecialPage::getTitleFor('intercom',$wgRequest->getVal('message'));
				$log->addEntry('unhide',$target,null,null,$wgUser);
		    $wgOut->addWikiText(wfMessage('intercom-uncancelsuccess')->text());        
      }
		  $sk = RequestContext::getMain()->getSkin();
      $wgOut->addHTML($sk->link( SpecialPage::getTitleFor( 'intercom' ), wfMessage('intercom-return')->escaped(),array(),array() , 'known'));
		} elseif ($action == 'selectgroups' && $wgRequest->wasPosted() && $wgUser->matchEditToken( $wgRequest->getVal( 'wpEditToken' ) ) )
		{
      
      if( wfReadOnly() ) {
        $wgOut->readOnlyPage();
        return;
      }
      
		  if ($wgUser->getId() == 0)
		  {
		    $wgOut->addWikiText(wfMessage('intercom-anon')->text());
		  } else {
        $lists = wfMessage('intercom-list')->text();
        $lists = preg_replace("/\*/","",$lists);
        $options = split("\n",$lists);
        $options = preg_replace("/^intercom-urgent$/","_intercom-urgent",$options);
        $dbw = wfGetDB(DB_MASTER);
        for ($i=0;$i<count($options);++$i)
        {
          if ( $wgRequest->getVal( urlencode($options[$i]) ) )
          {
            $dbw->replace('intercom_list',array('userid','list'),
                          array('userid' => $wgUser->getId(), 'list' => $options[$i]),
                          'SpecialIntercom::execute'
                         );
          } else {
            $dbw->delete('intercom_list',array('userid' => $wgUser->getId(),'list' => $options[$i]),'SpecialIntercom::execute');
          }
        }
        
        if (!$wgRequest->getVal('intercom-urgent')) {
          # user does not want to see urgent, so place it in the list
          $dbw->replace('intercom_list',array('userid','list'),
                         array('userid' => $wgUser->getId(), 'list' => 'intercom-urgent'),
                         'SpecialIntercom::execute'
                       );
        } else {
          # user wants to see urgent, remove it from list
          $dbw->delete('intercom_list',array('userid' => $wgUser->getId(),'list' => 'intercom-urgent'),'SpecialIntercom::execute');
        }
        
        $dbw->commit();
        $wgOut->redirect($titleObject->getFullURL(''));
      }
    } elseif ($action == 'writenew' && $wgRequest->wasPosted() && !$preview) {
      
      if( wfReadOnly() ) {
        $wgOut->readOnlyPage();
        return;
      }
      
      if ($wgUser->getId() == 0)
      {
        $wgOut->addWikiText(wfMessage('intercom-anon')->text());
      } else {
        if ($wgRequest->getVal('intercom_sendmessage') && $wgRequest->getVal('group'))
        {
          global $wgTitle;
          global $wgParser;
          $myParser = clone $wgParser;
          $myParserOptions = new ParserOptions();
#          $myParserOptions->initialiseFromUser($wgUser);
          $myParserOptions->enableLimitReport(false);
          $pre = $myParser->preSaveTransform($wgRequest->getVal('wpTextbox1',''), $wgTitle, $wgUser , $myParserOptions);
          //$result = $myParser->parse($pre, $wgTitle, $myParserOptions, false);
          
          $dbw = wfGetDB(DB_MASTER);
          $dbw->insert('intercom_message',
                       array(
                         'summary' => htmlspecialchars($summary),
                         'message' => $pre, //$result->getText(),
                         'author' => $wgUser->getId(),
                         'list' => urldecode($wgRequest->getVal('group')),
                         'timestamp' => wfTimestampNow(),
                         'expires' => $expires,
                         'parsed' => 2
                       ),
                       __METHOD__
                      );
					$message_id = $dbw->insertId();
          $dbw->commit();
          $log = new LogPage('intercom');
				  $target = SpecialPage::getTitleFor('intercom',$message_id);
				  $log->addEntry('send',$target, htmlspecialchars($summary),array(urldecode($wgRequest->getVal('group'))),$wgUser);
        }
        $wgOut->redirect($titleObject->getFullURL(''));
      }
		} elseif ($action == 'selectgroups') {
      
      if( wfReadOnly() ) {
        $wgOut->readOnlyPage();
        return;
      }
      
      if ($wgUser->getId() == 0)
      {
        $wgOut->addWikiText(wfMessage('intercom-anon')->text());
      } else {
        $lists = wfMessage('intercom-list')->text();
        $lists = preg_replace("/\*/","",$lists);
        $options = split("\n",$lists);
        $options = preg_replace("/^intercom-urgent$/","_intercom-urgent",$options);
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select('intercom_list','list',
                            array('userid' => $wgUser->getId()),
                            'SpecialIntercom::execute'
                           );
        $checked = array('intercom-urgent' => 1);
        while ($row = $res->fetchRow())
        {
          if ($row['list'] == 'intercom-urgent') {
            $checked['intercom-urgent'] = 0;
          } else {
            $checked[$row['list']] = 1;
          }
        }
        $res->free();
        $wgOut->addHTML(
          Xml::openElement('form', array( 'id' => 'intercomgroups', 'method' => 'post', 'action' => $titleObject->getLocalURL("intercomaction=selectgroups"), ) ) .
          Xml::openElement( 'fieldset' ) .
          Xml::element( 'legend', null, wfMessage( 'intercomgroups-legend' )->text() )
        );
        
        $wgOut->addHTML('<p>' .
                        Xml::check('intercom-urgent',$checked['intercom-urgent'],array('id' => 'intercom-urgent')) .
                        Xml::label(wfMessage('intercom-urgentlist')->text(),'intercom-urgent') .
                        '</p>'
                       );
        
        for ($i=0;$i<count($options);++$i)
        {
          $wgOut->addHTML('<p>' .
                          Xml::check(urlencode($options[$i]),$checked[$options[$i]],array('id' => $options[$i])) .
                          Xml::label($options[$i],$options[$i]) .
                          '</p>'
                         );
        }
        
        $wgOut->addHTML(
          Xml::submitButton( wfMessage( 'intercomgroups-save' )->text(),
                             array('name' => 'intercomgroups_save',
                                   'accesskey' => 's') ) .
          html::hidden('wpEditToken', $wgUser->editToken() ) .
          Xml::closeElement('fieldset') .
          Xml::closeElement('form')
        );
      }
    } elseif ($action =='writenew') {
      if( wfReadOnly() ) {
        $wgOut->readOnlyPage();
        return;
      }
      
      if ($wgUser->getId() == 0)
      {
        $wgOut->addWikiText(wfMessage('intercom-anon')->text());
      } else {
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select('intercom_list','list',
                            array('userid' => $wgUser->getId()),
                            'SpecialIntercom::execute'
                           );
        $groups = array();
        while ($row = $res->fetchRow())
        {
          $groups[] = $row['list'];
        }
        $res->free();
        
        if ($preview)
        {
          global $wgTitle;
          global $wgParser;
          
          $myParser = clone $wgParser;
          $myParserOptions = new ParserOptions();
          $myParserOptions->initialiseFromUser($wgUser);
          $myParserOptions->enableLimitReport(false);
          $pre = $myParser->preSaveTransform($wgRequest->getVal('wpTextbox1',''), $wgTitle, $wgUser , $myParserOptions);
          
          $previewlist = urldecode($wgRequest->getVal('group'));
          $groupname = $previewlist == 'intercom-urgent' ? wfMessage('intercom-urgentlist')->text() : $previewlist;
          $mess = array('id'       => 0,
                        'summary'  => htmlspecialchars($summary),
                        'text'     => $pre,
                        'sender'   => $wgUser->getName(),
                        'senderid' => $wgUser->getId(),
                        'group'    => $groupname,
                        'time'     => wfTimestampNow(),
                        'parsed'   => 2,
                        'realgroup'=> $previewlist,
                       );
          $wgOut->addHTML(Intercom::rendermessage($mess,$wgUser->getId(),false));
          /*$myParser = clone $wgParser;
          $myParserOptions = new ParserOptions();
          $myParserOptions->initialiseFromUser($wgUser);
          $myParserOptions->enableLimitReport(false);
          $pre = $myParser->preSaveTransform($wgRequest->getVal('wpTextbox1',''), $wgTitle, $wgUser , $myParserOptions);
          $result = $myParser->parse($pre, $wgTitle, $myParserOptions);
          $wgOut->addHTML($result->getText());*/
          
        }
        
        $expiryOptionsRaw = wfMessage( 'intercom-expires' )->inContentLanguage()->text();
        $expiryOptions = Xml::option( wfMessage( 'intercom-other' )->text(), 'other' );
        foreach (explode(',', $expiryOptionsRaw) as $option) {
          if ( strpos($option, ":") === false ) $option = "$option:$option";
          list($show, $value) = explode(":", $option);
          $show = htmlspecialchars($show);
          $value = htmlspecialchars($value);
          $expiryOptions .= Xml::option( $show, $value, $expiry === $value ? true : false ) . "\n";
        }
        
        if ( $wgUser->getOption( 'showtoolbar' ) ) {
        	# prepare toolbar for edit buttons
        	$toolbar = EditPage::getEditToolbar();
				} else {
					$toolbar = '';
				}

				$wgOut->addScriptFile( 'edit.js' );

        $wgOut->addHTML(
        	"{$toolbar}" . 
          Xml::openElement('form', array( 'id' => 'intercomedit', 'method' => 'post', 'action' => $titleObject->getLocalURL("intercomaction=writenew"), ) ) .
          Xml::textarea('wpTextbox1',$wgRequest->getVal('wpTextbox1',''),80,25,array('id' => 'wpTextbox1', 'accesskey' => ',' )) .
          "<p>"
        );

				//summary input, copied from EditPage::getSummaryInput
        $inputAttrs = array (
        	'id' => 'wpSummary',
        	'maxlength' => '200',
        	'tabindex' => '1',
        	'size' => 60,
        	'spellcheck' => 'true',
        );
        
        $spanLabelAttrs = array(
        	'class' => $this->missingSummary ? 'mw-summarymissed' : 'mw-summary',
        	'id' => "wpSummaryLabel"
        );
        
        $label = Xml::element( 'label', array( 'for' => $inputAttrs['id'] ), wfMessage('intercom-summary')->text());
        $label = Xml::tags( 'span', $spanLabelAttrs, $label );
        
        $input = Html::input( 'wpSummary', $summary, 'text', $inputAttrs );
        
        $wgOut->addHTML("{$label} {$input}" .
          "</p>" .
          Xml::tags('select',
                    array('id' => 'wpExpiry',
                          'name' => 'wpExpiry',
                          'onchange' => 'intercomExpiryOption()',
                         ),
                    $expiryOptions
                   ) .
          Xml::input( 'wpExpiryOther', 45, $expiryother,
                      array( 'id' => 'wpExpiryOther' ) ) . '&nbsp;' .
          "<select id='group' name='group'>"
        );
        for ($i=0;$i<count($groups);++$i)
        {
          if ($groups[$i] != 'intercom-urgent') {
            # Don't show the urgent group, handled by code below
            $wgOut->addHTML(Xml::option( $groups[$i], urlencode($groups[$i]), $wgRequest->getVal('group') === urlencode($groups[$i]) ? true : false ) . "\n");
          }
        }
        if (in_array('intercom-sendurgent',$wgUser->getRights()) ) { 
          $wgOut->addHTML(Xml::option(  wfMessage('intercom-urgentlist')->text(), 'intercom-urgent', $wgRequest->getVal('group') === 'intercom-urgent' ? true : false ) . "\n");
        }
        $wgOut->addHTML(
          "</select>" . '&nbsp;' .
          ((count($groups) > 0) ? Xml::submitButton( wfMessage( 'intercom-sendmessage' )->text(),
                             array('name' => 'intercom_sendmessage',
                                   'accesskey' => 's') ) : '') . '&nbsp;' .
          Xml::submitButton( wfMessage( 'intercom-preview' )->text(),
                             array('name' => 'intercom_preview',
                                   'accesskey' => 'p') ) . '&nbsp;' .
          html::hidden('wpEditToken', $wgUser->editToken() ) .
					'<div class="mw-editTools">'
				);
				$wgOut->addWikiMsgArray( 'edittools', array(), array( 'content' ) );
				$wgOut->addHTML( '</div>' .
          Xml::closeElement('form')
        );
      }      
    } else {
      # show individual message
      $messid = $wgRequest->getVal('message',$par);
      if ($messid)
      {
        if ($mes = Intercom::getMessage($messid))
        {
          if ($action == 'cancel')
          {
            if (in_array('intercom-sendurgent',$wgUser->getRights()))
            {          
              $wgOut->addWikiText(wfMessage('intercom-cancelconfirm')->text());
              $wgOut->addHTML(
                Xml::openElement('form', array( 'id' => 'intercomcancel', 'method' => 'post', 'action' => $titleObject->getLocalURL("intercomaction=cancel"), ) ) .
                Xml::submitButton( wfMessage( 'intercom-cancelbutton' )->text(),
                                   array('name' => 'intercom_cancelbutton',
                                   'accesskey' => 's') ) .
                html::hidden('message', $messid ) .
                html::hidden('wpEditToken', $wgUser->editToken() ) .
                Xml::closeElement('form')
              );
            } else {
              $wgOut->permissionRequired( 'intercom-sendurgent' );
            }
          } elseif ($action == 'uncancel') {
            if (in_array('intercom-sendurgent',$wgUser->getRights()))
            {          
              $wgOut->addWikiText(wfMessage('intercom-uncancelconfirm')->text());
              $wgOut->addHTML(
                Xml::openElement('form', array( 'id' => 'intercomuncancel', 'method' => 'post', 'action' => $titleObject->getLocalURL("intercomaction=uncancel"), ) ) .
                Xml::submitButton( wfMessage( 'intercom-uncancelbutton' )->text(),
                                   array('name' => 'intercom_uncancelbutton',
                                   'accesskey' => 's') ) .
                html::hidden('message', $messid ) .
                html::hidden('wpEditToken', $wgUser->editToken() ) .
                Xml::closeElement('form')
              );
            } else {
              $wgOut->permissionRequired( 'intercom-sendurgent' );
            }
          }
          $wgOut->addHTML(
            Xml::fieldset(wfMessage('intercom-messageheader')->text(),
            $mes
            ));
        } else {
          $wgOut->addWikiText(wfMessage('intercom-nomessage')->text());
        }
      }
      $sk = RequestContext::getMain()->getSkin();
      $newlink = $sk->link( SpecialPage::getTitleFor( 'intercom' ), wfMessage('intercom-newlink')->text(),array(),array('intercomaction' => 'writenew') , 'known');
      $groupslink = $sk->link( SpecialPage::getTitleFor( 'intercom' ), wfMessage('intercom-groupslink')->text(),array(),array('intercomaction' => 'selectgroups') , 'known');
      $wgOut->addHTML("$newlink<p/>$groupslink<p/>");
      # pager
      $dbr = wfGetDB(DB_SLAVE);
      # get the users lists
      $userid = $wgUser->getId();
      $list = Intercom::getList($dbr,$userid);
    
      if (count($list) != 0)
      {
        $pager = new IntercomPager($list);
        $wgOut->addHTML($pager->getNavigationBar() . '<ul>' .
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
    global $wgUser, $wgLang;
    static $sk=null;
    if( is_null( $sk ) )
    {
      $sk = RequestContext::getMain()->getSkin();
    }
    $user = User::newFromId($row->author);
    $listname = $row->list == 'intercom-urgent' ? wfMessage('intercom-urgentlist')->text() : $row->list;
    $line = wfMsgReplaceArgs( wfMessage('intercom-pager-row')->text(), 
                              array( $user->getName(), $listname, $wgLang->timeanddate( $row->timestamp, true ), 
                              $wgLang->timeanddate( $row->expires, true ), $row->summary ) );
    $readlink = $sk->link( SpecialPage::getTitleFor( 'intercom' ), wfMessage('intercom-pager-readlink')->text(),array(),array('message' => $row->id) , 'known');
    $cancellink = '';
    $uncancellink = '';
    if (in_array('intercom-sendurgent',$wgUser->getRights()) && $row->list == 'intercom-urgent')
    {
      $dbr = wfGetDB(DB_SLAVE);
      $res = $dbr->select('intercom_read','messageid, userid',array('messageid' => $row->id, 'userid' => 0),'IntercomPager::formatRow');
      if ($res->numRows() == 0)
      {
        $cancellink = ' - ' . $sk->link( SpecialPage::getTitleFor( 'intercom' ), wfMessage('intercom-pager-cancellink')->text(),array(),
                              array('intercomaction' => 'cancel', 'message' => $row->id) , 'known');
      } else {
        $uncancellink = ' - ' . $sk->link( SpecialPage::getTitleFor( 'intercom' ), wfMessage('intercom-pager-uncancellink')->text(),array(),
                                array('intercomaction' => 'uncancel', 'message' => $row->id) , 'known');
      }
    }
    return "<li>$line $readlink $cancellink $uncancellink</li>\n";
  }

  function getQueryInfo() {
    return array(
                 'tables' => 'intercom_message',
                 'fields' => 'id, summary, message, author, list, timestamp, expires',
                 'conds'  => array('list' => $this->mlist),
                );
  }

  function getIndexField() {
    return 'id';
  }
}
