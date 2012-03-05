<?php
/***********************************************************************
* Copyright notice
*
* (c) 2005-2011 Christian Technology Ministries International Inc.
* All rights reserved
*
* This file is part of the Web-Empowered Church (WEC)
* (http://WebEmpoweredChurch.org) ministry of Christian Technology Ministries
* International (http://CTMIinc.org). The WEC is developing TYPO3-based
* (http://typo3.org) free software for churches around the world. Our desire
* is to use the Internet to help offer new life through Jesus Christ. Please
* see http://WebEmpoweredChurch.org/Jesus.
*
* You can redistribute this file and/or modify it under the terms of the
* GNU General Public License as published by the Free Software Foundation;
* either version 2 of the License, or (at your option) any later version.
*
* The GNU General Public License can be found at
* http://www.gnu.org/copyleft/gpl.html.
*
* This file is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* This copyright notice MUST APPEAR in all copies of the file!
*************************************************************************/

$ext_path = t3lib_extMgm::extPath('wec_discussion');
require_once(PATH_tslib.'class.tslib_pibase.php');
require_once(PATH_t3lib.'class.t3lib_basicfilefunc.php');
include_once($ext_path.'pi1/class.tx_wecdiscussion_convert.php');

/**
* Plugin 'WEC Discussion Forum' for the 'wec_discussion' extension.
*
* @author Web-Empowered Church Team <devteam@webempoweredchurch.org>
* @package TYPO3
* @subpackage wec_discussion
*
*  "Do not let any unwholesome talk come out of your mouths, but only what
*  is helpful for building others up according to their needs, that it may
*  benefit those who listen." (Ephesians 4:29)
*
*  DESCRIPTION:
*  This extension allows you to add a discussion forum, blog, comments,
*  preview or RSS feed to a page. This discussion system is simpler than a forum and
*  is meant for one page. It can be used by one, a group, or all users.
*
*  All the discussion types support one pages and multiple categories.
*
*  The following are key differences in the discussion types:
*  DISCUSSION: multiple categories / anyone can post or reply
*  BLOG:   only one person or a chosen group can add / anyone (or just users) can add a comment
*  COMMENTS:  anyone can add / just can add a comment (no replies to a message)
*  PREVIEW: see last #N posts written / no post form
*  RSS Feed: see XML feed / no other features
*
*/

class tx_wecdiscussion_pi1 extends tslib_pibase {
	var $cObj;		// The backReference to the mother cObj object set at call time

	var $prefixId = 'tx_wecdiscussion_pi1';		// Same as class name
	var $scriptRelPath = 'pi1/class.tx_wecdiscussion_pi1.php'; // Path to this script relative to the extension dir.
	var $extKey = 'wec_discussion';		// The extension key.

	var $postTable = 'tx_wecdiscussion_post';
	var $groupTable = 'tx_wecdiscussion_group';
	var $categoryTable = 'tx_wecdiscussion_category';

	var $showDay;		// date to be shown -- MMDDYY format
	var $showDayTS;		// date to be shown -- TIMESTAMP format
	var $showCategory; 	// category to be shown

	var $action;		// action to do -- subscribe/unsubscribe/edit/etc.
	var $editMsgNum; 	// message to be editted
	var $filledInVars; 	// filled in for edit message
	var $postvars;		// post vars in wecdiscussion[var] format
	var $submitFormResponse; // response text after submit form
	var $formErrorText; // errors in form text

	var $db_fields;		// database fields (for processing)
	var $marker;		// global marker array
	var $pid_list;		// storage page id(s) list

	var $isAdministrator; 	// if is administrator/moderator
	var $post_userlist; 	// list of users who can post
	var $post_usergroup; 	// the user group who can post
	var $isValidUser; 		// if restricted, if is valid user logged in; if unrestricted, then always valid
	var $showPostForm;  	// if post form is available
	var $showCommentForm;	// if comment form is available
	var $isComment;			// if is a comment or is a reply/post
	var $canPostReply;		// if can post a reply

	var $categoryList; 		// array list of all categories
	var $categoryListByUID; // array list of all categories stored by uid
	var $categoryCount; 	// total categories

	var $freeCap;		// for use by sr_freeCap image captcha
	var $searchFieldList = 'name,email,subject,message,category';
	var $searchWords;	// words that are currently searching for
	var $useCaptcha;	// whether using the captcha or not
	var $easyCaptcha;	// image captcha (captcha) set if loaded

	// for htmlArea in Front-end
	var $RTEObj;
	var $docLarge = 0;
	var $RTEcounter = 0;
	var $formName;
	var $additionalJS_initial = '';// Initial JavaScript to be printed before the form (should be in head, but cannot due to IE6 timing bug)
	var $additionalJS_pre = array();// Additional JavaScript to be printed before the form (works in Mozilla/Firefox when included in head, but not in IE6)
	var $additionalJS_post = array();// Additional JavaScript to be printed after the form
	var $additionalJS_submit = array();// Additional JavaScript to be executed on submit
	var $PA = array(
		'itemFormElName' =>  '',
		'itemFormElValue' => '',
	);
	var $specConf = array(
		'rte_transform' => array(
		'parameters' => array('mode' => 'ts_css')
		)
	);
	var $thisConfig = array();
	var $RTEtypeVal = 'text';

	var $thePidValue;	// pid value(s)
	var $singleMsg;		// save the single message

	/**
	* Init: Initialize the extension. read in Flexform/Typoscript/getvars.
	*
	* @param array  $conf  the TypoScript configuration
	* @return void  No return value needed.
	*/
	function init($conf) {
		//$GLOBALS['TSFE']->set_no_cache();	// don't want to cache this page since it is updated often
		//  $this->pi_USER_INT_obj = 1;   	// configure so caching not expected
		$this->conf['cache']=1;
		$GLOBALS['TSFE']->page_cache_reg1 = 416;
	  	if ((t3lib_div::int_from_ver(TYPO3_version) >= 4003000) && 
		   	(t3lib_cache::isCachingFrameworkInitialized() && TYPO3_UseCachingFramework)) {
		    $GLOBALS['TSFE']->addCacheTags(array('wec_discussion'));
		}

		// ------------------------------------------------------------
		// Initialize vars, structures, arrays, etc.
		// ------------------------------------------------------------
		if (!$this->cObj) $this->cObj = t3lib_div::makeInstance('tslib_cObj');
		$this->conf = $conf;			// TypoScript configuration
		$this->pi_setPiVarDefaults();	// GetPut-parameter configuration
		$this->pi_initPIflexForm();		// Initialize the FlexForms array
		$this->pi_loadLL();				// localized language variables
		$this->templateName = 0;
		$this->isAdministrator = 0;
		$this->action = 0;
		$this->formErrorText = 0;
		$this->post_userlist = 0;
		$this->post_usergroup = 0;

		$this->db_fields = array('uid', 'name', 'email', 'subject', 'message', 'reply_uid', 'toplevel_uid', 'useruid', 'post_datetime', 'category', 'image', 'attachment', 'starttime', 'endtime','ipAddress');
		$this->db_showFields = array('name', 'email', 'subject', 'message', 'category', 'image', 'attachment', 'starttime', 'endtime');

		$this->id = $GLOBALS['TSFE']->id; // current page id

		$this->msgList = array();

		// ----------------------------------------------------------------------------------------
		// Set USER Info->userGroups
		// ----------------------------------------------------------------------------------------
		if ($GLOBALS['TSFE']->loginUser) {
			$this->userID = $GLOBALS['TSFE']->fe_user->user['uid'];
			$this->userName = $GLOBALS['TSFE']->fe_user->user['username'];
			$this->userFirstName = $GLOBALS['TSFE']->fe_user->user['first_name'];
			$this->userGroups = $GLOBALS['TSFE']->fe_user->user['usergroup'];
			if (strlen($this->userFirstName) < 1) $this->userFirstName = $this->userName;
			$lastName = $GLOBALS['TSFE']->fe_user->user['last_name'];
			if (strlen($lastName) > 0)
				$this->userPostName = $this->userFirstName . ' ' . substr($lastName, 0, 1) . '.';
			else
				$this->userPostName = $this->userName;
		} else {
			// no user logged in...
			$this->userID = 0;
			$this->userName = '';
			$this->userFirstName = '';
			$this->userPostName = '';
			$this->userGroups = 0;
		}

		// set the storage PID (currently supports one page...could add recursive or multiple pages later)
		//-------------------------------------------------------------
		$this->config['storagePID'] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'storagePID', 'sDEF');
		if ($this->config['storagePID']) // can specify in flexform
			$this->pid_list = $this->config['storagePID'];
		else if ($this->conf['pid_list']) // or specify in TypoScript
			$this->pid_list = $this->conf['pid_list'];
		else {
			$this->pid_list = $this->id;	// the default is the current page
			if (t3lib_div::_GP('wecdiscussion_inside'))
				$this->pid_list = t3lib_div::_GP('wecdiscussion_inside');
		}

		// read in post & get vars (i.e., tx_wecdiscussion[var1]...
		//------------------
		// if this is not a preview, RSS or archive, then handle incoming vars
		//
		$this->config['type'] = $this->getConfigVal($this, 'type', 's_options');		
		if (($this->config['type'] != 4) && ($this->config['type'] != 6) && ($this->config['type'] != 7)) {
			$this->postvars = t3lib_div::_GP('tx_wecdiscussion');
			// clean up all inputs
			$intvars = array('abs','pg','is_reply','reply_uid','toplevel_uid','single','ispreview','showreply');
			foreach ($intvars as $ivar) {
				if (isset($this->postvars[$ivar])) {
					$this->postvars[$ivar] = (int) $this->postvars[$ivar];
				}
			}
			$strvars = array('searchwords','name','subject','email','category');
			foreach ($strvars as $svar) {
				if (isset($this->postvars[$svar])) {
					$this->postvars[$svar] = htmlspecialchars($this->postvars[$svar]);
				}
			}
		}

		// ------------------------------------------------------------
		// Load in all flexform/Typoscript values
		// ------------------------------------------------------------
		$templateflex_file = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'template_file', 'sDEF');
		$this->templateCode = $this->cObj->fileResource($templateflex_file ? "uploads/tx_wecdiscussion/".$templateflex_file: $this->conf['templateFile']);

		// MAIN
		$this->config['title'] = $this->getConfigVal($this, 'title', 'sDEF');
		$this->config['restricted_userlist'] = trim($this->getConfigVal($this, 'restricted_userlist', 'sDEF'));
		if (!empty($this->config['restricted_userlist'])) {
			$this->post_userlist = t3lib_div::trimExplode(',', $this->config['restricted_userlist']);
		}
		$this->config['restricted_usergroup'] = trim($this->getConfigVal($this, 'restricted_usergroup', 'sDEF'));

		// DISPLAY OPTIONS
		$this->config['display_amount'] = $this->getConfigVal($this, 'display_amount', 's_options');
		$this->config['only_comments'] = $this->getConfigVal($this, 'only_comments', 's_options');
		$this->config['reply_is_comment'] = $this->getConfigVal($this, 'reply_is_comment', 's_options');
		$this->config['reply_level'] = $this->getConfigVal($this, 'reply_level', 's_options');
		$this->config['allow_toggle_commentsreply'] = $this->getConfigVal($this, 'allow_toggle_commentsreply', 's_options');
		$this->config['show_sidebar_actionbar'] = $this->getConfigVal($this, 'show_sidebar_actionbar', 's_options');
		$this->config['entry_look'] = $this->getConfigVal($this, 'entry_look', 's_options');
		$this->config['allow_search'] = $this->getConfigVal($this, 'allow_search', 's_options');
		$this->config['show_archive'] = $this->getConfigVal($this, 'show_archive', 's_options');
		$this->config['show_chooseCat'] = $this->getConfigVal($this, 'show_chooseCat', 's_options');
		$this->config['can_create_category'] = $this->getConfigVal($this, 'can_create_category', 's_options');
		$this->config['display_characters_limit'] = $this->getConfigVal($this, 'display_characters_limit', 's_options');
		$this->config['previewRSS_backPID'] = $this->getConfigVal($this, 'preview_backPID', 's_options');
		$this->config['num_previewRSS_items'] = $this->getConfigVal($this, 'num_preview_items', 's_options');
		$this->config['preview_length'] = $this->getConfigVal($this, 'preview_length', 's_options');
		$this->config['preview_allow_replies'] = $this->getConfigVal($this, 'preview_allow_replies', 's_options');
		$this->config['num_per_page'] = $this->getConfigVal($this, 'num_per_page', 's_options');

		// CONTROL OPTIONS
		$this->config['is_moderated'] = $this->getConfigVal($this, 'is_moderated', 's_control');
		$this->config['moderate_exclude'] = $this->getConfigVal($this, 'moderate_exclude', 's_control');
		$this->config['require_login_to_post'] = $this->getConfigVal($this, 'login_for_posting', 's_control');
		$this->config['require_login_to_reply'] = $this->getConfigVal($this, 'login_for_reply', 's_control');
		$this->config['require_login_to_subscribe'] = $this->getConfigVal($this, 'login_for_subscribing', 's_control');
		$this->config['email_author_replies'] = $this->getConfigVal($this, 'email_author_replies', 's_control');
		$this->config['show_report_abuse_button'] = $this->getConfigVal($this, 'show_report_abuse_button', 's_control');
		$this->config['can_subscribe'] = $this->getConfigVal($this, 'can_subscribe', 's_control');
		$this->config['allow_preview_before_post'] = $this->getConfigVal($this, 'allow_preview_before_post', 's_control');
		$this->config['allow_single_view'] = $this->getConfigVal($this, 'allow_single_view', 's_control');
		// if not been set (from previous plugin version), then set to default of 1
		if (!is_array($this->cObj->data['pi_flexform']['data']['s_control']['lDEF']) || !array_key_exists('allow_single_view',$this->cObj->data['pi_flexform']['data']['s_control']['lDEF']))
		 	$this->config['allow_single_view'] = 1;

		// SPAM
		$this->config['html_tags_allowed'] = $this->getConfigVal($this, 'html_tags_allowed', 's_antispam');
		$this->config['use_captcha'] = $this->getConfigVal($this, 'use_captcha', 's_antispam');
		$this->config['use_text_captcha'] = $this->getConfigVal($this, 'use_text_captcha', 's_antispam');
		$this->config['numlinks_allowed'] = $this->getConfigVal($this, 'numlinks_allowed', 's_antispam');
		$this->config['filter_wordlist'] = $this->getConfigVal($this, 'filter_wordlist', 's_antispam');
		$this->config['filter_word_handling'] = $this->getConfigVal($this, 'filter_word_handling', 's_antispam');
		$this->config['only_check_comments'] = $this->getConfigVal($this, 'only_check_comments', 's_antispam');
		$this->config['captcha_only_once'] = $this->getConfigVal($this, 'captcha_only_once', 's_antispam');

		// REQUIRED & DISPLAY FIELDS
		$this->config['required_fields'] = $this->getConfigVal($this, 'required_fields', 's_fields');
		if (!empty($this->config['required_fields'])) {
			$this->config['required_fields'] = t3lib_div::trimExplode(',', $this->config['required_fields']);
		}
		else
			$this->config['required_fields'] = array('message');
		// display
		$this->config['display_fields'] = $this->getConfigVal($this, 'display_fields', 's_fields');
		if (!empty($this->config['display_fields']))
			$this->config['display_fields'] = t3lib_div::trimExplode(',', $this->config['display_fields']);
		else // use default fields...
			$this->config['display_fields'] = array('name', 'email', 'subject', 'message', 'category');
		// if required is not in display, then remove from required
		if (is_array($this->config['display_fields'])) {
		  for ($k = count($this->config['required_fields']) - 1; $k >= 0; $k--) {
			if (!in_array($this->config['required_fields'][$k],$this->config['display_fields']) && strcmp($this->config['required_fields'][$k],"message")) {
				unset($this->config['required_fields'][$k]);
			}
		  }
		}

		// ADMIN
		$this->config['administrator_userlist'] = $this->getConfigVal($this, 'administrator_group', 's_administrator');
		$this->config['administrator_usergroup'] = $this->getConfigVal($this, 'administrator_usergroup', 's_administrator');
		$this->config['contact_name'] = $this->getConfigVal($this, 'contact_name', 's_administrator');
		$this->config['contact_email'] = $this->getConfigVal($this, 'contact_email', 's_administrator');
		$this->config['email_admin_posts'] = $this->getConfigVal($this, 'email_admin_posts', 's_administrator');
		$this->config['notify_email'] = $this->getConfigVal($this, 'notify_email', 's_administrator');

		// TEXT
		$this->config['subscribe_header'] = $this->getConfigVal($this, 'subscribe_header', 's_text');
		$this->config['subscriber_emailHeader'] = $this->getConfigVal($this, 'subscriber_emailHeader', 's_text');
		$this->config['subscriber_emailFooter'] = $this->getConfigVal($this, 'subscriber_emailFooter', 's_text');

		// SET administrator
		//---------------------------------------------------
		if ($this->userID && ($admins = $this->config['administrator_userlist'])) {
			$adminList = t3lib_div::trimExplode(',', $admins);
			foreach ($adminList as $thisAdmin) {
				if (($thisAdmin == $this->userID) || ($thisAdmin == $this->userName)) {
					$this->isAdministrator = 1;
					break;
				}
			}
		}
		// check if this user belongs to admin usergroup
		$adminUG = $this->config['administrator_usergroup'];
		if ($this->userGroups && $adminUG) {
			// put my groups in an array
			$myGroupArray = t3lib_div::trimExplode(',', $this->userGroups);
			// determine if my groups matches any admin usergroups
			$adminGroupArray = t3lib_div::trimExplode(',', $adminUG);
			foreach ($adminGroupArray as $adminGrp) {
				if (in_array($adminGrp,$myGroupArray)) {
					$this->isAdministrator = 1;
					break;
				}
			}
		}		
		
		// Determine if a user who can post
		//-----------------------------------------------------------------------
		$this->isValidUser = 0;

		// check if user on user list
		for ($i = 0; $i < sizeof($this->post_userlist); $i++) {
			$userident = $this->post_userlist[$i];
			if (($this->userID && ((int) $userident == (int) $this->userID)) || (strcasecmp((string) $userident, $this->userName) == 0)) {
				$this->isValidUser = 1;
				break;
			}
		}
		// check if user in usergroup
		if (($restrictedGroup = $this->config['restricted_usergroup']) && $this->userID && $this->userGroups) {
			$restrictedGroupArray = t3lib_div::trimExplode(',',$restrictedGroup);
			$allGroupArray = t3lib_div::trimExplode(',',$this->userGroups);
			if (count($allGroupArray) && count(array_intersect($restrictedGroupArray, $allGroupArray))) {
				$this->isValidUser = 1;
			}
		}

		// if no restricted users or usergroups, then valid
		if (empty($this->config['restricted_userlist']) && empty($this->config['restricted_usergroup']))
			$this->isValidUser = 1;

		// administrator is always valid and can do anything
		if ($this->isAdministrator)
			$this->isValidUser = 1;

		// determine if captcha needed
		// if this is not an edit, is a reply and check comments or not a logged in user
		$this->useCaptcha = !$editID && (($this->postvars['reply_uid'] > 0) || ($this->postvars['isreply'] > 0) || !$this->config['only_check_comments'] || !$this->userID);
		if ($this->useCaptcha && isset($_COOKIE[$this->extKey."_".$this->pid_list.'_captcha']))
			$this->useCaptcha = false;
		// Add captcha if loaded
		$this->freeCap = 0;
		if ($this->useCaptcha && $this->config['use_captcha'] && t3lib_extMgm::isLoaded('sr_freecap')) {
			require_once(t3lib_extMgm::extPath('sr_freecap') . 'pi2/class.tx_srfreecap_pi2.php');
			$this->freeCap = t3lib_div::makeInstance('tx_srfreecap_pi2');
		}
		else if ($this->config['use_captcha'] && t3lib_extMgm::isLoaded('captcha')) {
			$this->easyCaptcha = true;
		}

		// setup RTE if enabled
		if(!$this->RTEObj && $this->conf['RTEenabled'] && t3lib_extMgm::isLoaded('rtehtmlarea')) {
			require_once(t3lib_extMgm::extPath('rtehtmlarea').'pi2/class.tx_rtehtmlarea_pi2.php');
			$this->RTEObj = t3lib_div::makeInstance('tx_rtehtmlarea_pi2');
		} elseif (!$this->RTEObj && $this->conf['RTEenabled'] && t3lib_extMgm::isLoaded('tinymce_rte')) {
			require_once(t3lib_extMgm::extPath('tinymce_rte').'pi1/class.tx_tinymce_rte_pi1.php');
			$this->RTEObj = t3lib_div::makeInstance('tx_tinymce_rte_pi1');
		} else {
			$this->RTEObj = 0;
		}

		// if setup restricted user or usergroups, then set require_login_to_post (because this is likely their intention)
		if (!empty($this->config['restricted_userlist']) || !empty($this->config['restricted_usergroup'])) {
			$this->config['require_login_to_post'] = 1;
		}

		// SET UP DEFAULT VALUES FOR GIVEN TYPE
		//----------------------------------------------------------------------------
		switch ($this->config['type']) {
			case 1: // discussion
				$this->config['restricted_userlist'] = 0;
				// user list is anyone
				$this->config['reply_level'] = 3;
				// allow post & replies (reply_level = 3)
				$this->config['reply_is_comment'] = 0;
				$this->config['only_comments'] = 0;

				// anonymous OR reg. users can post/reply (only turn off if NO user or usergroups set)
				if (empty($this->config['restricted_userlist']) && empty($this->config['restricted_usergroup'])) {
					$this->config['require_login_to_post'] = 0;
				}
				$this->config['require_login_to_reply'] = 0;
			break;

			case 2: // BLOG
				// user list is restricted
				$this->config['reply_level'] = 1;
				$this->config['reply_is_comment'] = 1;
				$this->config['only_comments'] = 0;

				// only valid users can post
				$this->config['require_login_to_post'] = 1;
				$this->config['require_login_to_reply'] = 0;
			break;

			case 3: // COMMENTS
				$this->config['restricted_userlist'] = 0; // user list is anyone
				$this->config['reply_level'] = 0;
				$this->config['reply_is_comment'] = 1;
				$this->config['only_comments'] = 1;

				if (empty($this->config['restricted_userlist']) && empty($this->config['restricted_usergroup'])) {
					$this->config['require_login_to_post'] = 0;
				}
				$this->config['require_login_to_reply'] = 0;
			break;

			case 4: // PREVIEW
				// just show X number of items
				// no need to set config vars...because all are ignored
			break;

			case 5:
				// CUSTOM
				// no overrides...whatever they want to set it as, they can.
			break;
		}

		// HANDLE adding email author replies as a field
		if ($this->config['email_author_replies']) {
			$this->db_fields[] = 'email_author_replies';
			$this->db_showFields[] = 'email_author_replies';
		}

		// *************************************************************************
		// Check INCOMING POST Vars
		// *************************************************************************

		// handle if pass in 'single'
		$getvars = t3lib_div::_GET('tx_wecdiscussion');
		if (!$this->postvars['single'] && $getvars['single']) {
			$this->postvars['single'] = $getvars['single'];
		}

		// ----------------------------------------------------------------------------------------
		// Handle Passed-In values
		// passed in: &tx_wecdiscussion[show_date] (date in MMDDYY format) and &tx_wecdiscussion[show_cat] (category ID)
		// ----------------------------------------------------------------------------------------
		// read, validate, and then setup curDay and curDayTS
		$show_dateTime = $this->postvars['show_date'];
		if (!is_numeric($show_dateTime) && $show_dateTime < 0)
			unset($show_dateTime);
		if (!isset($show_dateTime) || $show_dateTime == 0 || (strlen($show_dateTime) != 6) || !is_numeric($show_dateTime)) {
			$this->showDayTS = mktime(); // return today() if not set or in correct format
			$this->showDay = date('mdy');
		} else {
			$this->showDay = $show_dateTime;
			$this->showDayTS = mktime(23, 58, 0, substr($show_dateTime, 0, 2), substr($show_dateTime, 2, 2), substr($show_dateTime, 4, 2));
		}

		// read, validate, and then setup showCategory
		$curCategory = (int) $this->postvars['show_cat'];
		if (!isset($curCategory) || !is_numeric($curCategory) || $curCategory < 0)
			$curCategory = 0;
		$this->showCategory = $curCategory;

		//*************************************************************************
		//
		// LOAD IN CATEGORIES
		//*************************************************************************
		$where = 'pid IN(' . $GLOBALS['TYPO3_DB']->cleanIntList($this->pid_list) . ')';
		$where .= $this->cObj->enableFields($this->categoryTable);
		// handle languages
		$lang = ($l = $GLOBALS['TSFE']->sys_language_uid) ? $l : '0,-1';
		$where .= ' AND sys_language_uid IN ('.$lang.') ';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->categoryTable, $where, '', 'sort_order');
		if (mysql_error()) t3lib_div::debug(array(mysql_error(), $res));
		$this->categoryCount = 0;
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$this->categoryList[$this->categoryCount]['name'] = $row['name'];
			$this->categoryList[$this->categoryCount]['image'] = $row['image'];
			$this->categoryList[$this->categoryCount]['uid'] = $row['uid'];
			$this->categoryListByUID[$row['uid']] = $row['name'];
			$this->categoryCount++;
		}
		if ($this->showCategory != 0) {
			// check for valid category UID
			$found = false;
			for ($i = 0; $i < $this->categoryCount; $i++) {
				if ($this->showCategory == $this->categoryList[$i]['uid'])
					$found = true;
			}
			if (!$found)
				$this->showCategory = 0;
		}

		// POSTING/REPLYING TO FORUM OR EDITING EXISTING MESSAGE...
		//----------------------------------------------------------

		if ($this->postvars['reply_uid'] < 0) $this->postvars['reply_uid'] = 0;
		$replyForm = $this->postvars['replyForm'];
		$canPost = (($this->config['require_login_to_post'] == 0) || ($this->userID != 0)) && $this->isValidUser;
		// if can post and not comments or not single view with reply level = 0, then show the post form
		$this->showPostForm = !$this->config['only_comments'] && $canPost;
		if ($canPost && $this->postvars['single'] && ($this->config['reply_level'] == 0))
			$this->showPostForm = false;
		// show comment form if can comment (only comments or reply is a comment)
		$this->canPostReply = ($this->config['require_login_to_reply'] == 0) || ($this->userID != 0);
		$this->isComment = $this->config['only_comments'] || $this->config['reply_is_comment'];
		$this->showCommentForm = $this->canPostReply && $this->isComment && ($this->postvars['single'] || ($this->config['reply_level']==0) || ($replyForm == 2) || ($this->config['allow_single_view'] == 0));

		// if incoming post/comment form, then check if can post, then post it
		if (((($replyForm == 1) && $this->showPostForm) ||
 		    (($replyForm == 2) && $this->showCommentForm && (($this->postvars['reply_uid'] > 0) || $this->config['only_comments'])))
		   && (($this->config['type'] < 4) || ($this->config['type'] == 5))
		   && !t3lib_div::_GP('ignore')) {
			// if preview before post, then handle it...
			if (t3lib_div::_POST('ForumPreviewBeforePost')) {
				// post to forum (but moderated=99)
				$this->postToForum($this->postvars);
			}
			// just a normal post
			else {
				$this->postToForum($this->postvars);
			}
		}

		if ($this->postvars['ispreview']) {
			// show in single view with edit containing msg(so can keep editing)
			if ($this->canEditPost($this->postvars['ispreview'])) {
				$this->singleMsg = $this->filledInVars;
				$this->singleMsg['moderationQueue'] = 0;
			}
			// allow to post or edit again
		}

		// handle click on subscribing/unsubscribing
		if (($thisSubAction = $this->postvars['sub']) != 0) {
			// if coming from unsubscribing link in email
			if (($thisSubAction == 2) && ($thisSubEmail = $this->postvars['email'])) {
				$this->action = 'unsubscribe';
				$this->unsubscribeFromGroup($thisSubEmail);
			}
			// make sure can subscribe and then allow
			else if ($this->config['can_subscribe'] && (!$this->config['require_login_to_subscribe'] || $this->isAdministrator || $this->userID))
				$this->action = 'subscribe';
		}

		// handle if reporting abuse
		if ($this->postvars['abs'] != 0 && $this->config['show_report_abuse_button']) {
			$this->action = 'report_abuse';
		}

		// SHOWING ARCHIVE
		//----------------------------------------------------------
		if ($this->postvars['archive']) {
			$this->config['display_amount'] = 2;
		}

		// SEARCH WORDS DEFINED...
		//----------------------------------------------------------
		if ($sw = $this->postvars['searchwords'])
			$this->searchWords = $sw;

		// DELETING POST FROM FORUM...
		//----------------------------------------------------------
		if ($this->postvars['deleteMsg'])
			$this->deletePost((int)$this->postvars['deleteMsg']);

		if ($this->postvars['deleteAllMsg'])
			$this->deletePost((int)$this->postvars['deleteAllMsg'],true);

		// EDITTING POST FROM FORUM...
		//----------------------------------------------------------
		if (($edMsg = $this->postvars['editMsg']) || ($edMsg = $this->postvars['edit_msg'])) {
			$this->editMsgNum = (int) $edMsg;
			if ($this->canEditPost($this->editMsgNum))
				$this->action = 'edit';
			else
				$this->editMsgNum = 0;
		}

		// SUBMIT SUBSCRIBE REQUEST
		//----------------------------------------------------------
		if (t3lib_div::_GP('submitsubscribe') && ($this->config['type'] != 4) && ($this->config['type'] != 7)) {
			if (!$this->subscribeToForum($this->postvars['email'],$this->postvars['name'])) {
				// if unsuccessful, then go back to form
				$this->action = 'subscribe';
			}
		}

		// SUBMIT UNSUBSCRIBE REQUEST
		//----------------------------------------------------------
		if (t3lib_div::_GP('submitunsubscribe') && ($this->config['type'] != 4) && ($this->config['type'] != 7)) {
			if (!$this->unsubscribeFromGroup($this->postvars['email'])) {
				// if unsuccessful, then go back to form
				$this->action = 'subscribe';
			}
		}

		// SUBMIT REPORT ABUSE REPORT
		//----------------------------------------------------------
		if (t3lib_div::_GP('submitabuse') && $this->config['show_report_abuse_button'] && ($this->config['type'] != 4) && ($this->config['type'] != 7)) {
			if (!$this->sendReportAbuse($this->postvars)) {
				$this->action = 'report_abuse';
			}
		}

		// MODERATE?
		//----------------------------------------------------------
		if ($this->postvars['moderate']) {
			if ($this->isAdministrator) {
				$this->action = 'moderate';
			}
			// if not logged in, give a warning
			else if (!$this->userID) {
				$this->submitFormResponse = $this->pi_getLL('login_to_moderate', 'You need to be logged in to moderate. ');
				if ((int)$this->conf['loginPID']) {
					$this->submitFormResponse .= ' <a class="button" href="' . $this->pi_getPageLink($this->conf['loginPID']) . '"> ' . $this->pi_getLL('goto_login', ' Goto Login ') . '</a>';
				}
			}
		}

		// if moderated form being sent, then process the moderated now
		if ($this->postvars['processmoderated'] && $this->isAdministrator) {
			$this->processModerated(t3lib_div::_POST());
		}

		// RSS FEED?
		//----------------------------------------------------------
		if (($this->config['type'] == 6) ||
		    (($GLOBALS["TSFE"]->type == 224) && ($this->conf['rssFeedOn'] == 1))) {  // RSS FEED
			$this->action = 'rss';

		}

		// allow RSS feed to be "discovered" by feed readers
		// if set rssFeedOn and NOT doing an RSS feed
		if (($this->conf['rssFeedOn'] == 1) && (($rssLink = $this->conf['xml.']['rss.']['link']) || strcmp((string) $this->action,'rss'))) {
			if (strpos($rssLink,'http:') === FALSE) {
				$urlParam['type'] = 224;
				$urlParam['sp'] = $this->pid_list;
				$rssURL = $this->getAbsoluteURL($this->id,$urlParam,TRUE);
			}
			else {
				$rssURL = $rssLink;
			}
			$rssTitle =  $this->conf['xml.']['rss.']['channel_title'] ? $this->conf['xml.']['rss.']['channel_title'] : ($this->config['title'] ? $this->config['title'] : 'RSS 2.0');
			$GLOBALS['TSFE']->additionalHeaderData['tx_wecdiscussion'] = '<link rel="alternate" type="application/rss+xml" title="'.$rssTitle.'" href="'.$rssURL.'" />';
		}

		if ($this->config['type'] == 4) // force preview
			$this->action = 'preview';

		if ($this->config['type'] == 7) // only show archive
			$this->action = 'archive';

		// Set CSS file(s), if exists
		if (($this->conf['isOldTemplate'] == 0) && t3lib_extMgm::isLoaded('wec_styles')) {
			require_once(t3lib_extMgm::extPath('wec_styles') . 'class.tx_wecstyles_lib.php');
			$wecStylesLib = t3lib_div::makeInstance('tx_wecstyles_lib');
			$wecStylesLib->includePluginCSS();
		}
		else if ($baseCSSFile = $this->conf['baseCSSFile']) {
			$fileList = array(t3lib_div::getFileAbsFileName($baseCSSFile));
			$fileList = t3lib_div::removePrefixPathFromList($fileList,PATH_site);
			$GLOBALS['TSFE']->additionalHeaderData['wecdiscussion_basecss'] = '<link type="text/css" rel="stylesheet" href="'.$fileList[0].'" />';
		}
		if ($cssFile = $this->conf['cssFile']) {
			$fileList = array(t3lib_div::getFileAbsFileName($cssFile));
			$fileList = t3lib_div::removePrefixPathFromList($fileList,PATH_site);
			$GLOBALS['TSFE']->additionalHeaderData['wecdiscussion_css'] = '<link type="text/css" rel="stylesheet" href="'.$fileList[0].'" />';
		}

		// include extra CSS file for post entries if it is set
		if (($this->conf['isOldTemplate'] == 0) && ($this->config['entry_look'] > 0)) {
			// @todo -- grab from same folder as cssFile. That way, you can put in own folder
			$entryCSSFile = 'EXT:wec_discussion/template/wecdiscussion-entry' . $this->config['entry_look'] . '.css';
			$fileList = array(t3lib_div::getFileAbsFileName($entryCSSFile));
			$fileList = t3lib_div::removePrefixPathFromList($fileList,PATH_site);
			$GLOBALS['TSFE']->additionalHeaderData['wecdiscussion_entrycss'] = '<link type="text/css" rel="stylesheet" href="'.$fileList[0].'" />';
		}

		// do timtab conversion?
		$ttconvertNum = $this->conf['ttconvert'] ? $this->conf['ttconvert'] : 997;
		if (!$this->action && (t3lib_div::_GET('ttconvert') == $ttconvertNum))
			$this->action = 'ttconvert';

	}

	/**
	* Main function: calls the init() function and decides by the given actions which functions to display content
	*
	* @param string  $content : function output is added to this
	* @param array  $conf : TypoScript configuration array
	* @return string  $content: complete content generated by the plugin
	*/
	function main($content, $conf) {
		$this->init($conf);
	    if ($conf['isLoaded'] != 'yes')
	      return $this->pi_getLL('errorIncludeStatic');

		$content = '';

		// do the given action set in init()
		$this->action = (string) $this->action;
		switch ($this->action) {
			case 'edit':
				$content .= $this->displayReplyForm($this->editMsgNum);
				break;

			case 'subscribe':
				$content .= $this->displaySubscribeForm();
				break;

			case 'report_abuse':
				$content .= $this->displayAbuseForm();
				break;

			case 'moderate':
				$content .= $this->moderateMessages();
				break;

			case 'preview':
				$content .= $this->displayPreview();
				break;

			case 'rss':
				$content .= $this->displayRSSFeed();
				return $content;
				break;

			case 'archive':
				$content .= $this->displayArchive($this->showDateTS, 3);
				return $content;
				break;

			case 'ttconvert':
				$content = $this->adminConvert();
				return $content;
				break;

			default:
				$content .= $this->displayMain();
		}

		return $this->pi_wrapInBaseClass($content);
	}

	/**
	* Displays main content: show the posts and reply form
	*
	* @return string  $content: complete content generated by the plugin
	*/
	function displayMain() {
		//
		// Build each piece and then display
		//-------------------------------------------------------------------------------
		$subpartMarker = array();

		// now read in the part of the template file with the PAGE subtemplatename
		$template = $this->cObj->getSubpart($this->templateCode, '###TEMPLATE_PAGE###');

		// generate the interface
		if ($this->config['title'])
			$this->marker['###TITLE###'] = $this->config['title'];
		else
			$subpartMarker['###SHOW_TITLE###'] = '';
		if ($this->submitFormResponse)
			$this->marker['###RESPONSE_MSG_TEXT###'] = $this->submitFormResponse;
		else
			$subpartMarker['###SHOW_RESPONSE_MSG###'] = '';

		// do not show sidebar?
		if (($this->config['show_sidebar_actionbar'] == 1) || ($this->config['show_sidebar_actionbar'] == 3)) {
			$subpartMarker['###SHOW_SIDEBAR###'] = '';
		}
		// do not show actionbar?
		if (($this->config['show_sidebar_actionbar'] == 0) || ($this->config['show_sidebar_actionbar'] == 3)) {
			$subpartMarker['###SHOW_ACTIONBAR###'] = '';
		}
		// do not show either
		if ($this->config['show_sidebar_actionbar'] != 3) {
			$subpartMarker['###SHOW_NOBAR###'] = '';
		}

		if ($this->config['can_subscribe'] && (!$this->config['require_login_to_subscribe'] || $this->isAdministrator || $this->userID)) {
			$paramArray['tx_wecdiscussion[sub]'] = 1;
			$subscribeURL = $this->pi_getPageLink($this->id, '', $paramArray);
			if ($this->conf['isOldTemplate'] == 1) {
				$this->marker['###SUBSCRIBE_BTN###'] = '<a href="'.$subscribeURL.'">' . $this->pi_getLL('subscribe_btn', 'Subscribe') . '</a>';
			}
			else {
				$this->marker['###SUBSCRIBE_BTN###'] = '<a class="button" href="'.$subscribeURL.'"><span class="label subscribeIcon">' . $this->pi_getLL('subscribe_btn', 'Subscribe') . '</span></a>';
			}
		}
		else
			$subpartMarker['###SHOW_SUBSCRIBE_BTN##']  = '';

		if ($this->isAdministrator && $this->config['is_moderated']) {
			$paramArray = t3lib_div::_GET(); // get current vars and then add...
			unset($paramArray['tx_wecdiscussion']['edit_msg']);
			unset($paramArray['tx_wecdiscussion']['editMsg']);
			unset($paramArray['tx_wecdiscussion']['processmoderated']);
			$paramArray['tx_wecdiscussion']['moderate'] = 1;
			$moderateURL = $this->pi_getPageLink($this->id, '', $paramArray);
			if ($this->conf['isOldTemplate'] == 1) {
				$this->marker['###MODERATE_BTN###'] = '<a href="'.$moderateURL.'">'.$this->pi_getLL('moderate_btn', 'Moderate').'</a>';
			}
			else {
				$this->marker['###MODERATE_BTN###'] = '<a class="button" href="'.$moderateURL.'"><span class="label adminIcon">'.$this->pi_getLL('moderate_btn', 'Moderate').'</span></a>';
			}
		}
		else
			$subpartMarker['###SHOW_MODERATE_BTN##']  = '';

		// show 'post message' button at top if form is available
		if ($this->showPostForm && !$this->postvars['single'])
			$this->marker['###POST_YOUR_MESSAGE_BTN###'] = $this->pi_getLL('post_your_message', 'Post Your Message');
		else
			$subpartMarker['###SHOW_POST_BTN###'] = "";

		// show 'add comment' button at top if is only comments and form is available
		if ($this->showCommentForm && $this->config['only_comments'])
			$this->marker['###POST_YOUR_COMMENT_BTN###'] = $this->pi_getLL('add_comments','Add Your Comment');
		else
			$subpartMarker['###SHOW_COMMENT_BTN###'] = "";

		if (!$this->submitFormResponse && !$this->showPostForm && !$this->isComment && !$this->post_userlist && !$this->post_usergroup) {
			$this->marker['###RESPONSE_MSG_TEXT###'] = $this->pi_getLL('login_to_comment','<span style="color:black">You must login to post a message.</span>');
			if ((int)$this->conf['loginPID']) {
				$this->marker['###RESPONSE_MSG_TEXT###'] .= ' <a class="button" href="' . $this->pi_getPageLink($this->conf['loginPID']) . '"> ' . $this->pi_getLL('goto_login', ' Goto Login ') . '</a>';
			}
		}

		if ($this->postvars['single']) {
			$goURL = $this->pi_getPageLink($this->id,'',$urlParams);
			if ($this->conf['isOldTemplate'] == 1) {
				$this->marker['###DISPLAY_VIEW_ALL_BUTTON###'] = '<span class="tx-wecdiscussion-button"><a id="goback" href="'.$goURL.'">' . $this->pi_getLL('viewall_button','View All Messages') . '</a></span>';
			}
			else {
				$this->marker['###DISPLAY_VIEW_ALL_BUTTON###'] = '<a class="button" id="goback" href="'.$goURL.'"><span class="label">' . $this->pi_getLL('viewall_button','View All Messages') . '</span></a>';
			}
		}
		else
			$this->marker['###DISPLAY_VIEW_ALL_BUTTON###'] = '';

		// put up header for archive and category view
		$showHeader = "";
		if ($arch = $this->postvars['archive']) {
			$archDate = $this->postvars['show_date'];
			$archMonth = substr($archDate,0,2);
//			$showHeader .= $this->pi_getLL('header_archive','Archive for ') . date($this->pi_getLL('archive_dateformat','F Y'),mktime(0,0,0,substr($archDate,0,2),substr($archDate,2,2),substr($archDate,4,2)));
			$showHeader .= $this->pi_getLL('header_archive','Archive for ') . $this->getStrftime($this->pi_getLL('archive_date_format','%B %Y'),mktime(0,0,0,substr($archDate,0,2),substr($archDate,2,2),substr($archDate,4,2)));
		}
		if ($cat = $this->postvars['show_cat']) {
			if (strlen($showHeader)) $showHeader .= $this->pi_getLL('header_separator',' / ');
			$showHeader .= $this->categoryListByUID[$cat] . ' '.$this->pi_getLL('header_category','category');
		}
		if (strlen($showHeader))
			$showHeader = $this->pi_getLL('header_tag_start','<h2>') . $showHeader . $this->pi_getLL('header_tag_end','</h2>');
		$this->marker['###DISPLAY_HEADER###'] = $showHeader;

		// generate main content
		$this->displayForum($this->showDayTS, $this->showCategory); // fills in ###DISPLAY_POSTS and ###DISPLAY_REPLIES

		// clear pagenum if not there
		if (!strlen($this->marker['###PAGE_PREV###']) && !strlen($this->marker['###PAGE_NEXT###']))  {
			$subpartMarker['###SHOW_PAGENUM###'] = '';
		}

		// set page title for single view
		if ($this->postvars['single'] && $this->singleMsg && strlen($singleSubj = $this->singleMsg['subject'])) {
			if (is_array($this->conf['single_view.']) && ($singlePageTitle = $this->conf['single_view.']['substitutePageTitle'])) {
				if ($singlePageTitle == 1) {  // replace
					$GLOBALS['TSFE']->page['title'] = $singleSubj;
					$GLOBALS['TSFE']->indexedDocTitle = $singleSubj;
				}
				else if ($singlePageTitle == 2) { // append
					$GLOBALS['TSFE']->page['title'] .= $this->pi_getLL('single_view_title_separator',': ') . $singleSubj;
					$GLOBALS['TSFE']->indexedDocTitle .= $this->pi_getLL('single_view_title_separator',': ') . $singleSubj;
				}
			}
		}

		if ($this->config['show_archive']) {
			// this must be after displayForum because expects certain vals set???
			if (strstr($template,'###DISPLAY_ARCHIVE###'))
				$subpartMarker['###SHOW_ARCHIVE###'] = $this->displayArchive($this->showDayTS);
			if (strstr($template,'###DISPLAY_ARCHIVE_DROPDOWN###')) {
				$this->marker['###ARCHIVE_HEADER###'] = $this->pi_getLL('archive_header', 'Archive:');
				$this->marker['###DISPLAY_ARCHIVE_DROPDOWN###'] = $this->displayArchive($this->showDayTS, 2);
			}
		}
		else {
			$subpartMarker['###SHOW_ARCHIVE###'] = '';
			$subpartMarker['###SHOW_ARCHIVE_DROPDOWN###'] = '';
		}

		// only show reply form if logged in (and valid user if a userlist) OR if don't care if logged in AND this is not a comment only
		if ($this->showPostForm && (!$this->postvars['single'] || !$this->showCommentForm) )
			$this->marker['###DISPLAY_REPLYFORM###'] = $this->displayReplyForm();

		// allow to have separate comment form (this can be hidden)
		if ($this->showCommentForm)
			$this->marker['###DISPLAY_COMMENTFORM###'] = $this->displayReplyForm(0, 1);

		if ($this->config['show_chooseCat'] && $this->categoryCount) {
			$this->marker['###CHOOSE_CATEGORY_VERTICAL###'] = $this->chooseCategory(1); // vertical list
			$this->marker['###CHOOSE_CATEGORY_DROPDOWN###'] = $this->chooseCategory(2); // dropdown
		}

		// if sidebar is off and main content width constant is default, then make main content include sidebar width
		// (this is the auto-configure dont-think code)
		$mainContentWidth = $this->conf['mainContentWidth'];
		if ((($this->config['show_sidebar_actionbar'] == 1) || ($this->config['show_sidebar_actionbar'] == 3)) &&
			!strcmp($mainContentWidth,'75%')) {
			$sidebarWidth 	  = $this->conf['sidebarWidth'];
			$mainContentWidth = (int)$mainContentWidth + (int) $sidebarWidth;
			$mainContentWidth = (string) $mainContentWidth . '%';
			$this->marker['###MAINCONTENT_SETWIDTH###'] = 'style="width:' . $mainContentWidth. '"';
		}
		else {
			$this->marker['###MAINCONTENT_SETWIDTH###'] = 'style="width:' . $mainContentWidth. '"';
		}
		// set width for old template
		$this->marker['###MAINCONTENT_WIDTH###'] = $mainContentWidth;

		if ($this->config['allow_search']) {
			$this->marker['###SEARCHFORM_URL###'] = $this->pi_getPageLink($this->id);
			$this->marker['###SEARCHWORDS###'] = $this->searchWords;
			$this->marker['###SEARCH_BUTTON###'] = $this->pi_getLL('search_btn', 'Search');
			if ($this->searchWords) {
				$this->marker['###DISPLAY_SEARCH_RESULTS###'] = $this->displaySearchResults($this->searchWords);
				// clear out the other results/forms
				$this->marker['###DISPLAY_POSTS###'] = '';
				$this->marker['###DISPLAY_COMMENTS###'] = '';
				$this->marker['###DISPLAY_REPLYFORM###'] = '';
				$this->marker['###DISPLAY_COMMENTFORM###'] = '';
				$subpartMarker['###SHOW_POST_BTN###'] = "";
			}
		}
		else
			$subpartMarker['###SHOW_SEARCH###'] = '';

		// add RSS feed icon
		$this->marker = $this->addSubscribeRSSFeed($this->marker);

		// then substitute all the markers in the template into appropriate places
		$content = $this->cObj->substituteMarkerArrayCached($template, $this->marker, $subpartMarker, array());

		// clear out any empty template fields
		$content = preg_replace('/###.*?###/', '', $content);

		return $content;
	}

	/**
	*==================================================================================
	*  Display forum --
	*
	*  This allows multiple levels of replies or comments underneath each post.
	*  The posts can be sorted by date (most recent or least recent). They can also be
	*  viewed by a given category.
	*
	*  The code is broken into 5 steps, and this method of doing it is done to try to
	*  minimize database access & allow flexibility.
	*
	*   tx_wecdiscussion_post fields:
	*      post_datetime, post_lastedit_time, reply_uid, subject, message, category,
	*      image, image_caption, attachment
	*
	*  @param integer  the timestamp of the date to show
	*  @param integer  the category to show (category ID)
	*  @return string  content that contains the display of messages
	*=====================================================================================
	*/
	function displayForum($showDateTS, $showCat) {
		$forum_content = '';
		$reply_content = '';

		$fieldArray = $this->db_fields;
		$subjCount = 0;
		$single = $this->postvars['single'];
		if ($this->postvars['ispreview']) $single = $this->postvars['ispreview'];

		//-------------------------------------------------------------------------------------------------------
		// 1. DETERMINE WHICH POSTS TO DISPLAY
		//  - based on time (WEEK / MONTH)
		//  - based on last X entries from given date
		//
		//-------------------------------------------------------------------------------------------------------
		$selFields = '*';

		$showDateTS = intval($showDateTS); // security check
		$where = $this->setWhereDate($this->config['display_amount'], $showDateTS);

		// set limit
		$limit = '';
		if ($this->config['display_amount'] == 3) $limit = '10';
		else if ($this->config['display_amount'] == 4) $limit = '20';
		else if ($this->config['display_amount'] == 5) $limit = '30';

		// setup gotoURL
		$params = t3lib_div::_GET();
		if (strcmp($this->showDay,date("mdy"))) $params['tx_wecdiscussion']['show_date'] = $this->showDay;
		$pageID = $params['id'] ? $params['id'] : $GLOBALS['TSFE']->id;
		unset($params['id']);
		unset($params['tx_wecdiscussion']['archive']);
		unset($params['tx_wecdiscussion']['processmoderated']);
		unset($params['tx_wecdiscussion']['moderate']);
		unset($params['tx_wecdiscussion']['ispreview']);
		unset($params['tx_wecdiscussion']['isreply']);
		unset($params['tx_wecdiscussion']['showreply']);
		$gotoURL = $this->pi_getPageLink($pageID, '', $params);
		$gotoURL = t3lib_div::locationHeaderURL($gotoURL);
		$gotoURL .= ((strpos($gotoURL,'?') === FALSE)  ? '?' : '&');

		//
		// Add Javascript to handle replying for a post to the forum
		//
		// this  will go to the form, set the reply value
		$GLOBALS['TSFE']->additionalHeaderData['wecdiscussion_js'] .= '<script type="text/javascript" src="' . t3lib_extMgm::siteRelPath('wec_discussion') . 'res/wec_discussion.js"></script>';

		$forum_content = '<script type="text/javascript">
		function makeReply(mNum,mNum2,mStr) {
			if (mNum2 == 0) {
				clearReply();
			}
			else if (st = document.getElementById("subjTitle")) {
				st.innerHTML = "'.$this->pi_getLL("reply_field", "<b>Reply:</b>").'";
			}

			if (sv = document.getElementById("subjValue")) {
				if ((arguments.length > 2) && mStr) {
					sv.value = mStr;
					sv.readOnly = true;
					sv.style.background="#CCC";
				}
				else {
					sv.readOnly = false;
					sv.style.background="white";
				}
			}
			if (rud = document.getElementById("reply_uid_discussion"))
				rud.value = mNum;
			if (tud = document.getElementById("toplevel_uid_discussion"))
				tud.value = mNum2;

			return false;
		}
		function makeComment(mNum,mNum2) {
			commentForm = document.getElementById("CommentFormToggle");
			if (!commentForm) return false;
			if (commentForm.style.display == "none")
				commentForm.style.display = "block";
			else
				commentForm.style.display = "none";
			if (rud = document.getElementById("reply_uid_comment"))
				rud.value = mNum;
			if (tud = document.getElementById("toplevel_uid_comment"))
				tud.value = mNum2;
			return false;
		}		
		function deleteForumMsg(num) {
			gotoURL = "'.$gotoURL.'tx_wecdiscussion[deleteMsg]="+num;
			location.href = gotoURL;
		}
		function deleteAllForumMsg(num) {
			gotoURL = "'.$gotoURL.'tx_wecdiscussion[deleteAllMsg]="+num;
			location.href = gotoURL;
		}
		function editForumMsg(num) {
			gotoURL = "'.$gotoURL.'tx_wecdiscussion[edit_msg]="+num;
			location.href = gotoURL;
		}
		function clearReply() {
			document.getElementById("reply_uid_discussion").value = 0;
			document.getElementById("toplevel_uid_discussion").value = 0;
			document.getElementById("subjValue").value = "";
			document.getElementById("subjValue").readOnly = false;
			document.getElementById("subjValue").style.background="white";
			document.getElementById("subjTitle").innerHTML = "'.$this->pi_getLL("subject_field", "Subject:").'";
			//    document.forumReplyForm.post_category.value = document.forumReplyForm.saved_category.value;
			return false;
		}
		</script>';

		//-------------------------------------------------------------------------------------------------------
		//
		// 2. LOAD ALL TOP-LEVEL POSTS FROM DATABASE FOR GIVEN DURATION & STORE THESE
		//
		// store in array message[topMsgUID][msgIndex][replies]
		//-------------------------------------------------------------------------------------------------------
		$order_by = 'post_datetime DESC';
		if ($showCat) {
			//$where .= ' AND category='.intval($showCat);
		 	$where .= " AND FIND_IN_SET('". $showCat ."', category)";
		}
		$where .= ' AND toplevel_uid=0 ';
		$where .= ' AND pid IN(' . $GLOBALS['TYPO3_DB']->cleanIntList($this->pid_list) . ')';
		$where .= ' AND moderationQueue=0 ';
		$where .= $this->cObj->enableFields($this->postTable);
		// handle languages
		$lang = ($l = $GLOBALS['TSFE']->sys_language_uid) ? $l : '0,-1';
		$where .= ' AND sys_language_uid IN ('.$lang.') ';
		if ($single) $where = 'uid = '.$single;

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($selFields, $this->postTable, $where, '', $order_by, $limit);
		if (mysql_error()) t3lib_div::debug(array(mysql_error(), "SELECT ".$selFields.' FROM '.$this->postTable.' WHERE '.$where.' ORDER BY '.$order_by.' LIMIT '.$limit));
		$count = $GLOBALS['TYPO3_DB']->sql_num_rows($res);

		$rootMsgUIDList = '';
		// save all uids of root messages in a list
		$rootMsgArray = array(); // save all uids in array too
		if ($count == 0) {
			// If no messages, give a message about none yet
			if (!$this->postvars['show_cat'])
				$noMessages = $this->pi_getLL('no_messages', 'No messages have been posted yet.');
			// if no messages for a given category, give a message about none for that category
			else
				$noMessages = $this->pi_getLL('no_messages_for_category', 'No messages have been posted yet for this category.');
			$this->marker['###DISPLAY_POSTS###'] = '<div class="message">'.$noMessages.'</div>'.$forum_content;
			return;
		}
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			// save all the top-level messages in a list and array
			$subjCount++;
			$thisUID = $row['uid'];
			$rootMsgUIDList .= ($rootMsgUIDList == '') ? $thisUID : ",".$thisUID;
			array_push($rootMsgArray, $thisUID);

			// now save all the data
			foreach ($fieldArray as $field) {
				$message[$thisUID][0][$field] = $row[$field];
			}
			$message[$thisUID][0]['count'] = 1;
			$this->msgList[$thisUID] = $row;
		}
		// save the subject for single (reply/comment form)
		if (!$this->singleMsg)
			$this->singleMsg = (($count ==1) && $single) ? $message[$single][0] : 0;

		//-------------------------------------------------------------------------------------------------------
		//
		// 3. LOAD ALL REPLY POSTS FROM DATABASE FOR GIVEN POSTS & STORE THESE
		//  Why we need to load the replies separately is because a reply might have been made weeks or
		// months AFTER a given message is posted.
		//
		//  This handles multi-level replies nested up to <X#> levels.
		//
		//-------------------------------------------------------------------------------------------------------
		$sortOrderComments = ($this->conf['sortComments'] == 'latest_first') ? 'DESC' : 'ASC';
		$order_by = 'toplevel_uid,reply_uid,post_datetime '.$sortOrderComments;
		$where = 'toplevel_uid IN ('.$rootMsgUIDList.')';
		$where .= ' AND moderationQueue=0 AND pid IN(' . $GLOBALS['TYPO3_DB']->cleanIntList($this->pid_list) . ')';
		$where .= $this->cObj->enableFields($this->postTable);
		if ($showCat)
			$where .= ' AND category='.$showCat;
		$limit = '';

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($selFields, $this->postTable, $where, '', $order_by, $limit);
		if (mysql_error()) t3lib_div::debug(array(mysql_error(), "SELECT ".$selFields.' FROM '.$this->postTable.' WHERE '.$where.' ORDER BY '.$order_by.' LIMIT '.$limit));
			$count = $GLOBALS['TYPO3_DB']->sql_num_rows($res);

		// pull out all reply messages and add them appropriately
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$topUID = $row['toplevel_uid'];
			foreach ($rootMsgArray as $subj) {
				if ($message[$subj][0]['uid'] == $topUID) {
					$k = $message[$subj][0]['count'];
					$message[$subj][0]['count']++;
					break;
				}
			}

			foreach ($fieldArray as $field) {
				$message[$subj][$k][$field] = $row[$field];
			}
		}

		//-------------------------------------------------------------------------------------------------------
		//
		// 4. SORT THE MESSAGES TO DISPLAY
		//
		// Sort the messages according to categories & replies
		//
		// Can SORT BY most recent / oldest
		//-------------------------------------------------------------------------------------------------------
		$displayList = array();

		foreach ($rootMsgArray as $subj) {
			$subj = (int) $subj;
			$toplevel_uid = $message[$subj][0]['uid'];
			$totalMsgs = $message[$subj][0]['count'];
			$message[$subj][0]['level'] = 0;
			array_push($displayList, $message[$subj][0]);
			for ($i = 1; $i < $totalMsgs; $i++) {
				// if top level, then add
				if ($message[$subj][$i]['reply_uid'] == $toplevel_uid) {
					$message[$subj][$i]['level'] = 1;
					array_push($displayList, $message[$subj][$i]);
					// then find all on SECOND level
					for ($j = 1; $j < $totalMsgs; $j++) {
						if ($message[$subj][$j]['reply_uid'] == $message[$subj][$i]['uid']) {
							$message[$subj][$j]['level'] = 2;
							array_push($displayList, $message[$subj][$j]);

							// then find all on THIRD level
							for ($k = 1; $k < $totalMsgs; $k++) {
								$message[$subj][$k]['level'] = 3;
								if ($message[$subj][$k]['reply_uid'] == $message[$subj][$j]['uid']) {
									array_push($displayList, $message[$subj][$k]);
								}
							}
						}
					}
				}
			}
		}

		//-------------------------------------------------------------------------------------------------------
		//
		// 5. DISPLAY MESSAGES
		//
		// Format and show the messages. The formatting can be determined by the template.
		//
		// format:  <Text>
		//      Posted by: <Name> <Date>  [REPLY] | [DELETE]
		//-------------------------------------------------------------------------------------------------------
		switch ($this->config['entry_look']) {
			case 2: $entryTemplate = '###TEMPLATE_DISPLAYENTRY2###'; break;
			case 3: $entryTemplate = '###TEMPLATE_DISPLAYENTRY3###'; break;
			case 4: $entryTemplate = '###TEMPLATE_DISPLAYENTRY4###'; break;
			case 5: $entryTemplate = '###TEMPLATE_DISPLAYENTRY5###'; break;
			default: $entryTemplate = '###TEMPLATE_DISPLAYENTRY###'; break;
		}
		$template = $this->cObj->getSubpart($this->templateCode, $entryTemplate);

		$postTemplateEntry 		= $GLOBALS['TSFE']->cObj->getSubpart($template, '###POST_ENTRY###');
		$replyTemplateEntry 	= $GLOBALS['TSFE']->cObj->getSubpart($template, '###REPLY_ENTRY###');
		$postTemplateEntryStart = $GLOBALS['TSFE']->cObj->getSubpart($template, '###POST_ENTRY_START###');
		$postTemplateEntryEnd 	= $GLOBALS['TSFE']->cObj->getSubpart($template, '###POST_ENTRY_END###');
		$replyTemplateEntryStart = $GLOBALS['TSFE']->cObj->getSubpart($template, '###REPLY_ENTRY_START###');

		// set vars
		$waitForToggle = 0;
		$topLevelUID = 0;
		$countComments = 0;
		$prevMsg = 0;
		$topLevelMsg = 0;
		$showReplyMsg = 0;
		$numPerPage = $this->config['num_per_page'] ? $this->config['num_per_page'] : 0;
		$numShown = 0;
		$numMessages = 0;
		$curPage = $this->postvars['pg'] ? $this->postvars['pg'] : 1;
		$totalPages = $numPerPage ? (sizeof($displayList) / $numPerPage) + 1 : 1;
		$checkComments = ($this->config['only_comments'] || ($this->config['reply_level'] == 0)) ? true : false;

		for ($i = 0; $i < sizeof($displayList); $i++) {
			// Handle Pagination
			$numMessages++;
			if (($checkComments == true) ||  ($displayList[$i]['toplevel_uid'] == 0) || $single) {
				$numShown++;
			}
			if ($numPerPage && (($numShown <= ($numPerPage * ($curPage - 1))) || ($numShown > ($numPerPage * $curPage)))) {
				continue;
			}
			unset($markerArray);
			unset($sMarkerArray);
			$addToEntry = "";
			$prevMsg = $msg;
			$msg = $displayList[$i];
			$nextMsg = ($i < (sizeof($displayList) - 1)) ? $displayList[$i+1] : 0; // peek ahead for determining comment toggle
			if ($msg['reply_uid'] == 0) {
				// if this is not a reply, then set this as a toplevel message
				$topLevelUID = $msg['uid'];
				$thisTemplateEntry = $postTemplateEntry;
				$topLevelMsg = $msg;

				$sMarkerArray['###START_POST_ENTRY###'] = '<div>';
				$sMarkerArray = $this->processMarkers($sMarkerArray,$msg,$topLevelUID,$replyUID);
				$addToEntry .=  $this->cObj->substituteMarkerArrayCached($postTemplateEntryStart, $sMarkerArray, array(), array());
				$countComments = 0;
			} else {
				// this is a reply...
				$thisTemplateEntry = $replyTemplateEntry;

				if (!$countComments) { // if first comment
					$sMarkerArray['###START_REPLY_ENTRY###'] = '<div>';
					$sMarkerArray = $this->processMarkers($sMarkerArray,$msg,$topLevelUID,$replyUID);
					$addToEntry .=  $this->cObj->substituteMarkerArrayCached($replyTemplateEntryStart, $sMarkerArray, array(), array());
				}
				$countComments++;
			}
			if ($msg['uid'] == $this->postvars['showreply'])
				$showReplyMsg = $msg;
			$msgLeftMargin = 0;
			for ($k = 0; $k < $msg['level']; $k++)
				$msgLeftMargin += 25;
			if ($msgLeftMargin)
				$markerArray['###MARGIN_LEFT###'] = 'style="padding-left:'.$msgLeftMargin.'px"';
			if (strlen($msg['subject']) == 0) {
				// if subject is blank, then...
				if ($this->conf['showBlankSubject']) // put a space so will show up
					$msg['subject'] = $this->pi_getLL('no_subject', '&nbsp;');
				else {
					// clear out subject area
					$thisTemplateEntry = $this->cObj->substituteSubpart($thisTemplateEntry, '###SHOW_SUBJECT###', '');
				}
			}

			// SHOW THE MESSAGE
			//------------------------------------------------------------
			$postName = stripslashes($msg['name']);
			$postName = (strlen($postName) > 0) ? $postName : $this->pi_getLL('no_user', 'Anonymous');
			$postEmail = stripslashes($msg['email']);
			$markerArray['###SUBJECT###'] = stripslashes($msg['subject']);

			// add single view options if allowed to show single and not in single view
			if (!$single && ($this->config['allow_single_view'] != 0)) {
				$urlParams['tx_wecdiscussion']['single'] = $msg['uid'];
				$singleLinkStart = '<a href="'.$this->pi_getPageLink($GLOBALS['TSFE']->id,'',$urlParams).'">';
				if (($this->conf['singleViewLink'] == 'subject') || ($this->conf['singleViewLink'] == 'subject_and_view')) {
				 	$markerArray['###VIEW_SINGLE_LINKSTART###'] = $singleLinkStart;
					$markerArray['###VIEW_SINGLE_LINKEND###'] = '</a>';
				}
				if (($this->conf['singleViewLink'] == 'view_link') || ($this->conf['singleViewLink'] == 'subject_and_view'))
					$markerArray['###VIEW_SINGLE###'] =  $this->pi_getLL('action_separator', '|') . $singleLinkStart . $this->pi_getLL('view_single','View') . '</a>';
			}

			// for message text, either allow HTML, only certain tags or no HTML
			$msgText = $msg['message'];

			$tagsAllowed = $this->config['html_tags_allowed'];
			// if tags allowed set and either comment or NOT only_check_comments
			if (strlen($tagsAllowed) && (($msg['toplevel_uid'] != 0) || !$this->config['only_check_comments'] || !$this->userID)) {
				$msgText = $this->html_entity_decode($msgText);
				if ($tagsAllowed != 1 && strlen($tagsAllowed)) {
					$msgText = strip_tags($msgText,$tagsAllowed);
				}
			}
			// format message with general_stdWrap from TS config
			$msgView = $msgText;
			if (is_array($this->conf['general_stdWrap.'])) {
				$msgText = $this->cObj->stdWrap($msgText, $this->conf['general_stdWrap.']);
			}

			// MORE TAG HANDLING
			//
			// if message is greater than character limit, then put MORE...
			$mStart = 0;
			$moreTag = $this->conf['more_tag'];
			$unclosedTags = 0;
			if ((strlen($moreTag) && (($mStart = strpos($msgText,$moreTag)) != FALSE)) ||
			    (($charLimit = $this->config['display_characters_limit']) && (strlen($msgView) > $charLimit))) {
				// single view does not need more tag...so if it exists, remove it
				if ($single || ($msg['uid'] == $this->postvars['showreply'])) {
					if (strlen($moreTag))
						$msgText = preg_replace('@'.$moreTag.'@si','',$msgText);
				}
				else {
					// if more tag found, then put it where want
					if ($mStart) {
						$msgText = str_replace($moreTag,'',$msgText);
						$msgToSee = substr($msgText,0, $mStart);
					}
					// else truncate text
					else {
						$retArray = $this->truncateText($msgText,$charLimit);
						if (is_array($retArray)) {
							$msgToSee = $retArray[0];
							$unclosedTags = $retArray[1];
							$unclosedFullTags = $retArray[2];
						}
						else {
							$msgToSee = $retArray;
						}
					}
					// as long as not near the end of the text (kind of lame to put a more tag when almost done)
					if ((strlen($msgText) - strlen($msgToSee) > 30)) {
						$msgToHide = substr($msgText, strlen($msgToSee), strlen($msgText) - strlen($msgToSee));
						if (is_array($unclosedTags)) {
							for ($k = 0; $k < count($unclosedTags); $k++) {
								$msgToSee .= '</'.$unclosedTags[$k].'>';
								$msgToHide = $unclosedFullTags[$k].$msgToHide;
				            }
						}
						if ($this->conf['more_link'] == 'single_view') {
							$urlParamsMore['tx_wecdiscussion']['single'] = $msg['uid'];
							$msgText = $msgToSee . ' <a href="' . $this->pi_getPageLink($GLOBALS['TSFE']->id,'',$urlParamsMore) . '">' . $this->pi_getLL('more','More...') . '</a>';
						}
						else {
							$msgID = 'MSG' . $msg['uid'];
							$moreID = 'MORE' . $msg['uid'];
							$msgText = $msgToSee  . '<span id="' . $moreID . '" style="display:inline;"><a href="#" onclick="showHideItem(\'' . $moreID . '\'); return showHideItem(\'' . $msgID . '\');"> ' . $this->pi_getLL('more','More...') . '</a></span>
							<div id="' . $msgID . '" style="display:none;">' . $this->pi_getLL('more_bridge','...') . $msgToHide . '</div>';
						}
					}
				}
			}
			$markerArray['###MESSAGE###'] = $msgText;

			$markerArray['###POST_NAME###'] = $postName;
			$markerArray['###POST_DATE###'] = $this->getStrftime($this->pi_getLL('date_format', '%b %e, %Y'), $msg['post_datetime']);

			$markerArray['###POST_DATETIME###'] = $this->getStrftime($this->pi_getLL('datetime_format', '%b %e, %Y %H:%M%p'), $msg['post_datetime']);
			$markerArray['###POSTEDBY_TEXT###'] = $this->pi_getLL('posted_by', 'Posted By:');
			$markerArray['###ON_TEXT###'] = $this->pi_getLL('on_text', 'on');

			// if there is an email, put it in an encrypted link, otherwise just put the name
			$markerArray['###POST_NAME_EMAILLINK###'] = strlen($postEmail) ? $this->cObj->getTypoLink($postName,$postEmail) : $postName;

			$markerArray['###IMAGE###'] = $msg['image'] ? $this->getImageURL($msg['image']) : '';

			// show IP Address if option on
			if (!$this->conf['showIpAddress']) {
				$thisTemplateEntry = $this->cObj->substituteSubpart($thisTemplateEntry, '###SHOW_IPADDRESS###', '');
			}
			
			if ($msg['attachment']) {
				$attachFile = 'uploads/tx_wecdiscussion/'.$msg['attachment'];
				$attachMsg = $this->pi_getLL('attached_file', 'Attached File: ')." <a href=\"".$attachFile."\">".$msg['attachment'].'</a>';
				$markerArray['###ATTACHMENT###'] = $attachMsg;
			}
			else
				$markerArray['###ATTACHMENT###'] = '';

			if ($msg['category']) {
				$paramArrayCat['tx_wecdiscussion[show_cat]'] = $msg['category'];
				$gotoURL = $this->pi_getPageLink($GLOBALS['TSFE']->id, '', $paramArrayCat);
				$markerArray['###CATEGORY###'] = $this->pi_getLL('category_title', 'Category: ').'<a href="'.$gotoURL.'">'.$this->categoryListByUID[$msg['category']].'</a>';
			}
			else {
				$thisTemplateEntry = $this->cObj->substituteSubpart($thisTemplateEntry, '###SHOW_CATEGORY###', '');
			}

			// add a view comment button (unless viewing single, then show all comments)
			$msg['view_commentreply'] = 0;
			if (($this->config['reply_level'] > 0) && !$single) {
				// go through and find the right top level message and add a "view comment" if there are comments for this message
				foreach ($rootMsgArray as $topMsgUID) {
					if (($message[$topMsgUID][0]['uid'] == $msg['uid']) && ($message[$topMsgUID][0]['count'] > 1)) {
						if ($this->config['allow_toggle_commentsreply']) {
							$viewCommentStr = ($this->config['reply_is_comment']) ? $this->pi_getLL('view_comments', 'View Comments') : $this->pi_getLL('view_replies', 'View Replies');
							$viewCommentStr .= ' ['.($message[$topMsgUID][0]['count']-1).']';
							if ($this->conf['isOldTemplate'] == 1) {
								$markerArray['###VIEW_COMMENTS###'] = $this->pi_getLL('action_separator', '|') . ' <a href="#" onclick="return showHideMsg(\'CMMT'.$msg['uid'].'\');return false;">'.$viewCommentStr.'</a>';
							}
							else {
								$markerArray['###VIEW_COMMENTS###'] = $this->pi_getLL('msgbutton_separator', '|') . ' <a class="button" href="#" onclick="return showHideMsg(\'CMMT'.$msg['uid'].'\');return false;"><span class="label viewIcon">'.$viewCommentStr.'</span></a>';
							}
							$msg['view_commentreply'] = 1;
						}

						$numComments = $message[$topMsgUID][0]['count']-1;
						$markerArray['###VIEW_COMMENTS_NUM###'] = '<span class="messageCommentNum">'.$this->pi_getLL('viewcommentnum_start').$numComments.$this->pi_getLL('viewcommentnum_end').(($numComments > 1) ? $this->pi_getLL('viewcommentnum_end_plural') : '').'</span>';
						if ($this->config['allow_toggle_commentsreply']) {
							$markerArray['###VIEW_COMMENTS_NUM###'] = ' <a href="#" onclick="return showHideMsg(\'CMMT'.$msg['uid'].'\');return false;">'.$markerArray['###VIEW_COMMENTS_NUM###'].'</a>';
						}
						$markerArray['###VIEW_COMMENTS_NUM_ONLY###'] = '<span class="messageCommentNumOnly">' . $numComments . '</span>';
						break;
					}
				}
				if ((($this->config['show_report_abuse_button'] == 1) && $msg['toplevel_uid']) || ($this->config['show_report_abuse_button'] == 2)) {
					$params = t3lib_div::_GET();
					$params['tx_wecdiscussion']['abs'] = $msg['uid'];
					$linkURL = $this->pi_getPageLink($this->id,'',$params);
					if ($this->conf['isOldTemplate'] == 1) {
						$markerArray['###ABUSE_BTN###'] = $this->pi_getLL('action_separator', '|')  . ' <a href="' . $linkURL . '">' . $this->pi_getLL('abuse_button','Report Abuse') . '</a>';
					}
					else {
						$markerArray['###ABUSE_BTN###'] = $this->pi_getLL('msgbutton_separator', '|')  . ' <a class="button" href="' . $linkURL . '"><span class="label">' . $this->pi_getLL('abuse_button','Report Abuse') . '</span></a>';
					}
				}
			}

			$markerArray = $this->processMarkers($markerArray,$msg,$topLevelUID,$replyUID);

			// if comments only, then clear the reply and topLevel vars
			if ($this->isComment && ($this->config['reply_level'] == 0)) {
				$replyUID = 0;
				$topLevelUID = 0;
			}

			// now substitute all the marker arrays in the template
			//-------------------------------------------------------------------------
			$msg_content = $this->cObj->substituteMarkerArrayCached($thisTemplateEntry, $markerArray, array(), array());

			// Handle showHideComment for comments div
			//--------------------------------------------------------------------------
			// if we are in toggle comments section AND next msg is a top level OR this is last message, then make this end...
			if (($waitForToggle == 2) && (($nextMsg && $nextMsg['reply_uid'] == 0) || ($i == (sizeof($displayList) - 1)))) {
				$msg_content = $msg_content.'</div>';
				$waitForToggle = 0;
			}

			// if last message or end of comments/replies, then close it properly
			if (($nextMsg && $nextMsg['reply_uid'] == 0) || ($i == (sizeof($displayList)-1))) {
				if ($nextMsg['reply_uid'] == 0) {
					$sMarkerArray['###END_POST_ENTRY###'] = '</div>';
				}
				if ($countComments) {
					$sMarkerArray['###END_REPLY_ENTRY###'] = '</div>';
				}
				$sMarkerArray = $this->processMarkers($sMarkerArray,$topLevelMsg,$topLevelUID,$replyUID);
				$msg_content .= $this->cObj->substituteMarkerArrayCached($postTemplateEntryEnd, $sMarkerArray, array(), array());
			}

			// START TOGGLE COMMENT after root message...if this has comments
			if ($msg['view_commentreply'] > 0) {
				$msg['view_commentreply'] = 0;
				// hide most comments unless supposed to be shown
				if (!$this->postvars['showreply'] || ($this->postvars['showreply'] != $msg['uid']))
					$startShow = 'display:none;';
				$msg_content .= '<div id="CMMT'.$msg['uid'].'" style="'.$startShow.'">';
				$waitForToggle = 2;
			}

			// Add content
			$msg_content = $addToEntry . $msg_content;

			// Then assign to right content type (Reply Or Main Message)
			if ($this->config['reply_is_comment'] && ($topLevelUID != 0))
				$reply_content .= $msg_content;
			else
				$forum_content .= $msg_content;

		} // end for loop

		$this->marker['###TOTAL_POSTS###'] = $numShown;
		$this->marker['###TOTAL_COMMENTS###'] = $numMessages - $numShown;
		$this->marker['###TOTAL_MESSAGES###'] = $numMessages;

		// Handle showing page links
		$lastPage = $numPerPage ? ceil($numShown / $numPerPage) : 1;
		$this->marker['###PAGE_NUM###'] = '<span class="pageLink">'. $this->pi_getLL('page_name', 'Page #') . (string)$curPage	. '</span>';
		if ($curPage < $lastPage) {
			$params = t3lib_div::_GET();
			$params['tx_wecdiscussion']['pg'] = $curPage + 1;
			$linkURL = $this->pi_getPageLink($this->id,'',$params);
			$this->marker['###PAGE_NEXT###'] = '<span class="pageLink"><a href="'. $linkURL.'">'.$this->pi_getLL('next_page', 'Next >').'</a></span>';
		}
		else {
			$this->marker['###PAGE_NEXT###'] = '';
		}
		if ($curPage > 1) {
			$params = t3lib_div::_GET();
			$params['tx_wecdiscussion']['pg'] = $curPage - 1;
			$linkURL = $this->pi_getPageLink($this->id,'',$params);
			$this->marker['###PAGE_PREV###'] = '<span class="pageLink"><a href="'. $linkURL.'">'.$this->pi_getLL('prev_page', '< Prev').'</a></span>';
		}
		else {
			$this->marker['###PAGE_PREV###'] = '';
		}
		if ($lastPage > 1) {
			$params = t3lib_div::_GET();
			$pageNumStr = '';
			for ($k = 1; $k <= $lastPage; $k++) {
				if ($k != $curPage) {
					$params['tx_wecdiscussion']['pg'] = $k;
					$linkURL = $this->pi_getPageLink($this->id,'',$params);
					$pageNumStr .= '<span class="pageLink"><a href="'. $linkURL.'">'.$k.'</a></span>';
				}
				else
					$pageNumStr .= '<span class="pageLink" style="font-weight:bold;">'.$k.'</span>';
			}
			$this->marker['###PAGE_NUM_LIST###'] = $pageNumStr;
		}

		// this is coming from preview to force the reply to be shown
		if ($msgID = $this->postvars['showreply'] && $this->postvars['ispreview']) {
			$topUID = ($showReplyMsg) ? $showReplyMsg['uid'] : 0;
			$subj   = ($showReplyMsg) ? $showReplyMsg['subject'] : '';
			$this->marker['###DISPLAY_CODE###'] =  '
				<script type="text/javascript">
					//<![CDATA[
					showOneHideAllMsg("MSG'.$msgID.'",'.$topUID.',"RE:'.$subj.'");
					//]]>
				</script>';
		}

		$this->marker['###DISPLAY_POSTS###'] = $forum_content;
		if ($this->config['reply_is_comment']) {
			$this->marker['###DISPLAY_COMMENTS###'] = $reply_content;
		}
	}

    function processMarkers($markerArray, $msg, $topLevelUID, $replyUID) {
		$markerArray['###TOGGLE_HIDE_START_ON###'] = '<div id="MSG'.$msg['uid'].'" style="display:block;">';
		$markerArray['###TOGGLE_HIDE_START_OFF###'] = '<div id="MSG'.$msg['uid'].'" style="display:none;">';
		$markerArray['###TOGGLE_HIDE_LINK###'] = '<div id="MSGOFF'.$msg['uid'].'" style="display:inline;"><a href="#" onclick="showHideItem(\'MSGOFF'.$msg['uid'].'\'); return showHideMsg(\'MSG'.$msg['uid'].'\');">'.$this->pi_getLL('show_toggle','View').'</a></div>';
		$markerArray['###TOGGLE_HIDE_ONCLICK###'] = 'showHideMsg(\'MSG'.$msg['uid'].'\'); return false;';
		$markerArray['###TOGGLE_HIDE_END###'] = '</div>';
		$markerArray['###VIEW_REPLY_FORM###'] = '<div id="ReplyForm'.$msg['uid'].'" style="display:block;"></div>';
		$markerArray['###POST_ANCHOR###'] = '<a name="msganchor'.$msg['uid'].'"></a>';

		// Add a reply button if user can reply
		//-----------------------------------------------------------------------
		if (($msg['level'] < $this->config['reply_level']) && (($this->config['require_login_to_reply'] == 0) || ($this->userID != 0))) {
			$mSubj = 'RE:'.$msg['subject'];
			$params = t3lib_div::_GET();
			$pageID = $params['id'] ? $params['id'] : $GLOBALS['TSFE']->id;
			unset($params['id']);
			unset($params['tx_wecdiscussion']['archive']);
			unset($params['tx_wecdiscussion']['deleteMsg']);
			unset($params['tx_wecdiscussion']['deleteAllMsg']);
			unset($params['tx_wecdiscussion']['showreply']);
			if (!strcmp($this->showDay,date("mdy"))) unset($params['tx_wecdiscussion[show_date]']);

			$replyUID = $msg['uid'];
			if (($this->config['reply_level'] == 0) && $this->isComment) {
				// if no parent messages, then keep msgs at same level
				$replyUID = 0;
				$topLevelUID = 0;
			}
			$goHash = ($this->canPostReply) ? "#typeYourMessage" : "#typeYourComment";

			if ($this->postvars['single'] || ($this->config['reply_level'] == 0) || ($this->config['allow_single_view'] == 0)) {
				$onclick = "window.location.hash='" . $goHash . "';return false;";
			}
			else {
				$params['tx_wecdiscussion[isreply]'] = $replyUID;
				$params['tx_wecdiscussion[single]'] = $topLevelUID ? $topLevelUID : $replyUID;
				$onclick = "window.location='".$this->getAbsoluteURL($pageID, $params, TRUE) . $goHash . "';return false;";
			}

			if ($this->isComment) {
				if ($this->conf['isOldTemplate'] == 1) {
 					$btnStr = '<a href="#" onclick="makeComment(' . $replyUID . ',' . $topLevelUID . ');' . $onclick . '">' . $this->pi_getLL('comment_btn', 'Add Comment') . '</a>';
				}
				else {
					$btnStr = '<a class="button" href="#" onclick="makeComment(' . $replyUID . ',' . $topLevelUID . ');' . $onclick . '"><span class="label replyIcon">' . $this->pi_getLL('comment_btn', 'Add Comment') . '</span></a>';
				}
			} else if ($this->showPostForm) {
				if ($this->conf['isOldTemplate'] == 1) {
					$btnStr = '<a href="#" onclick="makeReply(' . $replyUID . ',' . $topLevelUID.',\'' . $mSubj . '\');' . $onclick . '">' . $this->pi_getLL('reply_btn', 'Reply') . '</a>';
				}
				else {
					$btnStr = '<a class="button" href="#" onclick="makeReply(' . $replyUID . ',' . $topLevelUID.',\'' . $mSubj . '\');' . $onclick . '"><span class="label replyIcon">' . $this->pi_getLL('reply_btn', 'Reply') . '</span></a>';
				}
			}

			$markerArray['###REPLY_BTN###'] = $btnStr;
		}
		else
			$markerArray['###REPLY_BTN###'] = '';

		$markerArray['###TOGGLE_HIDEALL_ONCLICK###'] = 'showOneHideAllMsg(\'MSG'.$msg['uid'].'\','.$topLevelUID.',\''.$mSubj.'\'); return false;';

		// show IP Address if option on
		if ($this->conf['showIpAddress']) {
			$markerArray['###SHOW_IP_ADDRESS###'] = $msg['ipAddress'];
			$markerArray['###IP_ADDRESS###'] = $msg['ipAddress'];
		}
		
		// Add an edit/delete button if this user is the author or if is admin
		//------------------------------------------------------------------------
		if (($GLOBALS['TSFE']->loginUser) && (($msg['useruid'] == $this->userID) || ($this->isAdministrator))) {
			if ($this->conf['isOldTemplate'] == 1) {
				$markerArray['###EDIT_BTN###'] = $this->pi_getLL('action_separator', '|')  . " <a href=\"#\" onclick=\"editForumMsg(".$msg['uid']."); return false;\">".$this->pi_getLL('edit_btn', 'Edit').'</a>';
				$markerArray['###DELETE_BTN###'] = $this->pi_getLL('action_separator', '|')  . " <a href=\"#\" onclick=\"deleteForumMsg(".$msg['uid']."); return false;\">".$this->pi_getLL('delete_btn', 'Delete').'</a>';
				if (($msg['level'] == 0) && ($msg['count'] > 1)) {
					$markerArray['###DELETEALL_BTN###'] = $this->pi_getLL('action_separator', '|')  . " <a href=\"#\" onclick=\"if (confirm('".$this->pi_getLL('deleteall_confirm','Are you sure you want to delete this post and all comments?') . "')) { deleteAllForumMsg(" . $msg['uid'].") } return false;\"><span class=\"label\">" . $this->pi_getLL('deleteall_btn', 'Delete All') . '</a>';
				}
			}
			else {
				$markerArray['###EDIT_BTN###'] = $this->pi_getLL('msgbutton_separator', '|')  . " <a class=\"button\" href=\"#\" onclick=\"editForumMsg(".$msg['uid']."); return false;\"><span class=\"label editIcon\">".$this->pi_getLL('edit_btn', 'Edit').'</span></a>';
				$markerArray['###DELETE_BTN###'] = $this->pi_getLL('msgbutton_separator', '|')  . " <a class=\"button\" href=\"#\" onclick=\"deleteForumMsg(".$msg['uid']."); return false;\"><span class=\"label deleteIcon\">".$this->pi_getLL('delete_btn', 'Delete').'</span></a>';
				if (($msg['level'] == 0) && ($msg['count'] > 1)) {
					$markerArray['###DELETEALL_BTN###'] = $this->pi_getLL('msgbutton_separator', '|')  . " <a class=\"button\" href=\"#\" onclick=\"if (confirm('".$this->pi_getLL('deleteall_confirm','Are you sure you want to delete this post and all comments?') . "')) { deleteAllForumMsg(" . $msg['uid'].") } return false;\"><span class=\"label deleteIcon\">" . $this->pi_getLL('deleteall_btn', 'Delete All') . '</span></a>';
				}
			}
		}

		// Adds hook for processing of extra item markers
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_wecdiscussion_pi1']['extraItemMarkerHook'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_wecdiscussion_pi1']['extraItemMarkerHook'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$markerArray = $_procObj->extraItemMarkerProcessor($markerArray, $msg, $lConf, $this);
			}
		}
		// Pass to user defined function
		if ($this->conf['itemMarkerArrayFunc']) {
			$markerArray = $this->userProcess('itemMarkerArrayFunc', $markerArray);
		}

		return $markerArray;
	}


	/**
	 * Truncates text. Borrowed from cakephp TextHelper
	 *
	 * Cuts a string to the length of $length and replaces the last characters
	 * with the ending if the text is longer than length.
	 *
	 * @param string  $text	String to truncate.
	 * @param integer $length Length of returned string, including ellipsis.
	 * @return array Trimmed string, unclosed tags.
	 */
	 function truncateText($text, $length) {
           // if the plain text is shorter than the maximum length, return the whole text
           if (strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
               return $text;
           }
           // splits all html-tags to scanable lines
           preg_match_all('/(<.+?>)?([^<>]*)/s', $text, $lines, PREG_SET_ORDER);
           $total_length = 0;
           $open_tags = array();
           $truncate = '';
		   $open_fulltags = array();
           foreach ($lines as $line_matchings) {
               // if there is any html-tag in this line, handle it and add it (uncounted) to the output
               if (!empty($line_matchings[1])) {
                   // if it's an "empty element" with or without xhtml-conform closing slash (f.e. <br/>)
                   if (preg_match('/^<(\s*.+?\/\s*|\s*(img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param)(\s.+?)?)>$/is', $line_matchings[1])) {
                       // do nothing
                   // if tag is a closing tag (f.e. </b>)
                   } else if (preg_match('/^<\s*\/([^\s]+?)\s*>$/s', $line_matchings[1], $tag_matchings)) {
                       // delete tag from $open_tags list
                       $pos = array_search($tag_matchings[1], $open_tags);
                       if ($pos !== false) {
                           unset($open_tags[$pos]);
						   unset($open_fulltags[$pos]);
                       }
                   // if tag is an opening tag (f.e. <b>)
                   } else if (preg_match('/^<\s*([^\s>!]+).*?>$/s', $line_matchings[1], $tag_matchings)) {
                       // add tag to the beginning of $open_tags list
                       array_unshift($open_tags, strtolower($tag_matchings[1]));
					   array_unshift($open_fulltags,$line_matchings[1]);
                   }
                   // add html-tag to $truncate'd text
                   $truncate .= $line_matchings[1];
               }

               // calculate the length of the plain text part of the line; handle entities as one character
               $content_length = strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i', ' ', $line_matchings[2]));

               if ($total_length+$content_length > $length) {
                  // the number of characters which are left
                  $left = $length - $total_length;
                  $entities_length = 0;

                  if (preg_match('/^<(\s*.+?\/\s*|\s*(img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param)(\s.+?)?)>$/is', $line_matchings[1])) {
						$left = strlen($line_matchings[2]);
				  }

                  // search for html entities
                  else if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i', $line_matchings[2], $entities)) {
                       // calculate the real length of all entities in the legal range
                       foreach ($entities[0] as $entity) {
                           if ($entity[1]+1-$entities_length <= $left) {
                               $left--;
                               $entities_length += strlen($entity[0]);
                           } else {
                               // no more characters left
                               break;
                           }
                       }
                   }

                   $truncate .= substr($line_matchings[2], 0, $left+$entities_length);
                   // maximum length is reached, so exit loop
                   break;
               } else {
                   $truncate .= $line_matchings[2];
                   $total_length += $content_length;
               }

               // if the maximum length is reached, exit loop
               if($total_length >= $length) {
                   break;
               }
           	}

	        // words should not be cut in the middle...
            // ...search the last occurance of a period...and if not found, then a " " (space)
			$periodpos = strrpos($truncate, '. ');
			if ($periodpos === FALSE ) {
			    $periodpos = strrpos($truncate, ' ');
			    if ($periodpos !== FALSE) {
					// now check to make sure " " (space) is not in an HTML tag
					$tagStart = strrpos($truncate,"<");
					$tagEnd = strrpos($truncate,">");
					$tagText = substr($truncate,$tagStart,$tagEnd-$tagStart+1);
					if (strlen($tagText)) {
						if (count($open_fulltags) && !strcmp($tagText,$open_fulltags[0])) {
							array_shift($open_fulltags);
						}
						$truncate = substr($truncate,0,$tagStart);
					}
				 }
			     else {
			        $truncate = $text;
			     }
			}
			else {
			      $truncate = substr($truncate, 0, $periodpos+1);
			}

			$retArray = array($truncate,$open_tags,$open_fulltags);

			return $retArray;
	}

	function getImageURL($imageFileName) {
		// build special image code for showing the image
		$oldAbs = $GLOBALS['TSFE']->absRefPrefix;
		$GLOBALS['TSFE']->absRefPrefix = t3lib_div::getIndpEnv('TYPO3_SITE_URL');
		$imgFile = 'uploads/tx_wecdiscussion/' . $imageFileName;
		$imgConf['file'] = $imgFile;
		$imgConf['file.']['maxW'] = $this->conf['imageWidth'];
		$imgConf['file.']['maxH'] = $this->conf['imageHeight'];
		$imgURLStr = $this->cObj->Image($imgConf); // run through imageMagick
		$imgSrc = substr($imgURLStr, strpos($imgURLStr, 'src=')+4, 4);
		if ($imgSrc && (($imgSrc[0] == '"') || ($imgSrc[1] == '"'))) {
			// if imageMagick not found, just use the straight image
			if ($this->conf['imageWidth']) {
				$imgSize = $this->conf['imageWidth'] ? " width=".$this->conf['imageWidth'] : "";
			}
			else if ($this->conf['imageHeight']) {
				$imgSize .= $this->conf['imageHeight'] ? " height=".$this->conf['imageHeight'] : "";
			}
			$imgURLStr = '<img src="'.$imgFile.'" '.$imgSize.' />';
		}
		$GLOBALS['TSFE']->absRefPrefix = $oldAbs;

		return $imgURLStr;
	}

	/**
	* Set Where Date: Set the date for reading in posts from database
	*
	* @param integer  $which : the date/length field
	* @param string  $showDateTS : the date to show from
	* @return string  $where: the SQL query
	*/
	function setWhereDate($which, $showDateTS) {
		$where = '1';
		switch ($which) {
			case 1: // WEEKLY
			// Determine dates from beginning of week to end of week
				$dayOfWeek = strftime('%u', $showDateTS);
				$begOfWeekTS = mktime( 0,  1, 0, strftime('%m', $showDateTS), strftime('%d', $showDateTS)-$dayOfWeek,     strftime('%y', $showDateTS));
				$endOfWeekTS = mktime(23, 59, 0, strftime('%m', $showDateTS), strftime('%d', $showDateTS)+(6-$dayOfWeek), strftime('%y', $showDateTS));

				$where = "post_datetime>='".$begOfWeekTS."' AND post_datetime<='".$endOfWeekTS."'";
			break;

			case 2: // MONTHLY
				$startDayOfMonthTS = mktime(0, 1, 0, strftime('%m', $showDateTS) , 1, strftime('%Y', $showDateTS));
				$endDayOfMonthTS   = mktime(0, 0, 0, strftime('%m', $showDateTS)+1, 1, strftime('%Y', $showDateTS));
				$where = "post_datetime>='".$startDayOfMonthTS."' AND post_datetime<'".$endDayOfMonthTS."'";
			break;

			case 3: // LAST 10
				$where = "post_datetime<='".$showDateTS."'";
			break;

			case 4: // LAST 20
				$where = "post_datetime<='".$showDateTS."'";
			break;

			case 5: // LAST 30
				$where = "post_datetime<='".$showDateTS."'";
			break;

			case 7: // LAST 7 DAYS
				$sevenDaysAgoTS = mktime(0, 1, 0, strftime('%m', $showDateTS), strftime('%d', $showDateTS)-7, strftime('%y', $showDateTS));
				$where = "post_datetime>='".$sevenDaysAgoTS."' AND post_datetime<='".$showDateTS."'";
			break;

			case 6: // ALL
				$where = '1';
			break;

		}

		return $where;
	}


	/**
	 * Display the Search Results
	 *
	 *
	 * @param	string	searchWords	list of words to search separated by comma
	 * @return	string	searchContent	search results
	 */
	function displaySearchResults($searchWords) {
		// grab TS config if available
		if ($searchList = $this->conf['searchFieldList']) {
			$checkedFields = array();
			$searchFieldList = array();
			$searchArray = t3lib_div::trimExplode(',',$searchList,1);
			for ($i = 0; $i < count($searchArray); $i++) {
				if (in_array($searchArray[$i],$this->db_fields)) {
					$searchFieldList[] = $searchArray[$i];
				}
			}
			if (count($searchFieldList)) {
				$this->searchFieldList = $searchFieldList;
			}
		}

		// Grab template for search
		$templateSearchResults = $this->cObj->getSubpart($this->templateCode, '###TEMPLATE_SEARCH_RESULTS###');

		// Get all the search results
		$searchContent = "<ul class=\"entries\">";
		$where = 'pid IN(' . $GLOBALS['TYPO3_DB']->cleanIntList($this->pid_list) . ') ';
		$where .= $this->cObj->searchWhere($searchWords, $this->searchFieldList, 'tx_wecdiscussion_post');
		$where .= ' AND moderationQueue=0';
		$where .= $this->cObj->enableFields($this->postTable);

		// handle languages
		$lang = ($l = $GLOBALS['TSFE']->sys_language_uid) ? $l : '0,-1';
		$where .= ' AND sys_language_uid IN ('.$lang.') ';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('DISTINCT tx_wecdiscussion_post.*,tx_wecdiscussion_post.*', $this->postTable, $where, '', '');
		if (mysql_error()) t3lib_div::debug(array(mysql_error(), $res));
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$thisTemplateEntry = $templateSearchResults;
			$subpartArray = array();
			unset($markerArray);
			$markerArray['###SUBJECT###'] = stripslashes($row['subject']);
			if (strlen($row['subject']) == 0) {
				if ($this->conf['showBlankSubject']) // put a space so will show up
					$markerArray['###SUBJECT###'] = $this->pi_getLL('no_subject', '&nbsp;');
				else
					$subpartArray['###SHOW_SUBJECT###'] = "";
			}
			$markerArray['###MESSAGE###'] = stripslashes($row['message']);
			$markerArray['###POST_NAME###'] = $row['name'];
			$markerArray['###POST_NAME_EMAILLINK###'] = strlen($row['email']) ? $this->cObj->getTypoLink($row['name'],$row['email']) : $row['name'];
			$markerArray['###POST_DATE###'] = $this->getStrftime($this->pi_getLL('date_format', '%b %e, %Y'), $row['post_datetime']);
			$markerArray['###POST_DATETIME###'] = $this->getStrftime($this->pi_getLL('datetime_format', '%b %e, %Y %H:%M%p'), $row['post_datetime']);
			$markerArray['###ATTACHMENT###'] = $row['attachment'] ? $row['attachment'] : '';
			$markerArray['###IMAGE###'] = $row['image'] ? $this->getImageURL($row['image']) : '';
			$markerArray['###POSTEDBY_TEXT###'] = $this->pi_getLL('posted_by', 'Posted By:');
			$markerArray['###ON_TEXT###'] = $this->pi_getLL('on_text', 'on');

			if ($this->config['allow_single_view'] != 0) {
				if ($row['toplevel_uid'] != 0)
					$urlParams['tx_wecdiscussion']['single'] = $row['toplevel_uid'];
				else
					$urlParams['tx_wecdiscussion']['single'] = $row['uid'];
			}
			$markerArray['###POST_LINK_START###'] = '<a class="button" href="'.$this->pi_getPageLink($this->id,'',$urlParams).'">';
			$markerArray['###POST_LINK_END###'] = '</a>';
			$markerArray['###GOTO_POST_LABEL###'] = $this->pi_getLL('goto_post','Go to this message');
			if ($row['category'])
				$markerArray['###CATEGORY###'] = $this->pi_getLL('category_title', 'Category:').$this->categoryListByUID[$row['category']];
			$searchContent .= '<li class="entry">' . $this->cObj->substituteMarkerArrayCached($thisTemplateEntry, $markerArray, $subpartArray, array()) . '</li>';
		}
		// if no results, then say so
		if (!strlen($searchContent)) {
			$searchContent = '<li class="entry">' . $this->pi_getLL('no_search_results','No results found for your search.') . '</li>';
		}
		$searchContent .= '</ul>';

		return $searchContent;
	}

	/**
	*=====================================================================================
	* Display the Reply Form
	*
	* @param 	boolean  $editID  =1 if this is an edit of a message, =0 if just a reply
	* @param 	boolean  $isComment =1 if this is a comment, =0 if just a reply
	* @return 	string  content that contains the display of reply form
	*=====================================================================================
	*/
	function displayReplyForm($editID = 0, $isComment = 0) {
		$subpartArray = array();

		$isErrors = ($this->formErrorText && ((!t3lib_div::_GP('ForumComment') && !$isComment) || (t3lib_div::_GP('ForumComment') && $isComment))) ? 1 : 0;

		if ($this->postvars['ispreview'] && $this->singleMsg) {
			$editID = $this->postvars['ispreview'];
		}

		// if have replies/comments...then allow to toggle comment form
		if ($this->config['reply_level'] > 0 && !$this->singleMsg) {
			$markerArray['###COMMENTFORM_TOGGLE###'] = '<div id="CommentFormToggle" style="display:none">';
			$markerArray['###COMMENTFORM_TOGGLE_END###'] = '</div>';
		}

		// if comment, then load comment form (if exists) --- otherwise use the post/reply form
		if ($isComment && ($templateFormContent = $this->cObj->getSubpart($this->templateCode, '###TEMPLATE_COMMENTFORM###'))) {
			$markerArray['###FORM_HEADER###'] = $this->pi_getLL('form_comment_header', 'Please enter your comments:');
			if (!$this->config['only_comments']) {
				$subpartArray['###SHOW_SUBJECT###'] = '';
			}
		} else {
			$templateFormContent = $this->cObj->getSubpart($this->templateCode, '###TEMPLATE_POSTFORM###');
			$markerArray['###FORM_HEADER###'] = $this->pi_getLL('form_header', 'Please enter your message:');
		}
		// fill in error text if have errors
		if ($isErrors) {
			$markerArray['###FORM_ERROR###'] = $this->formErrorText;
			$goHash = ($this->canPostReply) ? "#typeYourMessage" : "#typeYourComment";
			$markerArray['###FORM_ERROR###'] .= '<script type="text/javascript">window.location.hash=\'' . $goHash . '\';</script>';
			// make sure the form is shown
			$markerArray['###COMMENTFORM_TOGGLE###'] = '<div id="CommentFormToggle" style="display:block">';
			$markerArray['###COMMENTFORM_TOGGLE_END###'] = '</div>';
		}
		else {
			$subpartArray['###SHOW_ERROR###'] = '';
		}

		// fill in all form required fields
		foreach ($this->db_showFields AS $req_field) {
			$markerArray['###FORM_'.strtoupper($req_field).'###'] = $this->pi_getLL('form_'.strtolower($req_field), $req_field);
		}

		// lock the username in if logged in
		if ($this->conf['lockInNameEmail'] && $this->userID) {
			$markerArray['###FORM_FIELD_DISABLED###'] = 'readonly="readonly" style="background:#eee;color:#333;"';
		}
		
		// show ip address message 
		$markerArray['###IP_ADDRESS_RECORDED###'] = $this->pi_getLL('ip_address_recorded', 'Your IP Address will be recorded:') . t3lib_div::getIndpEnv('REMOTE_ADDR');
		$markerArray['###IP_ADDRESS###'] = t3lib_div::getIndpEnv('REMOTE_ADDR');

		$markerArray['###SUBMIT_BTN###'] = ($this->postvars['single']) ? $this->pi_getLL('submit_reply_btn', 'Post Reply') : $this->pi_getLL('submit_btn', 'Post New Message');
		$markerArray['###SUBMIT_COMMENT_BTN###'] = $this->pi_getLL('submit_comment_btn', 'Add Comment');
		if ($this->config['allow_preview_before_post'])
			$markerArray['###PREVIEW_BEFORE_POST_BTN###'] = '<input name="ForumPreviewBeforePost" type="submit" value="'.$this->pi_getLL('submit_preview_btn', 'Preview Before Post').'" />';

		// setup default values for hidden vars
		$catVal = $this->showCategory;
		if ($isErrors) {
			$replyID = $this->postvars['reply_uid'];
			$topLevelID = $this->postvars['toplevel_uid'];
			$catVal = $this->postvars['category'];
			if ($catVal < 0) { $catVal = 0; $this->postvars['category'] = 0; }
		}
		else if ($editID) {
			$replyID = $this->filledInVars['reply_uid'];
			$topLevelID = $this->filledInVars['toplevel_uid'];
			$catVal = $this->filledInVars['category'];
			if ($catVal < 0) { $catCal = 0; $this->filledInVars['category'] = 0; }
			$getvarsE = t3lib_div::_GET();
			unset($getvarsE['id']);
			unset($getvarsE['tx_wecdiscussion']['edit_msg']);
			unset($getvarsE['tx_wecdiscussion']['editMsg']);
			unset($getvarsE['tx_wecdiscussion']['ispreview']);
			unset($getvarsE['tx_wecdiscussion']['isreply']);
			$markerArray['###CANCEL_BTN###'] = '<input name="Cancel" type="button" onclick="location.href=\''.$this->getAbsoluteURL($this->id, $getvarsE, TRUE).'\'" value="'.$this->pi_getLL('cancel_btn', 'Cancel').'" />';
		}
		else if ($this->singleMsg) {
			$replyID = $this->singleMsg['reply_uid'] ? $this->singleMsg['reply_uid'] : $this->singleMsg['uid'];
			$topLevelID = $this->singleMsg['toplevel_uid'] ? $this->singleMsg['toplevel_uid'] :$this->singleMsg['uid'];
			if ($this->postvars['isreply']) $replyID = $this->postvars['isreply'];
		}
		else {
			$replyID = 0;
			$topLevelID = 0;
		}
		if (!$catVal && $this->singleMsg && ($isComment || ($topLevelUID != 0))) {
			$catVal = $this->singleMsg['category'];
		}

		// fill in hidden vars field
		$markerArray['###HIDDEN_VARS###'] = '<input type="hidden" name="tx_wecdiscussion[replyForm]" value="'.($isComment ? '2' : '1').'"/>
			<input type="hidden" name="tx_wecdiscussion[useruid]" value="'.$this->userID.'"/>
			<input type="hidden" name="tx_wecdiscussion[category]" value="'.$catVal.'"/>
			<input type="hidden" name="saved_category" value="'.$catVal.'"/>
			<input type="hidden" name="no_cache" value="1"/>';
		if (strcmp($this->showDay,date("mdy"))) // if not today, then save date here
			$markerArray['###HIDDEN_VARS###'] .= '<input type="hidden" name="tx_wecdiscussion[show_date]" value="'.$this->showDay.'"/>';

		if ($isComment) {
			// add a fake subject for the comment so will not fail
			$markerArray['###HIDDEN_VARS###'] .= '<input type="hidden"  name="tx_wecdiscussion[subject]" value="Re:'.$this->singleMsg['subject'].'"/>
				<input type="hidden" name="tx_wecdiscussion[isComment]" value="1"/>';
			// add saved vars for reply_uid and toplevel_uid
			$markerArray['###HIDDEN_VARS###'] .= '<input type="hidden" name="tx_wecdiscussion[reply_uid]" id="reply_uid_comment" value="'.$replyID.'"/><input type="hidden" name="tx_wecdiscussion[toplevel_uid]" id="toplevel_uid_comment" value="'.$topLevelID.'"/>';
		}
		else { // add saved vars for reply_uid and toplevel_uid
			$markerArray['###HIDDEN_VARS###'] .= '<input type="hidden" name="tx_wecdiscussion[reply_uid]" id="reply_uid_discussion" value="'.$replyID.'"/><input type="hidden" name="tx_wecdiscussion[toplevel_uid]" id="toplevel_uid_discussion" value="'.$topLevelID.'"/>';
		}


		// if a reply, do not allow to change category
		if ($replyID) {
			$markerArray['###VALUE_CATEGORY###'] = $this->categoryListByUID[$catVal];
		}
		else {
			// setup category
			$markerArray['###VALUE_CATEGORY###'] = $this->chooseCategory(3, $catVal);

			// show new category IF admin or main post person
			if ($this->config['can_create_category'] && (($this->config['type'] == 2) || ($this->config['type'] == 5)) && !$replyID) {
				$newCat = (t3lib_div::_POST('new_category')) ? t3lib_div::_POST('new_category') : $this->postvars['newcategory'];
				$hasNewCategory = (($editID || $this->formErrorText) && $newCat) ? true : false;
				$markerArray['###CREATE_NEW_CATEGORY###'] = (!$hasNewCategory)  ? '<span id="create_new_category" style="display:inline;">
						<a href="#" onclick="document.getElementById(\'create_new_category\').style.display=\'none\';document.getElementById(\'new_category_value\').style.display=\'block\';return false;">'.$this->pi_getLL("create_new_category","Create new category").'</a></span>
						<span id="new_category_value" style="display:none;">' :
						'<span id="new_category_value">';
				$markerArray['###CREATE_NEW_CATEGORY###'] .= $this->pi_getLL('new_category','New category:') . '<input type="text" name="new_category" value="'.(($hasNewCategory) ? $newCat : "").'" /></span>';
			}

			// if editing and is not top level, then just make fixed
			if ($editID && $replyID) {
				$markerArray['###VALUE_CATEGORY###'] = $this->categoryListbyUID[$catVal];
			}
		}

		// fill in if single view...
		if ($this->postvars['single'] || ($this->singleMsg && $this->singleMsg['reply_uid'])) {
			$markerArray['###VALUE_SUBJECT###'] = "RE:".$this->singleMsg['subject'];
			$markerArray['###SUBJECT_CLASS###'] = 'readonly="readonly" style="background:#eee;color:#333;"';
		}

		// if this is an edit, not a new entry, setup differently
		if ($editID) {
			$markerArray['###HIDDEN_VARS###'] .= '
				<input type="hidden" name="tx_wecdiscussion[editForm]" value="'.$editID.'"/>
				';
			// load and set values
			$markerArray['###VALUE_NAME###'] = $this->filledInVars['name'];
			$markerArray['###VALUE_EMAIL###'] = $this->filledInVars['email'];
			$markerArray['###VALUE_SUBJECT###'] = htmlspecialchars($this->filledInVars['subject']);
			$markerArray['###VALUE_MESSAGE###'] = htmlspecialchars($this->filledInVars['message']);
			$markerArray['###VALUE_IMAGE###'] = $this->filledInVars['image'];
			$markerArray['###VALUE_STARTTIME###'] = ($this->filledInVars['starttime']) ? $this->getStrftime($this->pi_getLL('date_format_startendtime', '%m-%d-%y'), $this->filledInVars['starttime']) : '';
			$markerArray['###VALUE_ENDTIME###']   = ($this->filledInVars['endtime']) ? $this->getStrftime($this->pi_getLL('date_format_startendtime', '%m-%d-%y'), $this->filledInVars['endtime']) : '';
			// for image, show image and let clear it
			if ($this->filledInVars['image']) {
				$markerArray['###VIEW_IMAGE###'] = $this->getImageURL($this->filledInVars['image']);
			}
			else {
				// remove clear image
				$subpartArray['###CLEAR_IMAGE###'] = '';
			}
			$markerArray['###MESSAGE_STYLE###'] = $this->pi_getLL('edit_message_class','');
			$markerArray['###FORM_HEADER###'] = $this->pi_getLL('form_edit_header', 'Edit Your Post:');
			if (!$this->postvars['ispreview'])
				$markerArray['###SUBMIT_BTN###'] = $this->pi_getLL('submit_edit_btn', 'Save');
		}
		else {
			// normal entry
			// Pre-fill in data if logged in FE user
			if ($GLOBALS['TSFE']->loginUser && !isset($this->postvars['name'])) {
				$markerArray['###VALUE_NAME###'] = $this->getUserName();
				$markerArray['###VALUE_EMAIL###'] = $GLOBALS['TSFE']->fe_user->user['email'];
			}
			else {
				if (isset($_COOKIE[$this->extKey."_".$this->pid_list.'_saveform'])) {
					$nameEmailDecrypt = base64_decode($_COOKIE[$this->extKey."_".$this->pid_list.'_saveform']);
					$neArray = explode('|',$nameEmailDecrypt);
					if (count($neArray) == 2) {
						$markerArray['###VALUE_NAME###'] = $neArray[0];
						$markerArray['###VALUE_EMAIL###'] = $neArray[1];
					}
				}
			}
			// if errors, then fill in any known values
			if (isset($this->postvars['name']) && $this->formErrorText) {
				if ($this->postvars['name']) $markerArray['###VALUE_NAME###'] = $this->postvars['name'];
				if ($this->postvars['email']) $markerArray['###VALUE_EMAIL###'] = $this->postvars['email'];
				if ($this->postvars['subject']) $markerArray['###VALUE_SUBJECT###'] = stripslashes($this->postvars['subject']);
				if ($this->postvars['message']) $markerArray['###VALUE_MESSAGE###'] = stripslashes($this->postvars['message']);
			}
			$markerArray['###MESSAGE_STYLE###'] = $this->pi_getLL('post_message_class','');
			$subpartArray['###CLEAR_IMAGE###'] = '';
		}
		$markerArray['###CLEAR_SUBJECT###'] = $this->pi_getLL('clear_subject', 'clear');

		$getvars = t3lib_div::_GET();
		if ($this->postvars['show_date']) $getvars['tx_wecdiscussion[show_date]'] = $this->postvars['show_date'];
		if ($this->postvars['show_cat'])  $getvars['tx_wecdiscussion[show_cat]'] = $this->postvars['show_cat'];
		unset($getvars['id']);
		unset($getvars['tx_wecdiscussion']['archive']);
		unset($getvars['tx_wecdiscussion']['ispreview']);
		unset($getvars['tx_wecdiscussion']['isreply']);
		unset($getvars['tx_wecdiscussion']['processmoderated']);
		unset($getvars['tx_wecdiscussion']['moderate']);
		unset($getvars['tx_wecdiscussion']['editMsg']);
		unset($getvars['tx_wecdiscussion']['edit_msg']);

		$markerArray['###ACTION_URL###'] = $this->getAbsoluteURL($this->id, $getvars, FALSE);

		// if using a fake form for spam control,this is used so that if extension sees it, the input will be discarded
		$markerArray['###INPUT_FAKE_FORM###'] = '<input name="ignore" type="hidden" value="1"/>';

		//
		// MARK ALL REQUIRED FIELDS
		// put a space for ALL fields (this is default)
		//--------------------------------------------------------------------------
		foreach ($this->db_showFields AS $req_field) {
			$markerArray['###FORM_'.strtoupper($req_field).'_REQUIRED###'] = '&nbsp;';
		}
		if ($this->categoryCount <= 1) $markerArray['###FORM_CATEGORY_REQUIRED###'] = '';

		// then mark all the required fields
		if (is_array($this->config['required_fields']) && count($this->config['required_fields']) > 0) {
			foreach ($this->config['required_fields'] AS $req_field) {
				$markerArray['###FORM_'.strtoupper($req_field).'_REQUIRED###'] = $this->pi_getLL('required_text_marker', '*');
			}
			$markerArray['###REQUIRED_TEXT###'] = $this->pi_getLL('show_required_text', '* = required field');
		}
		else {
			$subpartArray['###SHOW_REQUIRED_TEXT###'] = '';
		}

		//
		// TURN OFF ALL NON-DISPLAY FIELDS
		//--------------------------------------------------------------------------
		if (is_array($this->config['display_fields']) && count($this->config['display_fields']) > 0) {
			$fieldArray = array('name', 'email', 'subject', 'category', 'image', 'attachment', 'starttime', 'endtime', 'ipAddress');
			for ($i = 0; $i < count($fieldArray); $i++) {
				$fieldFound = false;
				foreach ($this->config['display_fields'] AS $display_field) {
					if (!strcmp($display_field, $fieldArray[$i])) {
						$fieldFound = true;
						break;
					}
				}
				// the field was not in display_list, so turn it off
				if (!$fieldFound) {
					$dMarker = '###SHOW_'.strtoupper($fieldArray[$i]).'###';
					$subpartArray[$dMarker] = '';
				}
			}
		}

		// turn off categories if none to show OR if a reply
		if ($this->postvars['single'] || ($this->singleMsg && $this->singleMsg['reply_uid']))
			$subpartArray['###SHOW_CATEGORY###'] = '';

		// Add Image Captcha Support...
		if (is_object($this->freeCap) && $this->useCaptcha) {
			$markerArray = array_merge($markerArray, $this->freeCap->makeCaptcha());
		} else {
			$subpartArray['###CAPTCHA_INSERT###'] = '';
		}
		if ($this->easyCaptcha) {
			$markerArray['###FORM_CAPTCHA_LABEL###'] = $this->pi_getLL('form_captcha_label','Enter words you see in the image');
			$markerArray['###EASY_CAPTCHA_IMAGE###'] =  '<img src="'.t3lib_extMgm::siteRelPath('captcha').'captcha/captcha.php" alt="" />';
		}
		else {
			$subpartArray['###SHOW_EASY_CAPTCHA###'] = '';
		}
		// Add Text-Captcha Support...
		if ($this->config['use_text_captcha'] && $this->useCaptcha) {
			$markerArray['###TEXT_CAPTCHA_LABEL###'] = $this->pi_getLL('textcaptcha_field','Are you a person?');
			$markerArray['###TEXT_CAPTCHA_FIELD###'] = '<input type="checkbox" name="tx_wecdiscussion[textcaptcha_value]" style="width:20px;" />';
		}
		else {
			$subpartArray['###SHOW_TEXT_CAPTCHA###'] = '';
		}


		// only allow top level posts to have email author replies
		if ($this->isComment || $isComment || $replyID || !$this->config['email_author_replies'] || ($this->userID && !$GLOBALS['TSFE']->fe_user->user['email'] && !$subpartArray['###SHOW_EMAIL###']) || (!$this->userID && !$subpartArray['###SHOW_EMAIL###'])) {
			$subpartArray['###SHOW_EMAIL_AUTHOR_REPLIES###'] = '';
		}
		// add hidden field if posting and this email_author_replies field is active
		if (!$isComment && !$replyID && $this->config['email_author_replies'] && $this->userID && !$subpartArray['###SHOW_EMAIL###'] && $GLOBALS['TSFE']->fe_user->user['email']) {
			$markerArray['###HIDDEN_VARS###'] .= '<input type="hidden" name="tx_wecdiscussion[email]" value="'.$GLOBALS['TSFE']->fe_user->user['email'].'"/>';
		}
		
		// Add Front-End htmlArea editing
		if($this->conf['RTEenabled'] && is_object($this->RTEObj) && $this->RTEObj->isAvailable()) {
			$this->RTEcounter++;
			$this->table = 'tx_wecdiscussion';
			$this->field = 'message';
			$this->formName = 'forumReplyForm';
			$this->PA['itemFormElName'] = 'tx_wecdiscussion[message]';
			$msg = "";

			if ($this->postvars['editMsg'] || $this->postvars['edit_msg'])
				$msg = $this->filledInVars['message'];
			else if ($this->formErrorText)
				$msg = $this->postvars['message'];
			else if ($this->postvars['ispreview'])
				$msg = $this->singleMsg['message'];
			$this->PA['itemFormElValue'] = $msg;
			$this->thePidValue = $GLOBALS['TSFE']->id;

			$this->RTEObj->RTEdivStyle  = $this->conf['RTEfontSize'] ? 'font-size:'.$this->conf['RTEfontSize'].';' : '';
			$this->RTEObj->RTEdivStyle .= $this->conf['RTEheight']   ? 'height:'.$this->conf['RTEheight'].';'      : '';
			$this->RTEObj->RTEdivStyle .= $this->conf['RTEwidth']    ? 'width:'.$this->conf['RTEwidth'].';'        : '';

			$RTEItem = $this->RTEObj->drawRTE($this, $this->table, $this->field, $row=array(), $this->PA, $this->specConf, $this->thisConfig, $this->RTEtypeVal, '', $this->thePidValue);
			$markerArray['###RTE_PRE_FORM###'] = $this->additionalJS_initial.'
				<script type="text/javascript">'. implode(chr(10), $this->additionalJS_pre).'
					</script>';
			$markerArray['###RTE_POST_FORM###'] = '
				<script type="text/javascript">'. implode(chr(10), $this->additionalJS_post).'
					</script>';
			$markerArray['###RTE_SUBMIT###'] = implode(';', $this->additionalJS_submit);
			$markerArray['###RTE_FORM_ENTRY###'] = $RTEItem;
			$subpartArray['###SHOW_MESSAGE_TEXTAREA###'] = '';
		}
		else {
			$subpartArray['###SHOW_MESSAGE_RTE###'] = '';
		}
		// then do the substitution with the template
		$formContent = $this->cObj->substituteMarkerArrayCached($templateFormContent, $markerArray, $subpartArray, array());
		// clear out any empty template fields
		$formContent = preg_replace('/###.*?###/', '', $formContent);

		//return "";
		return $formContent;
	}

	/**
	*==================================================================================
	*   Check for Valid Fields -- Make sure all fields filled out & if any errors in certain fields
	*
	* @return string  return either 0 (no errors) or a string containing the error messages
	*==================================================================================
	*/
	function checkForValidFields($isComment = 0) {
		$errorStr = '';
		if (is_array($this->config['required_fields'])) {
			$whichOne = 0;
			foreach ($this->config['required_fields'] AS $req_field) {
				// if this field = category and we either have a comment or we are creating a new category, skip checking if valid
				if (!strcmp($req_field,'category') && ($isComment || ($this->config['can_create_category'] && trim(t3lib_div::_POST('new_category')))))
					continue;
				if (empty($this->postvars[$req_field])) {
					$errorStr .= '<li> ' . $this->pi_getLL('quote_char','&quot;') . ucfirst($this->pi_getLL('form_'.$req_field)) . $this->pi_getLL('quote_char','&quot;') . ' '.$this->pi_getLL('form_required_blank') .'</li>';
				}
			}
			if ($whichOne > 0)
				$errorStr .= '<div>&nbsp;</div>';
		}
		if (isset($this->config['numlinks_allowed']) && ($isComment || !$this->config['only_check_comments'] || !$this->userID)) {
			$numLinksFound = 0;
			$numLinksAllowed = (int) $this->config['numlinks_allowed'];
			$msg = $this->html_entity_decode($this->postvars['message']);
			$msg = stripslashes($msg);
			// count and strip off all href
			$numLinksFound = preg_match_all('/<a[^>]*?href=[\'"](.*?)[\'"][^>]*?>(.*?)<\/a>/si',$msg,$matches);
			if ($numLinksFound > 0)
				$msg = preg_replace('/<a[^>]*?href=[\'"](.*?)[\'"][^>]*?>(.*?)<\/a>/si',"",$msg,-1);
			// count all http:// left
			preg_match_all("/http:\/\//isU",$msg, $matches, PREG_PATTERN_ORDER);
   			$numLinksFound += count($matches[0]);
			// now determine if too many or not
			if ($numLinksFound > $this->config['numlinks_allowed'])
				if ($numLinksAllowed > 0)
					$errorStr .= '<li>'.$this->pi_getLL('too_many_links','Too many links found -- only allowed ').$numLinksAllowed.'</li>';
				else
					$errorStr .= '<li>'.$this->pi_getLL('no_links_allowed','No links are allowed to be posted here.').'</li>';
		}
		if (!empty($this->postvars['email'])) {
			if (t3lib_div::validEmail($this->postvars['email']) == false) {
				$errorStr .= '<li> '.$this->pi_getLL('form_email').' ('.$this->pi_getLL('form_invalid_email', 'Invalid email format -- i.e., name@mail.com').")</li>\n";
			}
		}

		$canEdit = !$this->config['only_check_comments'] || !$this->userID;
		if (is_object($this->freeCap) && $this->useCaptcha && !$this->postvars['editForm']) {
			$response = trim($this->postvars['captcha_response']);
			if (!$this->freeCap->checkWord($response)) {
				// force reload so can reload image
				$GLOBALS['TSFE']->set_no_cache();
				$this->clearCache();
				$errorStr .= '<li>' . $this->pi_getLL('captcha_bad','Please try entering the text for the Image Check again.'). '</li>';
			}
		}
		if ($this->easyCaptcha && t3lib_extMgm::isLoaded('captcha')) {
			session_start();
			$captchaStr = $_SESSION['tx_captcha_string'];
			$_SESSION['tx_captcha_string'] = '';
			if (!$captchaStr || ($this->postvars['captcha_response'] != $captchaStr)) {
				$errorStr .= '<li>' . $this->pi_getLL('captcha_bad','Please try entering the text for the Image Check again.'). '</li>';
			}
		}

		if ($this->config['use_text_captcha'] && $this->useCaptcha && !$this->postvars['textcaptcha_value'] && !$this->postvars['editForm']) {
			$errorStr .= '<li>' . $this->pi_getLL('textcaptcha_bad','You need to fill out the "Are you a person?" field') .'</li>';
		}
		if ($this->postvars['starttime'] && (strlen(trim($this->postvars['starttime'])) != 8)) {
			$errorStr .= '<li>' . $this->pi_getLL('starttime_format_error','Start Time needs to be formatted in MM-DD-YY format. Include \'-\' in date.') . '</li>';
		}
		if ($this->postvars['endtime'] && (strlen(trim($this->postvars['endtime'])) != 8)) {
			$errorStr .= '<li>' . $this->pi_getLL('endtime_format_error','End Time needs to be formatted in MM-DD-YY format. Include \'-\' in date.') . '</li>';
		}
		if (strlen($errorStr))
			return $this->pi_getLL('form_error_header', 'ERROR IN FORM:<br />').'<ul>'.$errorStr.'</ul>';
		else
			return false;
	}

	/**
	*=====================================================================================
	*
	* Add a forum post to the database
	*
	*  This will save the reply to the database and then reload
	*
	* @param array  the variables passed in from the form
	* @return void
	*=====================================================================================
	*/
	function postToForum($passedInVars) {
		// extract all the valid fields out of what is passed in
		// clean for XSS attacks
		foreach ($this->db_fields AS $field) {
			if (isset($passedInVars[$field]))
				$postMsg[$field] = $this->removeXSS($passedInVars[$field]);
		}

		// do cleanup of old previews (if any)
		if ($this->config['allow_preview_before_post']) {
			$oneDayAgoTimeCheck = mktime() - (60 * 60 * 24);
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->postTable, 'post_datetime<='.intval($oneDayAgoTimeCheck).' AND pid IN(' . $GLOBALS['TYPO3_DB']->cleanIntList($this->pid_list) . ') AND moderationQueue=99', '');
			$count = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
			if ($count > 0) {
				$delListStr = "";
				$c=0;
				while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
					$delListStr .= $row['uid'];
					$c++;
					if ($count != $c)
						$delListStr .= ',';
				}
				$where = 'uid IN ('.$delListStr.')';
				$res = $GLOBALS['TYPO3_DB']->exec_DELETEquery($this->postTable, $where);
			}
		}
		// handle if RTE and transform content and is POST (not comment)
		$emailMsg = $postMsg['message'];
		if($this->conf['RTEenabled'] && is_object($this->RTEObj) && $this->RTEObj->isAvailable() && $postMsg['message'] && $this->showPostForm) {
			$pageTSConfig = $GLOBALS['TSFE']->getPagesTSconfig();
			$RTEsetup = $pageTSConfig['RTE.'];
			$this->thisConfig = $RTEsetup['default.'];
			$this->thisConfig = $this->thisConfig['FE.'];

			// if encoded with HTML then decode
			$postMsg['message'] = $this->html_entity_decode($postMsg['message']);
			$emailMsg = $postMsg['message'];

			// send to RTE to transform it
			$this->thePidValue = $GLOBALS['TSFE']->id;
			$postMsg['message'] = $this->RTEObj->transformContent('db',$postMsg['message'], 'tx_wecdiscussion', 'message', $postMsg, $this->specConf, $this->thisConfig, '', $this->thePidValue);
		}

		// grab if is an edit (as opposed to a new entry)
		$isEdit = $passedInVars['editForm'];
		$isPreview = t3lib_div::_POST('ForumPreviewBeforePost');
		// if no category, then clear
		if ($postMsg['category'] < 0)
			$postMsg['category'] = 0;

		// HANDLE IMAGE
		$img = 0;
		$imgName = $_FILES['tx_wecdiscussion']['name']['image'];
		$imgTmpName = $_FILES['tx_wecdiscussion']['tmp_name']['image'];
		if ($imgTmpName) {
			$this->fileFunc = t3lib_div::makeInstance('t3lib_basicFileFunctions');
			$imgName = $this->fileFunc->cleanFileName($imgName);
			$imgPath = PATH_site.'uploads/tx_wecdiscussion/';
			$imgUniqueName = $this->fileFunc->getUniqueName($imgName, $imgPath);
			$imgExt = substr($imgName, strrpos($imgName, '.') + 1);
			if (!t3lib_div::inList($GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'],strtolower($imgExt))) {
				$this->formErrorText = $this->pi_getLL('attached_image_type_error','The attached image type - '.$imgExt.' - is not allowed to be uploaded.');
				return false;
			}
			// move the image into uploaded path.
			move_uploaded_file($imgTmpName, $imgUniqueName);
			$imgPathInfo = pathinfo($imgUniqueName);
			$img = $imgName;
		}
		$postMsg['image'] = $img;

		// HANDLE ATTACHED FILE
		$attachFile = 0;
		$attachFileName = $_FILES['tx_wecdiscussion']['name']['attachment'];
		$attachFileTmpName = $_FILES['tx_wecdiscussion']['tmp_name']['attachment'];
		if ($attachFileTmpName) {
			$this->fileFunc = t3lib_div::makeInstance('t3lib_basicFileFunctions');
			$attachFileName = $this->fileFunc->cleanFileName($attachFileName);
			$attachFilePath = PATH_site.'uploads/tx_wecdiscussion/';
			$attachFileUniqueName = $this->fileFunc->getUniqueName($attachFileName, $attachFilePath);
			// first check to see if allowed file type. If not, return error
			if (!t3lib_div::verifyFilenameAgainstDenyPattern($attachFileUniqueName))	{
				$attachFileExt = substr($attachFileName, strrpos($attachFileName, '.') + 1);
				$this->formErrorText = $this->pi_getLL('attached_file_type_error','The attached file type - .'.$attachFileExt.' - is not allowed to be uploaded.');
				return false;
			}
			move_uploaded_file($attachFileTmpName, $attachFileUniqueName);
			$attachFilePathInfo = pathinfo($attachFileUniqueName);
			$attachFile = $attachFileName;
		}
		$postMsg['attachment'] = $attachFile;

		// check to make sure all the fields are valid
		$this->formErrorText = 0;
		if ($errStr = $this->checkForValidFields($postMsg['reply_uid'] > 0)) {
			$this->formErrorText = $errStr;
			return false;
		}

		// Convert starttime/endtime to valid dates (format MM-DD-YY)
		if ($postMsg['starttime']) {
			$postMsg['starttime'] = mktime(0,0,1,substr($postMsg['starttime'],0,2),substr($postMsg['starttime'],3,2),substr($postMsg['starttime'],6,2));
		}
		if ($postMsg['endtime']) {
			$postMsg['endtime'] = mktime(23,59,59,substr($postMsg['endtime'],0,2),substr($postMsg['endtime'],3,2),substr($postMsg['endtime'],6,2));
		}

		// if use captcha and only want it once, then set that here...
		if ($this->useCaptcha && $this->config['captcha_only_once']) { // && !isset($_COOKIE[$this->extKey."-".$this->pid_list.'-captcha'])) {
			setcookie($this->extKey."_".$this->pid_list.'_captcha','1',time()+60*60*24*30,"/");
		}

		// save name and email in cookie
		if ($postMsg['name'] || $postMsg['email']) {
			$nameEmailEncrypt = base64_encode($postMsg['name'] . '|' . $postMsg['email']);
			setcookie($this->extKey."_".$this->pid_list.'_saveform',$nameEmailEncrypt,time()+60*60*24*7);
		}

		// filter the message, if necessary
		//----------------------------------------------------
		if (($postMsg['reply_uid'] > 0) || !$this->config['only_check_comments'] || !$this->userID)  {
			$filter_subject = $this->filterPost($postMsg['subject']);
			$filter_message = $this->filterPost($postMsg['message']);
			$filter_name 	= $this->filterPost($postMsg['name']);
			if (($filter_message != '0') || ($filter_subject != '0') || ($filter_name != '0')) {
				switch ($this->config['filter_word_handling']) {
					case 'filter':
						if ($filter_message != '0') $postMsg['message'] = $filter_message;
						if ($filter_subject != '0')	$postMsg['subject'] = $filter_subject;
						if ($filter_name != '0')	$postMsg['name'] 	= $filter_name;
						break;
					case 'moderate':
						$postMsg['moderationQueue'] = 1;
						break;
					default:
						// discard post
						$this->submitFormResponse = $this->pi_getLL('filter_delete', 'Your post had unacceptable words in it and could not be posted.');
						return false;
				}
			}
		}
		// Test for Duplicate.
		// (duplicate defined: someone posted something with same text in last 'n' minutes)
		if (!$isEdit && !$isPreview) {
			if (($postDelayTime = $this->conf['duplicateCheckDelaySeconds']) && $postMsg['message']) {
				$previousTimeCheck = mktime() - ($postDelayTime * 60);
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->postTable, 'post_datetime>='.intval($previousTimeCheck).' AND pid IN('.$this->pid_list.') AND moderationQueue=0', '');
				if (mysql_error()) t3lib_div::debug(array(mysql_error(), 'MYSQL Check for duplicates error'));
					$count = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
				if ($count > 0) {
					while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
						if ((($row['useruid'] == $postMsg['useruid']) || !$postMsg['useruid']) && (strcasecmp($postMsg['message'], $row['message']) == 0)) {
							// found duplicate
							$errStr = $this->pi_getLL('duplicate_entry','This appears to be a duplicate post.');
							$this->formErrorText = $errStr;
							return false;
						}
					}
				}
			}
		}

		// if we have a new category and have privileges to add a new category, then add it to db
		if (($newCat = t3lib_div::_POST('new_category')) && strlen($newCat) && $this->config['can_create_category'] && !$isPreview) {
			// check to make sure no other similar categories
			$catFound = 0;
			for ($k = 0; $k < count($this->categoryList); $k++) {
				if (!strcmp($newCat,$this->categoryList[$k]['name'])) {
					$catFound = $this->categoryList[$k]['uid'];
					$postMsg['category'] = $catFound;
				}
			}
			// create a new category and store it in category table of db
			if (!$catFound) {
				$newCatQuery['name'] = $newCat;
				$newCatQuery['pid'] = $this->pid_list;
				$newCatQuery['crdate'] = mktime();
				$newCatQuery['tstamp'] = mktime();
				$newCatQuery['cruser_id'] = $this->userID;
				$newCatQuery['sys_language_uid'] = $GLOBALS['TSFE']->sys_language_uid;
				$res = $GLOBALS['TYPO3_DB']->exec_INSERTquery($this->categoryTable, $newCatQuery);
				$postMsg['category'] = $GLOBALS['TYPO3_DB']->sql_insert_id();
				$this->categoryListByUID[$postMsg['category']] = $newCat;
			}
		}

		// Save the message to the database
		//--------------------------------------------------------
		$postMsg['post_datetime'] = mktime();
		$postMsg['pid'] = $this->pid_list;
		$postMsg['ipAddress'] = t3lib_div::getIndpEnv('REMOTE_ADDR');
		$postMsg['sys_language_uid'] = $GLOBALS['TSFE']->sys_language_uid;

		// if need to moderate
		$needToModerate = $this->config['is_moderated'] &&
						  ((($this->config['moderate_exclude'] == 'none') || !$this->config['moderate_exclude']) ||
						   (($this->config['moderate_exclude'] == 'user') && !$this->userID) ||
						   (($this->config['moderate_exclude'] == 'admin') && !$this->isAdministrator)
					      );

		// translate breaks into lines for non-RTE
		if (!$this->conf['RTEenabled'] || !is_object($this->RTEObj) || !$this->RTEObj->isAvailable())
			$postMsg['message'] = str_replace("\n", '<br />', $postMsg['message']);

		// sets moderationQueue var
		if ($isPreview)
			$postMsg['moderationQueue'] = 99;
		else if ($needToModerate)
			$postMsg['moderationQueue'] = 1;
		else
			$postMsg['moderationQueue'] = 0;

		$postMsg['tstamp'] = mktime();

		if ($postMsg['toplevel_uid'] || !$this->config['email_author_replies'])
			$postMsg['email_author_replies'] = 0;

		if (!$isEdit) {
			// ADD A NEW RECORD
			$postMsg['crdate'] = mktime();
			$res = $GLOBALS['TYPO3_DB']->exec_INSERTquery($this->postTable, $postMsg);
			if (mysql_error()) t3lib_div::debug(array(mysql_error(), $postMsg));
			$newMsgID = $GLOBALS['TYPO3_DB']->sql_insert_id();
		} else {
			// IF EDIT, JUST DO AN UPDATE
			$where = 'uid='.$isEdit;
			$postMsg['post_lastedit_time'] = mktime(); // update the last editted time
			unset($postMsg['post_datetime']);
			if ($passedInVars['saved_image_file'] && !$postMsg['image'])  {
				$postMsg['image'] = $passedInVars['saved_image_file'];
			}
			$newMsgID = $isEdit;
			$res = $GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->postTable, $where, $postMsg);
		}

		// if moderated, then send to moderators and let them know
		if ($postMsg['moderationQueue'] == 1) {
			$this->submitFormResponse = $this->pi_getLL('filter_moderate', 'Your post has been sent to the moderator.');
			$this->sendToModerators($postMsg['name'], $postMsg['email'], $postMsg['subject'], $postMsg['message']);
			return; // so can show message about being moderated
		}

		// alert subscribers of post
		// and translate to html for sending out in email
		if (!$isPreview) {
			$sendMsg[0] = $postMsg;
			if ($emailMsg)
				$sendMsg[0]['message'] = $emailMsg;
			else
				$sendMsg[0]['message'] = $this->html_entity_decode($sendMsg[0]['message']);

			$this->sendoutMessages($sendMsg);
		}

		// clear plugin cache
		$this->clearCache();

		// now reload the page
		//--------------------------------------------------------
		// will retrieve all GET vars (so can be included on a page with other elements)
		$getvars = t3lib_div::_GET();
		if ($this->postvars['show_date']) $getvars['tx_wecdiscussion']['show_date'] = $this->postvars['show_date'];
		if ($this->postvars['show_cat']) $getvars['tx_wecdiscussion']['show_cat'] = $this->postvars['show_cat'];
		unset($getvars['id']); // if 'id' was included, remove it since getPageLink will generate 'id'
		// unset all these in case some server configs do a GET with POSTvars
		unset($getvars['tx_wecdiscussion']['editMsg']);
		unset($getvars['tx_wecdiscussion']['edit_msg']);
		unset($getvars['tx_wecdiscussion']['archive']);
		unset($getvars['tx_wecdiscussion']['replyForm']);
		unset($getvars['tx_wecdiscussion']['message']);
		unset($getvars['tx_wecdiscussion']['reply_uid']);
		unset($getvars['tx_wecdiscussion']['toplevel_uid']);
		unset($getvars['tx_wecdiscussion']['category']);
		unset($getvars['tx_wecdiscussion']['subject']);
		unset($getvars['tx_wecdiscussion']['email']);
		unset($getvars['tx_wecdiscussion']['name']);
		unset($getvars['tx_wecdiscussion']['useruid']);
		unset($getvars['tx_wecdiscussion']['ispreview']);
		unset($getvars['tx_wecdiscussion']['single']);
		unset($getvars['tx_wecdiscussion']['newcategory']);
		unset($getvars['tx_wecdiscussion']['showreply']);
		if (!$isPreview)
			unset($getvars['tx_wecdiscussion']['isreply']);
		if ($postMsg['reply_uid'] && !$isPreview)
			$getvars['tx_wecdiscussion']['showreply'] = $postMsg['reply_uid'];
		if ($isPreview)
			$getvars['tx_wecdiscussion']['ispreview'] = $newMsgID;
		if ($isPreview && $newCat)
			$getvars['tx_wecdiscussion']['newcategory'] = htmlspecialchars($newCat);

		$gotoURL = $this->pi_getPageLink($GLOBALS['TSFE']->id, '', $getvars);
		header('Location: '.t3lib_div::locationHeaderUrl($gotoURL));
	}


	/**
	*=====================================================================================
	*
	* CAN EDIT A MESSAGE?
	*
	*  Determine if can edit/update a message post based on rights and privileges
	*
	*  @param integer  message UID
	*  @return boolean  =true if can edit a post (has access rights) or =false if cannot
	*=====================================================================================
	*/
	function canEditPost($msgID) {
		// Load Original Message from Database
		//
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->postTable, 'uid = '.intval($msgID), '');
		if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			// Check user id and make sure can edit
			if (($row['useruid'] != $this->userID) && !$this->isAdministrator) {
				return false;
			}
			// Fill in array with values
			foreach ($this->db_fields as $fi) {
				if ($row[$fi] != NULL)
					$this->filledInVars[$fi] = $this->html_entity_decode(stripslashes($row[$fi]));
			}
			$this->filledInVars['message'] = str_replace('<br />', "\n", $this->filledInVars['message']);
			$this->filledInVars['uid'] = $msgID;
		} else {	// not found
			return false;
		}

		return true;
	}

	/**
	*=====================================================================================
	*
	* DELETE A MESSAGE FROM DISCUSSION FORUM
	*
	*  Will only delete if admin privileges OR if owner
	*
	*  This is a somewhat complex (and ugly) way to handle deleting from a tree, but
	*  most of the code is for special cases. And since deleting is rarer...this should be
	*  better.
	*
	*  @param integer  the message ID to be deleted
	*  @param boolean  if should force delete of comments underneath (admin only allowed)
	* @return boolean  if delete is successful or not
	*=====================================================================================
	*/
	function deletePost($deleteMsgID, $forceDeleteComments=false) {
		$delMsgID = intval($deleteMsgID);
		$params = t3lib_div::_GET();

		// 1. GRAB ALL INFO FROM SOON-TO-BE-DELETED MESSAGE
		//---------------------------------------------------------------------
		$where = 'uid=' . $delMsgID;
		if (!$this->isAdministrator)
			$where .= ' AND useruid='.intval($this->userID);
		if ($forceDeleteComments && $this->isAdministrator) {
			$where = 'uid=' . $delMsgID . ' OR toplevel_uid=' . $delMsgID;
		}
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->postTable, $where, '', "", '');
		if (mysql_error()) t3lib_div::debug(array(mysql_error(), "SELECT * FROM ".$this->postTable.' WHERE '.$where));
		$count = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		if ($count == 0)
			return false; // no message found, so invalid delete

		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		$toplevel_uid = $row['toplevel_uid'];
		$reply_uid = $row['reply_uid'];
		$delMsgCat = $row['category'];

		// 2. TRY TO DELETE THE MESSAGE (Only Delete If IsAdmin OR IsOwner)
		//---------------------------------------------------------------------
		$delMsg['deleted'] = 1; // mark this deleted
		$res2 = $GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->postTable, $where, $delMsg);
		if (mysql_error()) {
			t3lib_div::debug(array(mysql_error(), 'DELETE FROM '.$this->postTable.' WHERE '.$where));
			return false; // want to return if delete not successful
		}

		// 3. HANDLE ANY CHILD POSTS (either fix them up or delete)
		//----------------------------------------------------------------------
		// grab all children
		$allChildArray = array();
		$allChildUIDList = '';
		$directChildArray = array();
		$directChildUIDList = '';
		$thisChildList = array($delMsgID);
		// keep looking until cannot find any more children
		while (1) {
			$curChildList = implode(',', $thisChildList);
			$where = 'reply_uid IN (' . $curChildList . ')';
			$where .= $this->cObj->enableFields($this->postTable);
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->postTable, $where, '', '', '');
			if (mysql_error()) t3lib_div::debug(array(mysql_error(), "SELECT * FROM ".$this->postTable.' WHERE '.$where.' ORDER BY '.$order_by));
			$count = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
			if (!$count)
				break;

			$thisChildList = array();
			$addDirect = (empty($allChildArray)) ? TRUE : FALSE;

			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				array_push($allChildArray, $row);
				if (!empty($allChildUIDList)) $allChildUIDList .= ',';
				$allChildUIDList .= $row['uid'];
				array_push($thisChildList, $row['uid']);
				if ($addDirect) {
					array_push($directChildArray, $row);
					if (!empty($directChildUIDList)) $directChildUIDList .= ',';
					$directChildUIDList .= $row['uid'];
				}
			}
		}
		// if no child posts, do nothing
		if (!count($allChildArray)) {
			;
		}
		// Delete all children messages if admin function set
		else if ($forceDeleteComments) {
			$delMsg['deleted'] = 1; // make this so deleted
			$delUIDs =$allChildUIDList;
			$where = 'uid IN (' . $delUIDs . ')';
			$res2 = $GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->postTable, $where, $delMsg);
			if (mysql_error()) {
				t3lib_div::debug(array(mysql_error(), 'DELETE FROM '.$this->postTable.' WHERE '.$where));
				return false; // want to return if delete not successful
			}
		}
		else {
			// b. set direct children reply_uid to reply_uid of original post
			$childUIDs = $directChildUIDList;
			$setVar['reply_uid'] = $reply_uid;
			if ($toplevel_uid == 0) {
				$setVar['toplevel_uid'] = 0;
			}
			$where = 'uid IN (' . $childUIDs . ')';
			$res2 = $GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->postTable, $where, $setVar);
			if (mysql_error()) { t3lib_div::debug(array(mysql_error(), 'UPDATE '.$this->postTable.' WHERE '.$where)); }
			// c. if deleted toplevel_uid=0, set any children and sub-children to new toplevel_uid
			if ($toplevel_uid == 0) {
				// for each child, set sub-children toplevel_uid to that id
				for ($i = 0; $i < count($directChildArray); $i++) {
					$topChild = $directChildArray[$i];
					$setTopLevel = array();
					for ($j = 0; $j < count($allChildArray); $j++) {
						$thisChild = $allChildArray[$j];
						if (!in_array($directChildArray,$thisChild)) {
							if ($topChild['uid'] == $thisChild['reply_uid']) {
								array_push($setTopLevel, $thisChild['uid']);
							}
						}
					}
					if (count($setTopLevel)) {
						$setUIDs = implode(',', $setTopLevel);
						$setVar2['toplevel_uid'] = $topChild['uid'];
						$where = 'uid IN (' . $setUIDs . ')';
						$res3 = $GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->postTable, $where, $setVar2);
						if (mysql_error()) { t3lib_div::debug(array(mysql_error(), 'UPDATE '.$this->postTable.' WHERE '.$where)); }
					}
				}
			}
		}

		// 3. SEE IF ANY IN CATEGORY LEFT...
		//-----------------------------------------------------
		if ($delMsgCat) {
			$where = 'category=' . $delMsgCat;
			$where .= $this->cObj->enableFields($this->postTable);
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->postTable, $where, '', '', '');
			$count = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
			if (!$count) {
				$delMsg['deleted'] = 1;
				$where = 'uid =' . $delMsgCat;
				$res2 = $GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->categoryTable, $where, $delMsg);
				// remove from URL params, if there
				unset($params['tx_wecdiscussion']['show_cat']);
			}
		}

		// 4. CLEAR PLUGIN CACHE
		//------------------------------------------------------
		$this->clearCache();

		// 5. FINALLY, RELOAD THE PAGE
		//------------------------------------------------------
		if (strcmp($this->showDay,date("mdy")))  $params['tx_wecdiscussion']['show_date'] = $this->showDay;
		$pageID = $params['id'] ? $params['id'] : $GLOBALS['TSFE']->id;
		unset($params['id']);
		unset($params['tx_wecdiscussion']['deleteMsg']);
		unset($params['tx_wecdiscussion']['deleteAllMsg']);
		$gotoURL = $this->pi_getPageLink($pageID, '', $params);
		header('Location: ' . t3lib_div::locationHeaderUrl($gotoURL));
	}

	/**
	*==================================================================================
	*  FILTER THE POST
	*
	*  Check for filter words and if found, run the filter.
	*
	* @param  string $msgText  text of message to filter
	* @return string text of message if filtered or '0' if no need to filter
	*==================================================================================
	*/
	function filterPost($msgText) {
		$filterWordList = trim($this->config['filter_wordlist']);

		// if * in filter word list, then use the default. This supports adding other words to the * default
		if ($filterWordList == '*' || (!(($all = strpos($filterWordList, '*')) === false))) {
			$newBadwordList = strrev($this->conf['spamWords']);
			if ($this->conf['addSpamWords']) {
				if (strlen($newBadwordList))
					$newBadwordList .= ',';
				$newBadwordList .= $this->conf['addSpamWords'];
			}
			if (strlen($filterWordList) > 1) {
				$start = $all;
				$end = $all+1;
				if (!(strpos($filterWordList, '*,') === false)) // if it is *, then remove both
					$end++;
				if (!(strpos($filterWordList, ',*') === false)) // if it is ,* then remove both
					$start--;
				$filterWordList = substr($filterWordList, 0, $start) . substr($filterWordList, $end);
				$filterWordList .= ','.$newBadwordList;
			}
			else
				$filterWordList = $newBadwordList;
		}
		else if (strlen($filterWordList) <= 1)
			return '0'; // if empty, then return ok

		$filterWordArray = explode(',', $filterWordList);
		$filterCount = 0;
		foreach ($filterWordArray as $checkWord) {
			if (strlen($checkWord) && preg_match('/' . $checkWord . '/', $msgText)) {
				$filterWord = strtolower($checkWord);
				$newWord = substr($filterWord, 0, 1).str_repeat('*', strlen($filterWord)-1);
				$msgText = preg_replace('/' . $filterWord . '/', $newWord, $msgText);
				$filterCount++;
			}
		}
		// if none to filter then return ok
		if (!$filterCount)
			return '0';

		// return new message text
		return $msgText;
	}

	/**
	*==================================================================================
	*  DISPLAY PREVIEW
	*
	*  Show a preview of the posts of this discussion page
	*
	* @param  integer  $num number to display (default is 5)
	* @return  string  text of last # previews formatted according to template
	*==================================================================================
	*/
	function displayPreview($num = 5) {
		$preview_content = '';
		$preview_entries = '';
		$numToPreview = $this->config['num_previewRSS_items'] ? $this->config['num_previewRSS_items'] : $num;
		$previewLen = $this->config['preview_length'] ? $this->config['preview_length'] : 255;
		$previewPID = $this->pid_list;
		$previewBackPID = $this->config['previewRSS_backPID'] ? $this->config['previewRSS_backPID'] : $this->pid_list;
		$templatePreviewContent = $this->cObj->getSubpart($this->templateCode, '###TEMPLATE_PREVIEW###');
		$templatePreviewEntry = $this->cObj->getSubpart($templatePreviewContent, '###PREVIEW_ENTRY###');
		$templatePreviewDisplay = $this->cObj->getSubpart($templatePreviewContent, '###PREVIEW_DISPLAY###');

		$order_by = 'post_datetime DESC';
		$where = 'pid IN (' . $GLOBALS['TYPO3_DB']->cleanIntList($previewPID) . ')';
		if ($this->showCategory)
			$where .= ' AND category='.intval($this->showCategory);
		if (!$this->config['preview_allow_replies']) $where .= ' AND toplevel_uid=0';
		$where .= ' AND moderationQueue=0';
		$where .= $this->cObj->enableFields($this->postTable);
		$where .= ' AND '.$this->setWhereDate($this->config['display_amount'], $this->showDayTS);
		// handle languages
		$lang = ($l = $GLOBALS['TSFE']->sys_language_uid) ? $l : '0,-1';
		$where .= ' AND sys_language_uid IN ('.$lang.') ';
		$limit = $numToPreview;
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->postTable, $where, '', $order_by, $limit);
		if (mysql_error()) t3lib_div::debug(array(mysql_error(), 'SELECT "*" FROM '.$this->postTable.' WHERE '.$where.' ORDER BY '.$order_by.' LIMIT '.$limit));

		$count = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		if ($count == 0) {
			// If no messages, give a blank message
			$templatePreviewNoEntry = $this->cObj->getSubpart($templatePreviewContent, '###PREVIEW_NOENTRY###');
			$markerArray['###NO_ENTRY_MESSAGE###'] = $this->pi_getLL('preview_none', 'There are no recent messages posted.');
			$preview_entries .= $this->cObj->substituteMarkerArrayCached($templatePreviewNoEntry, $markerArray, array(), array());
		} else {
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$subpartMarker = array();
				$urlParameters = array();
				// grab the message and show...
				$markerArray['###SUBJECT###'] = stripslashes($row['subject']);
				$markerArray['###POST_NAME###'] = $row['name'];
				$markerArray['###POST_DATE###'] = $this->getStrftime($this->pi_getLL('date_format', '%b %d, %Y'), $row['post_datetime']);
				$markerArray['###POST_DATETIME###'] = $this->getStrftime($this->pi_getLL('datetime_format', '%b %d, %Y %I:%M%p'), $row['post_datetime']);
				$markerArray['###ON_TEXT###'] = $this->pi_getLL('on_text', 'on');
				$markerArray['###POSTEDBY_TEXT###'] = $this->pi_getLL('posted_by', 'Posted By:');
				$markerArray['###IMAGE###'] = $row['image'] ? $this->getImageURL($row['image']) : '';
				if (!$row['image']) $subpartMarker['###PREVIEW_SHOWIMAGE###'] = '';

				if ($this->config['allow_single_view'] != 0)
					$urlParameters['tx_wecdiscussion']['single'] = $row['uid'];
				else
					$urlParameters['tx_wecdiscussion']['showreply'] = $row['uid'];
				$markerArray['###PREVIEW_LINK_BEGIN###'] = '<a href="'.$this->getAbsoluteURL($previewBackPID,$urlParameters).'">';
				$markerArray['###PREVIEW_LINK_END###'] = '</a>';

				$urlParameters2['tx_wecdiscussion']['showreply'] = $row['uid'];
				$markerArray['###PREVIEW_LINK_ALL_BEGIN###'] = '<a href="'.$this->getAbsoluteURL($previewBackPID,$urlParameters2).'">';
				$markerArray['###PREVIEW_LINK_ALL_END###'] = '</a>';

				// cut off message if needed
				$showMsg = $row['message'];
				if (($previewWrap = $this->conf['preview_stdWrap.']) && (is_array($previewWrap))) {
					if ($previewLen) {
						$previewWrap['crop'] = $previewLen . '|...|1';
					}
					$showMsg = str_replace('&nbsp;',' ',$showMsg);
					$showMsg = $this->cObj->stdWrap($this->html_entity_decode($showMsg,ENT_QUOTES), $previewWrap);
				}
				else {
					if (strlen($showMsg) > $previewLen) {
						// find first space and then cut there
						for ($i = $previewLen; $i > 0; $i--) {
							if ($showMsg[$i] == ' ') {
								$showMsg = substr($showMsg, 0, $i);
								$showMsg .= $this->pi_getLL('preview_bridge','...');
								break;
							}
						}
					}
					$showMsg = $this->html_entity_decode($showMsg, ENT_QUOTES);
				}
				$markerArray['###MESSAGE###'] = $showMsg;

				// see if comments # is in the template, and determine that if so
				if (strpos($templatePreviewEntry,'###VIEW_COMMENTS_NUM###') != FALSE) {
					if ($row['toplevel_uid'] != 0)
					 	$numComments = 0;
					else {
						$where = 'toplevel_uid='.$row['uid'];
						$where .= $this->cObj->enableFields($this->postTable);
						$res2 = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid', $this->postTable, $where, '', '','');
						$numComments = $GLOBALS['TYPO3_DB']->sql_num_rows($res2);
					}
					if ($numComments)
						$markerArray['###VIEW_COMMENTS_NUM###'] = $this->pi_getLL('viewcommentnum_preview_start') . $numComments . $this->pi_getLL('viewcommentnum_preview_end') . (($numComments > 1) ? $this->pi_getLL('viewcommentnum_preview_end_plural') : '');
					else
						$markerArray['###VIEW_COMMENTS_NUM###'] = '';
				}
				$preview_entries .= $this->cObj->substituteMarkerArrayCached($templatePreviewEntry, $markerArray, $subpartMarker, array());
			}
		}

		$markerArray2['###PREVIEW_ENTRIES###'] = $preview_entries;
		$preview_content = $this->cObj->substituteMarkerArrayCached($templatePreviewDisplay, $markerArray2, array(), array());


		return $preview_content;
	}

	/**
	*==================================================================================
	* Show archive links so can look at previous entries
	*
	*  @param integer	how to display (1 = list, 2 = dropdown, 3 = full page)
	*  @return string  	content to display the archive
	*==================================================================================
	*/
	function displayArchive($showDateTS, $how=1) {
		$markerArray = array();

		if (!$showDateTS)
			$showDateTS = mktime(12, 0, 0);

		// find page URL (usually only set if Archive is stand-alone)
		$pageID = $this->config['previewRSS_backPID'] ? $this->config['previewRSS_backPID'] : $this->id;

		// keep any existing params, like Category, so can get archived categories
		$paramArray = t3lib_div::_GET();
		unset($paramArray['tx_wecdiscussion']['archive']);
		unset($paramArray['tx_wecdiscussion']['show_date']);
		unset($paramArray['tx_wecdiscussion']['pg']);
		unset($paramArray['tx_wecdiscussion']['showreply']);

		// for list view and page view, we use a template.
		if ($how != 2) {
			$whichTemplate = ($how == 1) ? '###SHOW_ARCHIVE###' : '###TEMPLATE_ARCHIVE###';
			$archTemplate = $this->cObj->getSubpart($this->templateCode, $whichTemplate);
		}

		// Grab from Database all past content (default limit 300) -- just UID and date
		$selFields = 'uid,post_datetime';
		$where = 'pid IN(' . $GLOBALS['TYPO3_DB']->cleanIntList($this->pid_list) . ')';
		$where .= ' AND moderationQueue=0';
		$where .= ' AND toplevel_uid=0';
		$where .= $this->cObj->enableFields($this->postTable);
		// handle languages
		$lang = ($l = $GLOBALS['TSFE']->sys_language_uid) ? $l : '0,-1';
		$where .= ' AND sys_language_uid IN ('.$lang.') ';
		$limit = $this->conf['archiveLimit'] ? $this->conf['archiveLimit'] : 300;
		$order_by = 'post_datetime DESC';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($selFields, $this->postTable, $where, '', $order_by, $limit);
		if (mysql_error())
			t3lib_div::debug(array(mysql_error(), $res));
		$count = $GLOBALS['TYPO3_DB']->sql_num_rows($res);

		// Store all the entries by month
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$monthList[(int) strftime('%m', $row['post_datetime'])][(int)strftime('%Y', $row['post_datetime'])]['count']++;
		}
		
		$archiveContent = '';

		// if no archive, say so and return
		if ($count == 0) {
			$archiveContent .= $this->pi_getLL('no_archive', 'No archive available.');
			return $archiveContent;
		}

		if ($how == 3) {
			$GLOBALS['TSFE']->additionalHeaderData['wecdiscussion_js'] .= '<script type="text/javascript" src="' . t3lib_extMgm::siteRelPath('wec_discussion') . 'res/wec_discussion.js"></script>';
		}
		
		// Show archive by dropdown
		if ($how == 2) {
			$archiveContent .= '<select name="archive" id="tx_wecdiscussion_archive" size="1" onchange="if (this.selectedIndex) location.href=this.options[this.selectedIndex].value;">';
			$archiveContent .= '<option value="0" '.(!$this->postvars['archive'] ? 'selected="selected"' : '').'>'.$this->pi_getLL('archive_select','Select...').'</option>';
			$archiveContent .= '<option value="'.$this->pi_getPageLink($pageID, '',$paramArray).'">'.$this->pi_getLL('archive_view_all', '[View current]').'</option>';
		}
		// Show archive by list
		else {
			$gotoURL = htmlspecialchars($this->pi_getPageLink($pageID,'',$paramArray));
			$archViewAll = '<li class="'.(!$this->postvars['archive'] ? 'isSelected' : '').'"><a href="'.$gotoURL.'">'.$this->pi_getLL('archive_view_all', '[View current]').'</a></li>';
			if ($how == 1)
				$archiveContent .= $archViewAll;
			else
				$markerArray['###SHOW_ALL_ARCHIVE_LINK###'] = $archViewAll;
		}

		// start at today's month/year for archive
		$curMonth = (int) strftime('%m', mktime());
		$curYear  = (int) strftime('%Y', mktime());
		$startYear = $curYear;
		$showDateYear = date('Y', $showDateTS);
		$noDataCount = 0; // if get noData for 24 months, then stop archive
		$archiveListCount = 0;
		$yearWrap = 0;
		while ($noDataCount < 24) {
			// if we have data for month, then show it in archive
			if ($monthList[$curMonth][$curYear]['count']) {
				// generate URL and put date as end of month so will show whole month
				$endDayOfMonthTS = mktime(12, 0, 0, $curMonth+1, 0, $curYear);
				$paramArray['tx_wecdiscussion']['show_date'] = date('mdy', $endDayOfMonthTS);
				$paramArray['tx_wecdiscussion']['archive'] = 1;
				$archiveMonthShow = $this->getStrftime($this->pi_getLL('archive_date_format','%B %Y'), $endDayOfMonthTS);
				$gotoURL = $this->pi_getPageLink($pageID, '', $paramArray);
				$gotoURL = htmlspecialchars($gotoURL);

				// figure out current place and if this is todays date
				$thisSelected = false;
				if ($this->postvars['archive'] && ($this->postvars['show_date'] == $paramArray['tx_wecdiscussion']['show_date']))
					$thisSelected = true;

				// now output the element
				if ($how == 2) { // dropdown list
					$gotoFullURL = $this->getAbsoluteURL($pageID, $paramArray);
					$archiveContent .= '<option value="'.$gotoFullURL.'" '.($thisSelected ? 'selected="selected"' :  '').'>'.$archiveMonthShow.'</option>';
				}
				else if ($how == 1) { // list item
					$archiveContent .= '<li class="'.($thisSelected ? 'isSelected' : '').'"><a href="'.$gotoURL.'">'.$archiveMonthShow.'</a></li>';
				}
				else if ($how == 3) { // full page
					$itemTemplate = $this->cObj->getSubpart($this->templateCode, '###ARCHIVE_ITEM###');
					$iMarker['###ITEM_URL###'] = $gotoURL;
					$iMarker['###ITEM_TEXT###'] = $archiveMonthShow;
					$iMarker['###ITEM_SEL_CLASS###'] = $thisSelected ? 'isSelected' : '';
					$archiveContent .= $this->cObj->substituteMarkerArrayCached($itemTemplate, $iMarker, array(), array());
				}
				$noDataCount = 0;
				$archiveListCount++;
				$lastDataYear = $curYear;
			}
			else
				$noDataCount++;

			// determine new date on 1 month back
			if ($curMonth-- <= 1) {
				// wrap to previous year
				$curMonth = 12;
				$curYear--;
				if ($how != 2) {
					if ($noDataCount > 12) {
						$archiveContent = $lastDataArchive;
					}
					else if ($yearWrap) {
						$archiveContent .= '</div>';
					}
					$lastDataArchive = $archiveContent; // save if have to restore because no data in the next year
					$dontShowYear = ($showDateYear != $curYear) && (($noDataCount > 12) || (($startYear - $curYear) > 1));
					$archiveContent .= '<li id="showarchive'.$curYear.'" style="display:block;">';
					$archiveContent .= '<a href="#" onclick="showHideMsg(\'archive'.$curYear.'\');showHideMsgInline(\'plus'.$curYear.'\');showHideMsgInline(\'minus'.$curYear.'\');return false;">' .
						'<div id="plus'.$curYear.'" style="display:' . ($dontShowYear ? 'inline' : 'none') . ';">'.$this->pi_getLL('archive_view_year','+ ').'</div>
						<div id="minus'.$curYear.'" style="display:' . ($dontShowYear ? 'none' : 'inline') . ';">'.$this->pi_getLL('archive_hide_year','- ').'</div>'. $curYear .
					'</a>';
					$archiveContent .= '</li>';
					$archiveContent .= '<div id="archive'.$curYear.'" class="showarchive" style="display:' . ($dontShowYear ? 'none' : 'block') . ';">';
					$yearWrap = $curYear;
				}
			}
		}

		// if have skipped past a year, then do not show year (because empty)
		if (($lastDataYear != $curYear) && $lastDataArchive) {
			$archiveContent = $lastDataArchive;
		}
		if ($how == 2) {
			$archiveContent .= '</select>';
		}
		else {
			if ($how == 3) {
				// add RSS feed icon
				$markerArray = $this->addSubscribeRSSFeed($markerArray);

				// add categories, if available (must add to template)
				if ($this->categoryCount) {
					$markerArray['###CHOOSE_CATEGORY_VERTICAL###'] = $this->chooseCategory(1); // vertical list
					$markerArray['###CHOOSE_CATEGORY_DROPDOWN###'] = $this->chooseCategory(2); // dropdown
				}
			}
			$markerArray['###DISPLAY_ARCHIVE###'] = $archiveContent;
			$markerArray['###ARCHIVE_HEADER###'] = $this->pi_getLL('archive_header', 'Archive:');
			$subpartArray['###ARCHIVE_ITEM###'] = '';
			$archiveContent = $this->cObj->substituteMarkerArrayCached($archTemplate, $markerArray, $subpartArray, array());
			$archiveContent = preg_replace('/###.*?###/', '', $archiveContent);
		}

		return $archiveContent;
	}

	/**
	*==================================================================================
	* Show a way to choose the category, if there are any categories
	*
	* @param integer $whichWay (1 = vertical list, 2 = vertical dropdown, 3 = select menu)
	* @param integer $whichCat which category
	* @return string $category_content text content for building category selector
	*==================================================================================
	*/
	function chooseCategory($whichWay, $whichCat = -1) {
		$category_content = '';
//		if ($this->categoryCount == 0) {
//			return $category_content;
//		}
		if ($whichCat == -1) $whichCat = $this->showCategory;

		$urlParams = t3lib_div::_GET();
		unset($urlParams['tx_wecdiscussion']['show_cat']);
		unset($urlParams['tx_wecdiscussion']['pg']);
		unset($urlParams['tx_wecdiscussion']['showreply']);
		$url = htmlspecialchars($this->pi_getPageLink($this->id, '', $urlParams));
		if ($whichWay == 1) {
			// vertical list
			$catSelCSS = '<li class="isSelected">';
			$catCSS = '<li>';

			$category_content .= '<div class="categoryList">';
			$category_content .= '<h4>' . $this->pi_getLL('category_header', 'Categories:').'</h4>';
			$category_content .= '<ul>';

			// first add VIEW ALL selection
			$category_content .= ($whichCat == 0) ? $catSelCSS : $catCSS;
			$category_content .= '<a href="' . $url . '">' . $this->pi_getLL('viewall_category', '[View All]') . '</a>';
			$category_content .= '</li>';

			// then show each category item in list
			for ($i = 0; $i < $this->categoryCount; $i++) {
				$catUID = $this->categoryList[$i]['uid'];
				$category_content .= ($whichCat == $catUID) ? $catSelCSS : $catCSS;
				$urlParams['tx_wecdiscussion[show_cat]'] = $catUID;
				$url = htmlspecialchars($this->pi_getPageLink($this->id, '', $urlParams));
				$category_content .= '<a href="'.$url.'">'.$this->categoryList[$i]['name'].'</a>';
				$category_content .= '</li>';

				if ($i != ($this->categoryCount -1))
					$category_content .= $this->pi_getLL('category_divider');
			}
			$category_content .= '</ul></div>';
		}
		else if ($whichWay == 2) {
			$url = $this->getAbsoluteURL($this->id, $urlParams);
			// vertical dropdown
			$category_content .= $this->pi_getLL('choose_category', 'Choose Category: ');
			$goURL = $url . (strpos($url, '?') ? "&tx_wecdiscussion[show_cat]" : "?tx_wecdiscussion[show_cat]"); // either put ? or & depending on what exists in URL already
			$category_content .= '<select name="categories" id="tx_wecdiscussion_category_dropdown" size="1" onchange="location.href=\''.$goURL.'=\'+this.options[this.selectedIndex].value;">';
			$category_content .= '<option value="0" '.(($whichCat == 0) ? 'selected="selected"' : "").'>'.$this->pi_getLL('viewall_category', 'View All').'</option>';
			for ($i = 0; $i < $this->categoryCount; $i++) {
				$catUID = $this->categoryList[$i]['uid'];
				$category_content .= '<option value="'.$catUID.'" '.(($whichCat == $catUID) ? 'selected="selected"' : "").'>'.$this->categoryList[$i]['name'].'</option>';
			}
			$category_content .= '</select>';
		}
		else if ($whichWay == 3) {
			// select one for entry form
			$category_content .= '<select name="tx_wecdiscussion[category]" id="tx_wecdiscussion_category" size="1">';
			if ($this->categoryCount == 0)
				$category_content .= '<option value="-1">'.$this->pi_getLL('no_category','none available').'</option>';
			else {
			  $category_content .= '<option value="0">'.$this->pi_getLL('select_category','Select category...').'</option>';

			  for ($i = 0; $i < $this->categoryCount; $i++) {
				$catUID = $this->categoryList[$i]['uid'];
				$category_content .= '<option value="'.$catUID.'" '.(($whichCat == $catUID) ? 'selected="selected"' : "").'>'.$this->categoryList[$i]['name'].'</option>';
			  }
			}
			$category_content .= '</select>';
		} else {
			$category_content = '';
		}

//		$category_content = '<span>'.$category_content.'</span>';
		$category_content = $category_content;

		return $category_content;
	}

	/**
	*==================================================================================
	*  Display the Subscribe Form: can subscribe or unsubscribe to this discussion forum
	*
	* @return string  content that contains the subscribe form
	*==================================================================================
	*/
	function displaySubscribeForm() {
		// extract subscribe form out of template
		//
		$templateFormContent = $this->cObj->getSubpart($this->templateCode, '###TEMPLATE_SUBSCRIBEFORM###');
		$subpartArray = array();

		// if any error messages, then display
		if ($this->submitFormResponse) {
			$markerArray['###FORM_ERROR###'] = $this->submitFormResponse;
		}
		else {
			$subpartArray['###SHOW_ERROR###'] = '';
		}

		// now fill in all the markers
		$substituteArray = array('name', 'email', 'submit_sub', 'submit_unsub', 'cancel');
		foreach ($substituteArray AS $marker) {
			$markerArray['###FORM_'.strtoupper($marker).'###'] = $this->pi_getLL('subscribeform_'.$marker);
		}
		$markerArray['###SUBSCRIBE_HEADER###'] = $this->config['subscribe_header'] ? $this->config['subscribe_header'] : $this->pi_getLL('subscribeform_header', 'Subscribe/Unsubscribe');
		if ($instr = $this->pi_getLL('subscribeform_instructions')) {
			$markerArray['###SUBSCRIBE_INSTRUCTIONS###'] = $instr;
		}
		else {
			$subpartArray['###SHOW_SUBSCRIBE_INSTRUCTIONS###'] = '';
		}

		$markerArray['###PID###'] = $this->id;
		$markerArray['###ACTION_URL###'] = $this->getAbsoluteURL($this->id);
		$markerArray['###HIDDEN_VARS###'] = '<input type="hidden" name="no_cache" value="1"/>';

		$getvarsE = t3lib_div::_GET();
		unset($getvarsE['id']);
		unset($getvarsE['tx_wecdiscussion']['sub']);
		$markerArray['###CANCEL_URL###'] = 'location.href=\''.$this->getAbsoluteURL($this->id, $getvarsE).'\'';

		// Pre-fill form data if FE user in logged in
		if ($this->postvars['email']) {
			$markerArray['###VALUE_NAME###'] = $this->postvars['name'];
			$markerArray['###VALUE_EMAIL###'] = $this->postvars['email'];
		}
		else if ($GLOBALS['TSFE']->loginUser) {
			$markerArray['###VALUE_NAME###'] = $this->getUserName();
			$markerArray['###VALUE_EMAIL###'] = $GLOBALS['TSFE']->fe_user->user['email'];
		}

		// then do the substitution with the template
		$formContent = $this->cObj->substituteMarkerArrayCached($templateFormContent, $markerArray, $subpartArray, array());

		// clear out any empty template fields
		$formContent = preg_replace('/###.*?###/', '', $formContent);

		return $formContent;
	}

	/**
	*==================================================================================
	*  Subscribe To Forum -- Add the user to the subscriber group to receive emails
	*
	*  @param string $userEmail the user email to subscribe
	* @return boolean if successful in subscribing
	*==================================================================================
	*/
	function subscribeToForum($userEmail,$userName='') {
		if (strlen($userEmail) < 2) {
			$this->submitFormResponse = $this->pi_getLL('subscribe_error1', 'Please provide your email address.');
			return false;
		}
		if (t3lib_div::validEmail($userEmail) == false) {
			$this->submitFormResponse = $this->pi_getLL('subscribe_error2', 'Please provide a valid email in the form name@web.com (ie. mothergoose@aol.com, jacksprat@yahoo.com)');
			return false;
		}

		// first check to see if email is already there
		if ($this->checkIfSubscribed($userEmail)) {
			$this->submitFormResponse = $this->pi_getLL('subscribe_error3', 'You are already subscribed to this group.');
			$this->isSubscribed = true;
			return true;
		}

		// adding the email to subscriber group
		$saveData['pid'] = $this->pid_list;
		$saveData['user_uid'] = $this->userID;
		$saveData['user_email'] = htmlspecialchars($userEmail);
		$saveData['user_name'] = $userName ? $userName : $this->userName;
		$insert = $GLOBALS['TYPO3_DB']->exec_INSERTquery($this->groupTable, $saveData);

		if (mysql_error()) {
			t3lib_div::debug(array(mysql_error(), $insert), 'mySQL Error in group update/insert');
			return false;
		}
		$this->submitFormResponse = $this->pi_getLL('subscribe_success', 'You are now subscribed to this group.');
		$this->isSubscribed = true;
		$this->postvars = 0; // clear out post vars
		return true;
	}

	/**
	* ==================================================================================
	* Check if subscribed to this email group
	*
	* ==================================================================================
	*
	* @param	string		$thisEmail email of person to check
	* @return	boolean		return if subscribed or not
	*/
	function checkIfSubscribed($thisEmail) {
		// first check to see if email or userID is already there
		$selectStr = 'pid IN (' . $GLOBALS['TYPO3_DB']->cleanIntList($this->pid_list) . ') ';
		$selectStr .= ' AND user_email=\''.$GLOBALS['TYPO3_DB']->quoteStr($thisEmail, $this->groupTable).'\'';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->groupTable, $selectStr, '');
		if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			return true;
		}
		return false;
	}

	/**
	*==================================================================================
	*  Unsubscribe From Group -- Remove the user from the subscriber group
	*
	*  @param string $userEmail the user email to subscribe
	* @return boolean if successful in unsubscribing from forum
	*==================================================================================
	*/
	function unsubscribeFromGroup($userEmail) {
		if (strlen($userEmail) < 2) {
			$this->submitFormResponse = $this->pi_getLL('subscribe_error1', 'Please provide your email address.');
			return false;
		}
		if (t3lib_div::validEmail($userEmail) == false) {
			$this->submitFormResponse = $this->pi_getLL('subscribe_error2', 'Please provide a valid email in the form name@web.com (ie. mothergoose@aol.com, jacksprat@yahoo.com)');
			return false;
		}

		$selectStr = 'user_email=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($userEmail) . ' AND pid IN (' . $GLOBALS['TYPO3_DB']->cleanIntList($this->pid_list) . ')';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->groupTable, $selectStr, '');
		if ($count = $GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
			$GLOBALS['TYPO3_DB']->exec_DELETEquery($this->groupTable, $selectStr);
			$this->submitFormResponse =  $this->pi_getLL('unsubscribe_success', 'You were successfully unsubscribed from this discussion forum.');
			$this->isSubscribed = false;
			return true;
		} else {
			$this->submitFormResponse = $this->pi_getLL('unsubscribe_error', 'We could not find your email -- you do not appear to be subscribed to this.').' <br />[email = '.$userEmail.']';
			return false;
		}

	}

	/**
	*==================================================================================
	*  Display the Abuse Form: can report of an inappropriate post
	*
	* @return string  content that contains the abuse form
	*==================================================================================
	*/
	function displayAbuseForm() {
		// extract abuse form out of template
		//
		$templateFormContent = $this->cObj->getSubpart($this->templateCode, '###TEMPLATE_REPORTABUSEFORM###');
		$subpartArray = array();

		// if any error messages, then display
		if ($this->submitFormResponse) {
			$markerArray['###FORM_ERROR###'] = $this->submitFormResponse;
		}
		else {
			$subpartArray['###SHOW_ERROR###'] = '';
		}

		// now fill in all the markers
		$substituteArray = array('name', 'email', 'text', 'submit_abuse', 'cancel');
		foreach ($substituteArray AS $marker) {
			$markerArray['###FORM_'.strtoupper($marker).'###'] = $this->pi_getLL('abuseform_'.$marker);
		}
		$markerArray['###ABUSE_HEADER###'] = $this->pi_getLL('abuseform_header', 'Report Abuse');
		$markerArray['###ABUSE_INSTRUCTIONS###'] = $this->pi_getLL('abuseform_instructions');
		$markerArray['###PID###'] = $this->id;
		$markerArray['###ACTION_URL###'] = $this->getAbsoluteURL($this->id);
		$markerArray['###HIDDEN_VARS###'] = '<input type="hidden" name="no_cache" value="1"/>';
		$markerArray['###HIDDEN_VARS###'] .= '<input type="hidden" name="msgid" value="'.$this->postvars['abs'].'"/>';

		$where = ' uid='.$this->postvars['abs'];
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->postTable, $where, '', '');
		if (mysql_error()) t3lib_div::debug(array(mysql_error(), 'SELECT "*" FROM '.$this->postTable.' WHERE '.$where.' ORDER BY '.$order_by.' LIMIT '.$limit));
		if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))
			$markerArray['###ABUSE_MESSAGE###'] = $this->pi_getLL('abuse_message_label','Message to report:') . '<br/>' . $row['message'];

		$getvarsE = t3lib_div::_GET();
		unset($getvarsE['id']);
		unset($getvarsE['tx_wecdiscussion']['abs']);
		$markerArray['###CANCEL_URL###'] = 'location.href=\''.$this->getAbsoluteURL($this->id, $getvarsE).'\'';
		// Pre-fill form data if FE user in logged in
		if ($this->postvars['email'])
			$markerArray['###VALUE_EMAIL###'] = $this->postvars['email'];
		else if ($GLOBALS['TSFE']->loginUser) {
			$markerArray['###VALUE_NAME###'] = $this->getUserName();
			$markerArray['###VALUE_EMAIL###'] = $GLOBALS['TSFE']->fe_user->user['email'];
		}

		// then do the substitution with the template
		$formContent = $this->cObj->substituteMarkerArrayCached($templateFormContent, $markerArray, array(), array());

		// clear out any empty template fields
		$formContent = preg_replace('/###.*?###/', '', $formContent);
		return $formContent;
	}

	/**
	*==================================================================================
	*  Report Abuse -- Send to moderators the reported abuse
	*
	*  @param array $postv
	* @return boolean if successful in subscribing
	*==================================================================================
	*/
	function sendReportAbuse($pVars) {
		// format message to send:
		$msg = $this->pi_getLL('abuse_email_header','This is a report of abuse in a forum message') . '<br/>';

		// grab message
		$where = ' uid=' . t3lib_div::_GP('msgid');
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->postTable, $where, '', '');
		if (mysql_error()) t3lib_div::debug(array(mysql_error(), 'SELECT "*" FROM '.$this->postTable.' WHERE '.$where.' ORDER BY '.$order_by.' LIMIT '.$limit));
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);

		// show reporter
		if (strlen($pVars['name']) || strlen($pVars['email'])) {
			if (strlen($pVars['name']))
				$msg .= $this->pi_getLL('abuse_email_name_label','Reporter Name: ') . $pVars['name'];
			if (strlen($pVars['email']))
			 	$msg .= '<br/>' . $this->pi_getLL('abuse_email_email_label','Reporter Email: ') . $pVars['email'];
		}
		else
			$msg .= $this->pi_getLL('abuse_email_unknown','Reporter Name Not Given');

		// add message
		$msg .= '<br/>';
		if (strlen($pVars['text']))
			$msg .= $this->pi_getLL('abuse_email_message',' Reporters message: ') . $pVars['text'] . '<br/>';
		$msg .= '<br/>';

		// link to offending post
		$urlParams['tx_wecdiscussion']['single'] = t3lib_div::_GP('msgid');
		$msg .= '<a href="' . $this->getAbsoluteURL($GLOBALS['TSFE']->id,$urlParams,TRUE) . '">' . $this->pi_getLL('abuse_email_link','View message in forum') . '</a>';

		$msg = $row['message'] . '<br/><br/>' . $msg;
		$this->sendToModerators($row['name'], $row['email'], $row['subject'], $msg, true);

		$this->postvars = 0; // clear out post vars
		return true;
	}

	/**
	*==================================================================================
	*  Send Out Email Messages: send the message(s) to all subscribers of this page
	*
	*  @param array $dataList array of messages to send out
	*  @param integer $total   total number of messages to send
	* @return void
	*==================================================================================
	*/
	function sendoutMessages($dataList, $total = 1) {
		// First compose the header info
		//---------------------------------------------------
		$emailFrom = $this->makeFromEmail();
		$thisDescription = $this->config['title'] ? $this->config['title'] : $this->pi_getLL('title_description', 'Discussion Board');
		$emailSubject = ($total > 1) ? $this->pi_getLL('email_subject_multiple', 'New Messages Posted') : $this->pi_getLL('email_subject', 'New Message Posted');

		// Next, create the email message content
		//----------------------------------------------------
		$emailBody = $emailSubject.' at: <a href = "'.$this->getAbsoluteURL($this->id,'',TRUE).'">'.$this->getAbsoluteURL($this->id,'',TRUE).'</a><br /><br />';
		if ($this->config['subscriber_emailHeader'])
			$emailBody .= '<br />' . $this->config['subscriber_emailHeader'] . '<br />';
		$adminText = '';
		for ($i = 0; $i < $total; $i++) {
			if ($i > 0)
				$emailBody .= $this->pi_getLL('email_msgSeparator','----------------------------------------------').'<br /><br />';

			$thisSubject = str_replace('&quot;', "\"", $dataList[$i]['subject']);
			$emailBody .= $this->pi_getLL('email_subjectText', 'Subject: ') . stripslashes($thisSubject) . "<br />";
			$emailBody .= $this->pi_getLL('email_postedbyText', 'Posted By: ') . $dataList[$i]['name'] . ($dataList[$i]['email'] ? ' <'.$dataList[$i]['email'].'>' : '')."<br />";
			if ($dataList[$i]['category']) {
				$catName = $this->categoryListByUID[$dataList[$i]['category']];
				if (!strlen($catName)) $catName = $dataList[$i]['category'];
				$emailBody .= $this->pi_getLL('email_categoryText', 'Category: ') . $catName . "<br />";
			}

			$emailBody .= '<br />';
			$thisMessage = $dataList[$i]['message'];

			if ($dataList[$i]['image']) {
				$thisMessage .= '<br />' . $this->getImageURL($dataList[$i]['image']) . '<br />';
			}
			if ($dataList[$i]['ipAddress']) {
				$adminText = $this->pi_getLL('ip_address_field','IP: ') . t3lib_div::getIndpEnv('REMOTE_ADDR');
			}

			$emailBody .= $this->pi_getLL('email_messageText', 'Message: ') . stripslashes($thisMessage) . "<br /><br />";
		}

		if ($this->config['subscriber_emailFooter']) {
			$emailBody .= "<br />" . $this->config['subscriber_emailFooter'];
		}

		// Build a list of any authors to email
		//-------------------------------------------------------------------------
		$emailedAuthor = array();
		$emailedAuthorCount = 0;
		if ($this->config['email_author_replies']) {
			for ($j = 0; $j < $total; $j++) {
				if ($thisID = $dataList[$j]['toplevel_uid']) {
					// grab the top level message and add email if author has requested it...
					$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('name,email,email_author_replies', $this->postTable, 'uid='.$thisID, '');
					if (($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) && $row['email_author_replies']) {
						$emailedAuthor[$j] = $row;
						$emailedAuthorCount++;
					}
				}
			}
		}
		// Now load in the email subscriber list
		//-------------------------------------------------------------------------
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->groupTable, 'pid IN(' . $GLOBALS['TYPO3_DB']->cleanIntList($this->pid_list) . ')', '');
		$count = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		$listCount = 0;
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			if ($row['user_email']) {
				$emailList[$listCount]['email'] = $row['user_email'];
				$emailList[$listCount]['name'] = $row['user_name'];
				$emailList[$listCount]['author'] = false;
				$listCount++;

				// if the current email is the author, then mark that we don't need to add them.
				for ($j = 0; $j < $emailedAuthorCount; $j++) {
					if ($row['user_email'] == $emailedAuthor[$j]['email']) {
						$emailedAuthor[$j] = 0;
						$emailedAuthorCount--;
					}
				}
			}
		}
		// if should email author the replies, then m
		if ($this->config['email_author_replies'] && $emailedAuthorCount) {
			for ($j = 0; $j < $emailedAuthorCount; $j++) {
				if ($emailedAuthor[$j]) {
					$emailList[$listCount]['email'] = $emailedAuthor[$j]['email'];
					$emailList[$listCount]['name']  = $emailedAuthor[$j]['name'];
					$emailList[$listCount]['author'] = true;
					$listCount++;
				}
			}
		}
		// FINALLY, send out the email to the whole list
		// do not send if only supposed to send top-level posts
		if (!$this->conf['sendOnlyPosts'] || ($row['reply_uid'] == 0)) {
			for ($i = 0; $i < $listCount; $i++) {
				$listEmailFrom = $emailFrom;
				$sendEmailBody = $emailBody;
				$sendEmailSubject = $emailSubject;

				// if no valid email, skip this person
				if (!$emailList[$i]['email'])
					continue;
				else if ($emailList[$i]['name'])
					$toName = $emailList[$i]['name'].' <'.$emailList[$i]['email'].'>';
				else
					$toName = $emailList[$i]['email'];

				// add footer for unsubscribing
				if (!$emailList[$i]['author']) {
					$urlPar['tx_wecdiscussion[sub]'] = 2;
					$urlPar['tx_wecdiscussion[email]'] = htmlspecialchars($emailList[$i]['email']);
					$sendEmailBody .= "<br />";
					if ($this->pi_getLL('email_unsubscribeText1')) {
						$sendEmailBody .= $this->pi_getLL('email_unsubscribeText1', '-------------------------------------------');
						$sendEmailBody .= "<br />";
					}
					if ($this->pi_getLL('email_unsubscribeText2'))
						$sendEmailBody .= $this->pi_getLL('email_unsubscribeText2', 'To be unsubscribed from this list and not receive these emails anymore, please click ');
					if ($unsub3Text = $this->pi_getLL('email_unsubscribeText3')) {
						$sendEmailBody .= '<a href="'.$this->getAbsoluteURL($this->id, $urlPar,TRUE).'">'.$unsub3Text."</a>";
						$sendEmailBody .= "<br />";
					}
				}
				else {
					$sendEmailSubject = $this->pi_getLL('email_subject_author_comment','Your Message Was Replied To');
				}
				// format for text and HTML
				$emailHTMLAndText = $this->formatTextHTMLEmail($sendEmailBody,$listEmailFrom);
				// send out to person
				mail($toName, $sendEmailSubject, $emailHTMLAndText, $listEmailFrom);
			}
		}

		// format for admin emails...
		if (strlen($adminText))
			$emailBody .= '<br />' . $adminText;
		$emailHTMLAndText = $this->formatTextHTMLEmail($emailBody,$emailFrom);

		// and then send out to notify email if listed
		if ($this->config['notify_email']) {
			mail($this->config['notify_email'], $this->pi_getLL('email_notify', 'NOTIFY:').$emailSubject, $emailHTMLAndText, $emailFrom);
		}

		// and send out to admins if requested (but if already moderated, this will be sent, so do not resend)
		if ($this->config['email_admin_posts'] && !$this->config['do_moderate']) {
			$adminList = $this->getAdmins($this->config['administrator_userlist'], $this->config['administrator_usergroup']);
			if (count($adminList)) {
				foreach ($adminList as $thisAdmin) {
					mail($thisAdmin['email'], $this->pi_getLL('email_notify', 'NOTIFY:').$emailSubject, $emailHTMLAndText, $emailFrom);
				}
			}
		}
	}

	/**
	*==================================================================================
	*  Make From Email: generate the 'from' portion of an email
	*
	*  @param string $emailParam	email address field
	*  @param string $nameParam		name field
	*  @return string			return the formatted from string
	*==================================================================================
	*/
	function makeFromEmail($emailParam=0,$nameParam=0) {
		$fromName = "";
		$fromEmail = "";
		if ($emailParam) {
			$fromEmail = $emailParam;
			$fromName  = $nameParam ? $nameParam : ($this->config['contact_name'] ? $this->config['contact_name'] : "");
		}
		else if ($this->config['contact_name'] && $this->config['contact_email']) {
			$fromName = $this->config['contact_name'];
			$fromEmail = $this->config['contact_email'];
		}
		else if ($this->config['contact_email']) {
			$fromEmail = $this->config['contact_email'];
		}
		else {
			$fromEmail = $this->pi_getLL('email_none','nobody@nowhere.org');
		}
		
		$origFrom = $fromEmail;	
		// we must put <> around it for name
		if (strpos($fromEmail,'<') === FALSE) {
			$fromEmail = ' <' . $fromEmail . '>';
		}

		$emailFromAddr = $fromName . " " . $fromEmail;
		$emailFrom = "From: ". $emailFromAddr . "\n";
		$emailFrom .= "Reply-To: ". $emailFromAddr . "\n";
		$emailFrom .= "Return-Path: ". $emailFromAddr . "\n";
		$emailFrom .= "Message-ID: <".time()."-" . trim($origFrom) . ">" . "\n";
		$emailFrom .= "X-Mailer: PHP v" . phpversion();

		return $emailFrom;
	}

	/**
	*==================================================================================
	*  Format Text and HTML Email: given an HTML string to send, format for text and HTML
	*      including putting in mime boundaries
	*
	*  @param string $emailHTML	email text that contains HTML
	*  @return string			return the formatted mail string that is ready to send
	*==================================================================================
	*/
    function formatTextHTMLEmail($emailHTML,&$emailFrom) {
       	$emailText = str_replace("\n", '', str_replace("\r\n", '', $emailHTML));

		// replace all <p> tags with <br> tags
      	$search = array('/(<p[^>]*>)/i', '/(<br[^>]*>)/i');
       	$replace = array("\n\n", "\n");
       	$emailText = preg_replace($search, $replace, $emailText);
//		$emailText = strip_tags($emailText);

		$charset = $GLOBALS['TSFE']->renderCharset ? $GLOBALS['TSFE']->renderCharset : 'iso-8859-1';

		$mime_boundary="==Multipart_Boundary_x".md5(mt_rand())."x";
		$emailFrom .= "\n" . "MIME-Version: 1.0" . "\n" .
					"Content-Type: multipart/alternative;\n".
					" boundary=\"".$mime_boundary."\"" . "\n";
		$emailHTMLAndText = "This is a multi-part message in MIME format. If you see this, you should upgrade to a mail reader that understands this format.\n".
			"\n--" . $mime_boundary . "\n" .
			"Content-type: text/plain;\n charset=\"" . $charset . "\""."\n" .
			"Content-transfer-encoding: 8bit\n\n".
			$emailText . "\n" .
			"\n--" . $mime_boundary . "\n" .
			"Content-type: text/html;\n charset=\"" . $charset . "\"". "\n" .
			"Content-transfer-encoding: 8bit\n\n" .
			$emailHTML .
			"\n\n" .
			"--" . $mime_boundary . "--";

		return $emailHTMLAndText;
	}

	/**
	*==================================================================================
	*  Send Out Multiple Messages: send out several messages to all subscribers of this page
	*
	*  @param array $msgUIDList array of message UIDs to send out
	*  @return void
	*==================================================================================
	*/
	function sendoutMultiMessages($msgUIDList) {
		// LOOKUP MESSAGES IN DATABASE
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->postTable, "uid IN (".$msgUIDList.")", '');
		if ($res && $GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
			// go through and send each message out
			$count = 0;
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				if ($this->conf['sendOnlyPosts'] && ($row['reply_uid'] != 0))
					continue;
				foreach ($this->db_fields AS $field) {
					$entry[$count][$field] = $row[$field];
				}
				$entry[$count]['message'] = $this->html_entity_decode($entry[$count]['message']);
				$count++;
			}
			if ($count)
				$this->sendoutMessages($entry, $count);
		}
		else {
			t3lib_div::debug('SELECT * FROM '.$this->postTable.' WHERE uid IN ('.$msgUIDList.') NOT FOUND');
		}
	}

	/**
	* ==================================================================================
	* Get Admins From List -- retrieves admin info from database. If already loaded, just returns
	*
	* ==================================================================================
	*
	* @param	string	$adminUserList comma-separated list of people
	* @param	string	$adminUserGroups usergroup list 
	* @return	array	$saveAdminList	array of name/email of admins.
	*/
	function getAdmins($adminUserList,$adminUserGroups) {
		if ($this->adminList)
			return $this->adminList;

		// 1. GRAB ALL USERLIST OF ADMIN IDs
		//--------------------------------------------------------------
		$saveAdminList = array();
		if ($adminUserList) {
			$adminList = t3lib_div::trimExplode(',', $adminUserList);
		}

		// now build a string with all #s or names in them separated by comma
		$queryStr = '';
		if (count($adminList)) {
			$listIn = '';
			for ($i = 0; $i < count($adminList); $i++) {
				$listIn .= "'".$adminList[$i]."'";
				if ($i != (count($adminList) - 1))
					$listIn .= ',';
			}
			if (ctype_digit(substr($adminList[0], 0, 1))) // if list of ID#s then look for those
				$queryStr = 'uid IN ';
			else
				$queryStr = 'username IN ';
			$queryStr .= '(' . $listIn . ") ";
		}
		
		// 2. SEE IF IN USERGROUP
		if ($adminUserGroups) {
			$adminUGArray = t3lib_div::trimExplode(',', $adminUserGroups);
			for ($i = 0; $i < count($adminUGArray); $i++) {
				$inUserGroup .= $GLOBALS['TYPO3_DB']->listQuery('usergroup',$adminUGArray[$i],'fe_users');
				if ($i < count($adminUGArray) - 1) 
					$inUserGroup .= ' OR ';
			}
			// add the userlist query if defined
			if ($queryStr) {
				$queryStr = "(( " . $queryStr . ") OR (" . $inUserGroup . "))";
			}
			// otherwise make this the main query
			else {
				$queryStr = "(" . $inUserGroup . ")";
			}
		}
		
		// make sure not deleted user
		$queryStr .= ' AND deleted=0';

		// 3. DO DB QUERY AND BUILD LIST
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'fe_users', $queryStr, '');
		$listCount = 0;
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$saveAdminList[$listCount]['email'] = $row['email'];
			$saveAdminList[$listCount++]['name'] = $row['name'];
		}

		return $saveAdminList;
	}

	/**
	*==================================================================================
	*  Send to Moderators -- will send the request to all moderators of this group
	*
	*  @param string $msgName the name of person who sent message
	*  @param string $msgEmail the email of person
	*  @param string $msgSubject the subject
	*  @param string $msgText the message text
	*  @return void
	*==================================================================================
	*/
	function sendToModerators($msgName, $msgEmail, $msgSubject, $msgText, $reportAbuse=false) {
		$msgSubject = stripslashes($msgSubject);
		$msgText = $this->html_entity_decode(stripslashes($msgText));

		$modList = $this->getAdmins($this->config['administrator_userlist'],$this->config['administrator_usergroup']);

		if (!count($modList))
			return;

		// COMPOSE THE EMAIL FROM CONTACT/OWNER
		if (!$reportAbuse)
			$emailBody = $this->pi_getLL("email_moderateMsgHeader", "Need to Moderate New Message:");
		else
			$emailBody = $this->pi_getLL("email_abuseMsgHeader", "Reported Message On Forum:");
		$emailBody .= "<br /><br />From: " . $msgName . ' <' . $msgEmail . "><br />Subject: " . $msgSubject . "<br /><br />Message: " . $msgText;

		// Add the link to moderate
//		$paramArray =  t3lib_div::_GET();
//		unset($paramArray['id']);
		if (!$reportAbuse) {
			$paramArray['tx_wecdiscussion[moderate]'] = 1;
			$gotoLink = $this->getAbsoluteURL($this->id,$paramArray,TRUE);
			$emailBody .= "<br /><br />".$this->pi_getLL('email_gotolink', 'Go to link: ').$gotoLink;
		}
		$emailFrom = $this->makeFromEmail();

		if (!$reportAbuse)
			$emailSubject = $this->pi_getLL('email_moderateMsg', 'Moderate Discussion Board Message');
		else
			$emailSubject = $this->pi_getLL('email_reportAbuseMsg','Reported Abuse on Discussion Board');

		$emailHTMLandText = $this->formatTextHTMLEmail($emailBody,$emailFrom);

		// FINALLY, send out the email to the whole moderator list
		for ($i = 0; $i < count($modList); $i++) {
			if ($modList[$i]['name'])
				$toName = $modList[$i]['name'].' <'.$modList[$i]['email'].'>';
			else
				$toName = '<'.$modList[$i]['email'].'>';
			mail($toName, $emailSubject, $emailHTMLandText, $emailFrom);
		}
	}

	/**
	*==================================================================================
	*  Moderate Messages -- Will show the form to allow the moderator to process the
	* moderation queue and either approve or disapprove messages
	*
	* @return string moderator content
	*==================================================================================
	*/
	function moderateMessages() {
		// grab all messages that can moderate
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->postTable, 'moderationQueue=1 AND pid IN(' . $GLOBALS['TYPO3_DB']->cleanIntList($this->pid_list) . ')', '');
		$count = 0;

		$db_fields = $this->db_showFields;
		array_push($db_fields, 'uid');

		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			foreach ($db_fields as $field) {
				$modList[$count][$field] = $row[$field];
			}

			$count++;
		}

		// display on screen with ability to mark as approve or delete
		$mod_content = '
			<script type="text/javascript">
			//<![CDATA[
			function SetAll(typename,val) {
				theForm = document.moderateMsgs;
				len = theForm.elements.length;
				for (var i = 0; i < len; i++) {
					if (theForm.elements[i].value.substr(0,3) == typename) {
						theForm.elements[i].checked=val;
					}
				}
			}
			//]]>
			</script>
			' ;

		// nothing below this
		$paramArray = t3lib_div::_GET();
		unset($paramArray['tx_wecdiscussion']['moderate']);
		unset($paramArray['tx_wecdiscussion']['ispreview']);
//		$paramArray['tx_wecdiscussion']['processmoderated'] = $count;
		$mod_content .= '<FORM class="moderatedForm" name="moderateMsgs" method="POST" action="'.$this->getAbsoluteURL($this->id, $paramArray).'">
			<INPUT type="hidden" name="tx_wecdiscussion[processmoderated]" value="'.$count.'"/>
			<TABLE border=0 cellspacing=0 cellpadding=0>
			<TR style="border-bottom:3px solid #444"><TD align=center valign=center class="headerField btnColumn">' . $this->pi_getLL('mod_approve','Approve') . '</TD>
			<TD align=center valign=center class="headerField btnColumn">' . $this->pi_getLL('mod_delete','Delete') . '</TD>
			<TD align=center valign=center class="headerField msgColumn">' . $this->pi_getLL('mod_messages','MODERATE MESSAGES') . '</TD>
			</TR>
			';

		// go through and place each entry in a box with all info
		//
		for ($i = 0; $i < $count; $i++) {
			$mod_content .= '<TR>
				<TD align=center><INPUT type="radio" name="modMsg' . $i . '" value="add' . $modList[$i]['uid'] . '"/></TD>
				<TD align=center><INPUT type="radio" name="modMsg' . $i . '" value="del' . $modList[$i]['uid'] . '"/></TD>
				<TD align=left class="msgCell">
				<span class="subjectLabel">' . $this->pi_getLL('mod_subject','SUBJECT') . '</span>:' .
					'<span class="textLabel"> ' . stripslashes($modList[$i]['subject']) . '</span>
					<div style="display:inline;margin-left:20px;">
					<span class="subjectLabel">FROM:</span>
					<span class="textLabel"> ' . $modList[$i]['name'] . ' (email=\''.$modList[$i]['email'].'\')</span>
					</div>
				<br />
				<span class="subjectLabel">' . $this->pi_getLL('mod_message','MESSAGE') . '</span>:
					<span class="textLabel"> ' .stripslashes($modList[$i]['message']) . '</span>
				<br />
				';
			$small_fields = array('phone', 'address', "city", 'state', "zipcode", 'country', "website_url", 'business_name', "contact_name", 'category');
			foreach ($small_fields as $showField) {
				if ($modList[$i][$showField]) {
					$modValue = $modList[$i][$showField];
					if ($showField == 'category')
						$modValue = $this->categoryListByUID[$modValue];
					$mod_content .= '<span class="subjectLabel">'.$showField.'</span>:<span class="textLabel">' . $modValue . '&nbsp;&nbsp;</span>';
				}
			}
			$mod_content .= '
				</TD>
				</TR>
				';
			$mod_content .= '<TR><TD colspan=3 height=2 bgColor="#444"></TD></TR>';
		}
		if ($count == 0) {
			$mod_content .= '<tr><td colspan=3 align=center valign=center height=50>' . $this->pi_getLL('mod_no_messages','No messages to moderate') . '</td></tr>
				<tr><td colspan=3 align=center>
				<input type="button" value="' . $this->pi_getLL('mod_go_back','Go Back') . '" onclick="javascript:history.go(-1);"/>
				</td></tr>
				';
		} else {
			// allow to submit in form
			$mod_content .= '<TR>
				<td align=center><a href="#" onclick="SetAll(\'add\',1);return false;"><font size=-2>' . $this->pi_getLL('mod_approve_all','Approve All') . '</font></a></TD>
				<td align=center><a href="#" onclick="SetAll(\'del\',1);return false;"><font size=-2>' . $this->pi_getLL('mod_delete_all', 'Delete All') . '</font></a></TD>
				<td align=center>
				<br />
				<input type="submit" value="' . $this->pi_getLL('mod_process','Process') . '"/>
				<input type="button" value="' . $this->pi_getLL('mod_cancel','Cancel') . '" onclick="javascript:history.go(-1);"/>
				';
		}
		$mod_content .= '</FORM>
			</td></tr></table>';

		return $mod_content;

	}

	/**
	*==================================================================================
	*  Process Moderated Messages -- Will allow the moderator to process the moderation
	*   queue and either approve or disapprove messages
	*
	* @param  array  $msgList array of messages to show
	* @return void
	*==================================================================================
	*/
	function processModerated($msgList) {
		$msgAddList = '';
		$msgDelList = '';
		for ($i = 0; $i < $msgList['tx_wecdiscussion']['processmoderated']; $i++) {
			if ($msgVal = htmlspecialchars(t3lib_div::_GP('modMsg'.$i))) {
				// grab moderated msg uid# from passed in value(s)
				// format is add<#> or del<#>, i.e., add421 is add msg(uid=421)
				$msgNum = substr($msgVal, 3);
				$msgAction = substr($msgVal, 0, 3);
				if (!strcasecmp($msgAction, 'add')) {
					if (strlen($msgAddList) > 0) $msgAddList .= ',';
					$msgAddList .= $msgNum;
				}
				else if (!strcasecmp($msgAction, 'del')) {
					if (strlen($msgDelList) > 0) $msgDelList .= ',';
					$msgDelList .= $msgNum;
				}
			}
		}
		if (strlen($msgAddList) > 0) {
			$updData['moderationQueue'] = 0;
			$update = $GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->postTable, "uid IN (".$msgAddList.")", $updData);

			// now go through and let subscribers now about all new messages
			// we are going to try to put them  all together so they will receive only one email, not many
			$this->sendoutMultiMessages($msgAddList);
		}
		if (strlen($msgDelList) > 0) {
			$GLOBALS['TYPO3_DB']->exec_DELETEquery($this->postTable, "uid IN (".$msgDelList.')');
		}

		// clear page cache
		$this->clearCache();
	}

	/**
	* Getting the full URL (ie. http://www.host.com/... to the given ID with all needed params
	* This function handles cross-site (on same server) links
	*
	* @param integer  $id: Page ID
	* @param string   $urlParameters: array of parameters to include in the url (i.e., "$urlParameters['action'] = 4" would append "&action=4")
	* @param boolean  $forceFullURL: if should create a full URL or just a relative one (http://www.site.com/test/... vs. /test/)
	* @return string  $url: URL
	*/
	function getAbsoluteURL($id, $extraParameters = '', $forceFullURL = FALSE) {
		// get the page url from TYPO system (realURL or simulated or not)
		$pageURL = $this->pi_getPageLink($id, '', $extraParameters);
		// if did not cross page boundaries, then generate url from info
		if ((strpos($pageURL,"http") === FALSE) || $forceFullURL) {
			// use the baseURL if given
			if ($GLOBALS["TSFE"]->config['config']['baseURL']) {
				$hostURL = $GLOBALS["TSFE"]->config['config']['baseURL'];
			}
			else if ($GLOBALS["TSFE"]->config['config']['absRefPrefix']) {
				$hostURL = $GLOBALS["TSFE"]->config['config']['absRefPrefix'];
			}
			// otherwise generate URL from PHP var
			else {
				$hostURL = (t3lib_div::getIndpEnv('TYPO3_SSL') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/';
			}
			// build URL from host + page if not already has a full URL
			if (strpos($pageURL,"http") === FALSE) {
				$absURL =  $hostURL . $pageURL;
			}
			else { 
				$absURL = $pageURL;
			}
		}
		// crosses boundaries (likely different url on same server)
		else {
			$absURL = $pageURL;
		}

		//convert any ampersands
		$absURL = str_replace('&','&amp;', $absURL);
		return $absURL;
	}

	/**
	 * getConfigVal: Return the value from either plugin flexform, typoscript, or default value, in that order
	 *
	 * @param	object		$Obj: Parent object calling this function
	 * @param	string		$ffField: Field name of the flexform value
	 * @param	string		$ffSheet: Sheet name where flexform value is located
	 * @param	string		$TSfieldname: Property name of typoscript value
	 * @param	array		$lConf: TypoScript configuration array from local scope
	 * @param	mixed		$default: The default value to assign if no other values are assigned from TypoScript or Plugin Flexform
	 * @return	mixed		Configuration value found in any config, or default
	 */
	function getConfigVal( &$Obj, $ffField, $ffSheet, $TSfieldname='', $lConf='', $default = '' ) {
		if (!$lConf && $Obj->conf) $lConf = $Obj->conf;
		if (!$TSfieldname) $TSfieldname = $ffField;

		//	Retrieve values stored in flexform and typoscript
		$ffValue = $Obj->pi_getFFvalue($Obj->cObj->data['pi_flexform'], $ffField, $ffSheet);
		$tsValue = $lConf[$TSfieldname];

		//	Use flexform value if present, otherwise typoscript value
		$retVal = $ffValue ? $ffValue : $tsValue;

		//	Return value if found, otherwise default
		return $retVal ? $retVal : $default;
	}

	/**
	*==================================================================================
	*  GetStrftime -- get strftime with locale conversion
	*
	*   @param	string		$format: format string for strftime
	*   @param	string		$content: data to format
	* 	@return formatted date string
	*==================================================================================
	*/
	function getStrftime($format,$content) {
		$content = strftime($format,$content);
		$tmp_charset = $conf['strftime.']['charset'] ? $conf['strftime.']['charset'] : $GLOBALS['TSFE']->localeCharset;
		if ($tmp_charset)	{
				$content = $GLOBALS['TSFE']->csConv($content,$tmp_charset);
		}
		return $content;
	}

	/**
	*==================================================================================
	*  Display RSS Feed
	*
	* 	@return string RSS feed content
	*==================================================================================
	*/
	function displayRSSFeed() {
		$rss_content = "";

		$rssTemplateFile = $this->conf['rssTemplateFile'];
		if (!$rssTemplateFile)
			return $this->pi_getLL('no_rss_template_file',"No RSS Template File configured.");

		// current page where forum is
		$gotoPageID = $this->config['previewRSS_backPID'] ? $this->config['previewRSS_backPID'] : $this->id;

		// this is set for enabling relative URLs for images and links in the RSS feed.
		$sourceURL = $this->conf['xml.']['rss.']['source_url'] ?  $this->conf['xml.']['rss.']['source_url'] : t3lib_div::getIndpEnv('TYPO3_SITE_URL');

		// load in template
		$rssTemplateCode = $this->cObj->fileResource($rssTemplateFile);
		$rssTemplate = $this->cObj->getSubpart($rssTemplateCode, '###TEMPLATE_RSS2###');

		// fill in template
		$dataArray = array('CHANNEL_TITLE','CHANNEL_LINK','CHANNEL_DESCRIPTION','LANGUAGE','NAMESPACE_ENTRIES','COPYRIGHT','DOCS','CHANNEL_CATEGORY','MANAGING_EDITOR','WEBMASTER','CHANNEL_IMAGE','TTL');
		for ($i = 0; $i < count($dataArray); $i++) {
			$rssField = $dataArray[$i];
			$linkField = strtolower(str_replace('CHANNEL_','',$rssField));
			if ($val = $this->conf['xml.']['rss.'][strtolower($rssField)]) {
				$markerArray['###'.strtoupper($rssField).'###'] = '<'.$linkField.'>'.$val.'</'.$linkField.'>';
			}
		}
		$charset = ($GLOBALS['TSFE']->metaCharset?$GLOBALS['TSFE']->metaCharset:'iso-8859-1');
		$markerArray['###XML_CHARSET###'] = ' encoding="'.$charset.'"';

		// fill in defaults...if not set
		$markerArray['###GENERATOR###'] = $this->conf['xml.']['rss.']['generator'] ? $this->conf['xml.']['rss.']['generator'] : 'TYPO3 v4 CMS';
		$markerArray['###XMLNS###'] = $this->conf['xml.']['rss.']['xmlns'];
		$markerArray['###XMLBASE###'] = 'xml:base="'.$sourceURL.'"';
		$markerArray['###GEN_DATE###'] = date('D, d M Y h:i:s T');
		if (!$markerArray['###CHANNEL_TITLE###'])
			$markerArray['###CHANNEL_TITLE###'] = '<title>'. ($this->config['title'] ? $this->config['title'] : $this->conf['title']) .'</title>';
		if (!$markerArray['###CHANNEL_LINK###'])
			$markerArray['###CHANNEL_LINK###'] = '<link>'.$sourceURL.'</link>';
		$markerArray['###CHANNEL_GENERATOR###'] = '<generator>'.$markerArray['###GENERATOR###'].'</generator>';

		// grab item template
		$itemTemplate = $this->cObj->getSubpart($rssTemplateCode, '###ITEM###');

		// grab messages
		$pidList = t3lib_div::_GP('sp') ? t3lib_div::_GP('sp') : $this->pid_list;
		$order_by = 'post_datetime DESC';
		$where = 'toplevel_uid=0 ';
		$where .= ' AND pid IN(' . $GLOBALS['TYPO3_DB']->cleanIntList($pidList) . ')';
		$where .= ' AND moderationQueue=0';
		$where .= $this->cObj->enableFields($this->postTable);
		// handle languages
		$lang = ($l = $GLOBALS['TSFE']->sys_language_uid) ? $l : '0,-1';
		$where .= ' AND sys_language_uid IN ('.$lang.') ';
		$limit = $this->config['num_previewRSS_items'] ? $this->config['num_previewRSS_items'] : 5;
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->postTable, $where, '', $order_by, $limit);
		if (mysql_error()) t3lib_div::debug(array(mysql_error(), "SELECT ".$selFields.' FROM '.$this->postTable.' WHERE '.$where.' ORDER BY '.$order_by.' LIMIT '.$limit));

		// fill in item
		$item_content = "";
		$mostRecentMsgDate = 0;
		
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$itemMarker['###ITEM_TITLE###'] = '<title>'.htmlspecialchars(stripslashes($row['subject'])).'</title>';
			$urlParams = array();
			$hashParams = '';
			if ($this->config['allow_single_view'] != 0)
				$urlParams['tx_wecdiscussion']['single'] = $row['uid'];
			else
				$urlParams['tx_wecdiscussion']['showreply'] = $row['uid'];
			$itemMarker['###ITEM_LINK###'] = '<link>' . htmlspecialchars($this->getAbsoluteURL($gotoPageID,$urlParams,TRUE)) . '</link>';

			$msgText = $row['message'];
			if (is_array($this->conf['general_stdWrap.'])) {
				$msgText = str_replace('&nbsp;',' ',$msgText);
				$msgText = $this->cObj->stdWrap($this->html_entity_decode($msgText,ENT_QUOTES), $this->conf['general_stdWrap.']);
			}
			// if absRefPrefix set, then do transform so adds (because bug in TYPO3 and USER_INT)
			if (($absRefPrefix = $GLOBALS['TSFE']->config['config']['absRefPrefix']) && ($RTEImageStorageDir = $GLOBALS['TYPO3_CONF_VARS']['BE']['RTE_imageStorageDir'])) {
				$msgText = str_replace('"' .$RTEImageStorageDir, '"' . $absRefPrefix . $RTEImageStorageDir, $msgText);
			}
			// fill in item markers			
			$itemMarker['###ITEM_DESCRIPTION###'] = '<description>' . htmlspecialchars(stripslashes($msgText)) . '</description>';
			if ($row['email'])
				$itemMarker['###ITEM_AUTHOR###'] = '<author>' . htmlspecialchars(stripslashes($row['email'])) . ' ('.htmlspecialchars(stripslashes($row['name'])).')</author>';
			if (!empty($row['category']) && $this->categoryListByUID[$row['category']])
				$itemMarker['###ITEM_CATEGORY###'] = '<category>' . $this->categoryListByUID[$row['category']] . '</category>';
			$itemMarker['###ITEM_COMMENTS###'] = '';
			$itemMarker['###ITEM_ENCLOSURE###'] = '';
			$itemMarker['###ITEM_PUBDATE###'] = '<pubDate>' . date('D, d M Y H:i:s O',$row['post_datetime']) . '</pubDate>';
			$itemMarker['###ITEM_GUID###'] = '<guid isPermaLink="true">' . $this->getAbsoluteURL($gotoPageID,$urlParams, TRUE) . '</guid>';
			$itemMarker['###ITEM_SOURCE###'] = '<source url="' . $sourceURL . '">' . htmlspecialchars($row['subject']) . '</source>';

			if ($mostRecentMsgDate < $row['post_datetime'])
				$mostRecentMsgDate = $row['post_datetime'];

			// generate item info
			$item_content .= $this->cObj->substituteMarkerArrayCached($itemTemplate,$itemMarker,array(),array());
		}
		$subpartArray['###ITEM###'] = $item_content;
		if ($mostRecentMsgDate)
			$markerArray['###LAST_BUILD_DATE###'] = '<pubDate>' . date('D, d M Y H:i:s O', $mostRecentMsgDate) . '</pubDate>';

		// then substitute all the markers in the template into appropriate places
		$rss_content = $this->cObj->substituteMarkerArrayCached($rssTemplate,$markerArray,$subpartArray, array());

		// clear out any empty template fields (so if ###CONTENT1### is not substituted, will not display)
		$rss_content = preg_replace('/###.*?###/', '', $rss_content);

		// remove blank lines
		$rss_content = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $rss_content);

		// make certain tags XHTML compliant
		$rss_content = preg_replace("/<(img|hr|br|input)([^>]*)>/mi", "<$1$2 />", $rss_content);

		return $rss_content;
	}

	/**
	*==================================================================================
	*  Add RSS feed icon / text so can subscribe
	*
	*	@param  array  $marker array of markers (so can add to)
	* 	@return marker array
	*==================================================================================
	*/
	function addSubscribeRSSFeed($marker) {
		if ($this->conf['rssFeedOn']) {
			$rssLink = $this->conf['xml.']['rss.']['link'];
			if (strpos($this->conf['xml.']['rss.']['link'],'http:') === FALSE) {
				if (strpos($rssLink,'+') === 0) {
					$curURL = $this->getAbsoluteURL($this->id,'',TRUE);
					$rssLink = strpos($curURL,'?') ? str_replace('+','&',$rssLink) : str_replace('+','?',$rssLink);
					$rssLink = $curURL . $rssLink;
				}
				$rssLink .= (strpos($rssLink,'?') ? '&' : '?') . 'sp='.$this->pid_list;
			}

			$rssIcon = $this->conf['xml.']['rss.']['icon'];
			$rssImgConf['file'] = $rssIcon;
			$rssText = $this->pi_getLL('subscribe_to_feed','Subscribe to feed');

			$rssImgURLStr = $this->cObj->Image($rssImgConf); // run through imageMagick
			$marker['###RSSFEED_ICON1###'] = '<a href="'.$rssLink.'">'.$rssImgURLStr.'</a>';
			$marker['###RSSFEED_ICON2###'] = '<a href="'.$rssLink.'" style="text-decoration:none;">'.$rssImgURLStr.'</a>';
			$marker['###RSSFEED_TEXT###'] = '<a href="'.$rssLink.'" style="text-decoration:none;">'.$rssText.'</a>';
		}
		return $marker;
	}

	function adminConvert() {
		$output = tx_wecdiscussion_convert::convertTimTab(t3lib_div::_GET('pidfrom'),t3lib_div::_GET('pidto'),t3lib_div::_GET('deldata'));

		return $output;
	}

	function html_entity_decode($str,$quoteStyle=ENT_COMPAT) {
		if (( version_compare( phpversion(), '5.0' ) < 0 ) && (strtolower($GLOBALS['TSFE']->renderCharset) == 'utf-8')) {
			$trans_tbl = get_html_translation_table (HTML_ENTITIES);
			$trans_tbl = array_flip ($trans_tbl);
			$source = strtr ($str, $trans_tbl);
			$source = utf8_encode($source);
		}
		else if (version_compare(phpversion(),"4.3.0") < 0) {
		    // replace numeric entities
		    $source = preg_replace('~&#x([0-9a-f]+);~ei', 'chr(hexdec("\\1"))', $str);
		    $source = preg_replace('~&#([0-9]+);~e', 'chr("\\1")', $source);
		    // replace literal entities
		    $trans_tbl = get_html_translation_table(HTML_ENTITIES);
		    $trans_tbl = array_flip($trans_tbl);
		    $source = strtr($str, $trans_tbl);
		}
		else {
			$source = html_entity_decode($str,$quoteStyle,$GLOBALS['TSFE']->renderCharset);
		}

		return $source;
	}

	/**
	*==================================================================================
	*  Remove any potential XSS content using given method
	*
	*	@param  string $val	string to clean
	* 	@return string		cleaned string
	*==================================================================================
	*/
	function removeXSS($str) {
		// if blog and is admin...then allow it no matter what
		if (($this->config['type'] == 2 && $this->isValidUser) || ($this->isAdministrator))
			return htmlspecialchars($str);

		return $this->removeXSS_TYPO3($str);
	}

	/**
	*==================================================================================
	*  Remove any potential XSS content
	*	taken from t3lib_div.php but revised for 'style'
	*
	*	@param  string $val	string to clean
	* 	@return string		cleaned string
	*==================================================================================
	*/
	function removeXSS_TYPO3($val)	{
		$replaceString = '<x>';
		// remove all non-printable characters. CR(0a) and LF(0b) and TAB(9) are allowed
		// this prevents some character re-spacing such as <java\0script>
		// note that you have to handle splits with \n, \r, and \t later since they *are* allowed in some inputs
		$val = preg_replace('/([\x00-\x08][\x0b-\x0c][\x0e-\x19])/', '', $val);

		// straight replacements, the user should never need these since they're normal characters
		// this prevents like <IMG SRC=&#X40&#X61&#X76&#X61&#X73&#X63&#X72&#X69&#X70&#X74&#X3A&#X61&#X6C&#X65&#X72&#X74&#X28&#X27&#X58&#X53&#X53&#X27&#X29>
		$search = '/&#[xX]0{0,8}(21|22|23|24|25|26|27|28|29|2a|2b|2d|2f|30|31|32|33|34|35|36|37|38|39|3a|3b|3d|3f|40|41|42|43|44|45|46|47|48|49|4a|4b|4c|4d|4e|4f|50|51|52|53|54|55|56|57|58|59|5a|5b|5c|5d|5e|5f|60|61|62|63|64|65|66|67|68|69|6a|6b|6c|6d|6e|6f|70|71|72|73|74|75|76|77|78|79|7a|7b|7c|7d|7e);?/ie';
		$val = preg_replace($search, "chr(hexdec('\\1'))", $val);
		$search = '/&#0{0,8}(33|34|35|36|37|38|39|40|41|42|43|45|47|48|49|50|51|52|53|54|55|56|57|58|59|61|63|64|65|66|67|68|69|70|71|72|73|74|75|76|77|78|79|80|81|82|83|84|85|86|87|88|89|90|91|92|93|94|95|96|97|98|99|100|101|102|103|104|105|106|107|108|109|110|111|112|113|114|115|116|117|118|119|120|121|122|123|124|125|126);?/ie';
		$val = preg_replace($search, "chr('\\1')", $val);

		// now the only remaining whitespace attacks are \t, \n, and \r
		$ra1 = array('javascript', 'vbscript', 'expression', 'applet', 'meta', 'xml', 'blink', 'link', 'style', 'script', 'embed', 'object', 'iframe', 'frame', 'frameset', 'ilayer', 'layer', 'bgsound', 'title', 'base', 'onabort', 'onactivate', 'onafterprint', 'onafterupdate', 'onbeforeactivate', 'onbeforecopy', 'onbeforecut', 'onbeforedeactivate', 'onbeforeeditfocus', 'onbeforepaste', 'onbeforeprint', 'onbeforeunload', 'onbeforeupdate', 'onblur', 'onbounce', 'oncellchange', 'onchange', 'onclick', 'oncontextmenu', 'oncontrolselect', 'oncopy', 'oncut', 'ondataavailable', 'ondatasetchanged', 'ondatasetcomplete', 'ondblclick', 'ondeactivate', 'ondrag', 'ondragend', 'ondragenter', 'ondragleave', 'ondragover', 'ondragstart', 'ondrop', 'onerror', 'onerrorupdate', 'onfilterchange', 'onfinish', 'onfocus', 'onfocusin', 'onfocusout', 'onhelp', 'onkeydown', 'onkeypress', 'onkeyup', 'onlayoutcomplete', 'onload', 'onlosecapture', 'onmousedown', 'onmouseenter', 'onmouseleave', 'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup', 'onmousewheel', 'onmove', 'onmoveend', 'onmovestart', 'onpaste', 'onpropertychange', 'onreadystatechange', 'onreset', 'onresize', 'onresizeend', 'onresizestart', 'onrowenter', 'onrowexit', 'onrowsdelete', 'onrowsinserted', 'onscroll', 'onselect', 'onselectionchange', 'onselectstart', 'onstart', 'onstop', 'onsubmit', 'onunload');
		$ra_tag = array('applet', 'meta', 'xml', 'blink', 'link', 'style', 'script', 'embed', 'object', 'iframe', 'frame', 'frameset', 'ilayer', 'layer', 'bgsound', 'title', 'base');
		$ra_attribute = array('style', 'onabort', 'onactivate', 'onafterprint', 'onafterupdate', 'onbeforeactivate', 'onbeforecopy', 'onbeforecut', 'onbeforedeactivate', 'onbeforeeditfocus', 'onbeforepaste', 'onbeforeprint', 'onbeforeunload', 'onbeforeupdate', 'onblur', 'onbounce', 'oncellchange', 'onchange', 'onclick', 'oncontextmenu', 'oncontrolselect', 'oncopy', 'oncut', 'ondataavailable', 'ondatasetchanged', 'ondatasetcomplete', 'ondblclick', 'ondeactivate', 'ondrag', 'ondragend', 'ondragenter', 'ondragleave', 'ondragover', 'ondragstart', 'ondrop', 'onerror', 'onerrorupdate', 'onfilterchange', 'onfinish', 'onfocus', 'onfocusin', 'onfocusout', 'onhelp', 'onkeydown', 'onkeypress', 'onkeyup', 'onlayoutcomplete', 'onload', 'onlosecapture', 'onmousedown', 'onmouseenter', 'onmouseleave', 'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup', 'onmousewheel', 'onmove', 'onmoveend', 'onmovestart', 'onpaste', 'onpropertychange', 'onreadystatechange', 'onreset', 'onresize', 'onresizeend', 'onresizestart', 'onrowenter', 'onrowexit', 'onrowsdelete', 'onrowsinserted', 'onscroll', 'onselect', 'onselectionchange', 'onselectstart', 'onstart', 'onstop', 'onsubmit', 'onunload');
		$ra_protocol = array('javascript', 'vbscript', 'expression');

		//remove the potential &#xxx; stuff for testing
		$val2 = preg_replace('/(&#[xX]?0{0,8}(9|10|13|a|b);)*\s*/i', '', $val);
		$ra = array();
		foreach ($ra1 as $ra1word) {
			//stripos is faster than the regular expressions used later
			//and because the words we're looking for only have chars < 0x80
			//we can use the non-multibyte safe version
			if (strpos(strtolower($val2), strtolower($ra1word) ) !== false ) {
				//keep list of potential words that were found
				if (in_array($ra1word, $ra_protocol)) {
					$ra[] = array($ra1word, 'ra_protocol');
				}
				if (in_array($ra1word, $ra_tag)) {
					$ra[] = array($ra1word, 'ra_tag');
				}
				if (in_array($ra1word, $ra_attribute)) {
					$ra[] = array($ra1word, 'ra_attribute');
				}
				//some keywords appear in more than one array
				//these get multiple entries in $ra, each with the appropriate type
			}
		}
		// remove all comments
		$val = preg_replace("!/\*.*?\*/!s", '', $val);

		// now the only remaining whitespace attacks are \t, \n, and \r
		//only process potential words
		if (count($ra) > 0) {
			// keep replacing as long as the previous round replaced something
			$found = true;
			while ($found == true) {
				$val_before = $val;
				for ($i = 0; $i < sizeof($ra); $i++) {
					$pattern = '';
					for ($j = 0; $j < strlen($ra[$i][0]); $j++) {
						if ($j > 0) {
							$pattern .= '((&#[xX]0{0,8}([9ab]);)|(&#0{0,8}(9|10|13);)|\s)*';
						}
						$pattern .= $ra[$i][0][$j];
 					}
					//handle each type a little different (extra conditions to prevent false positives a bit better)
					switch ($ra[$i][1]) {
						case 'ra_protocol':
							//these take the form of e.g. 'javascript:'
							$pattern .= '((&#[xX]0{0,8}([9ab]);)|(&#0{0,8}(9|10|13);)|\s)*(?=:)';
							break;
						case 'ra_tag':
							//these take the form of e.g. '<SCRIPT[^\da-z] ....';
							$pattern = '(?<=<)' . $pattern . '((&#[xX]0{0,8}([9ab]);)|(&#0{0,8}(9|10|13);)|\s)*(?=[^\da-z])';
							break;
						case 'ra_attribute':
							//these take the form of e.g. 'onload='  Beware that a lot of characters are allowed
							//between the attribute and the equal sign!
							$pattern .= '[\s\!\#\$\%\&\(\)\*\~\+\-\_\.\,\:\;\?\@\[\/\|\\\\\]\^\`]*(?==)';
							break;
					}
					$pattern = '/' . $pattern . '/i';
					// add in <x> to nerf the tag
					$replacement = substr_replace($ra[$i][0], $replaceString, 2, 0);
					// filter out the hex tags
					$val = preg_replace($pattern, $replacement, $val);
					if ($val_before == $val) {
						// no replacements were made, so exit the loop
						$found = false;
					}
				}
			}
		}
		return $val;
	}

	/**
	 * Calls user function defined in TypoScript
	 *
	 * @param	integer		$mConfKey : if this value is empty the var $mConfKey is not processed
	 * @param	mixed		$passVar : this var is processed in the user function
	 * @return	mixed		the processed $passVar
	 */
	function userProcess($mConfKey, $passVar) {
		if ($this->conf[$mConfKey]) {
			$funcConf = $this->conf[$mConfKey . '.'];
			$funcConf['parentObj'] = & $this;
			$passVar = $GLOBALS['TSFE']->cObj->callUserFunction($this->conf[$mConfKey], $funcConf, $passVar);
		}
		return $passVar;
	}

	/**
	 * Gets the user name based on settings (uses name, first name, and/or last name)
	 *
	 * @param	none
	 * @return	string	the constructed name
	 */
	function getUserName() {
		$username = $GLOBALS['TSFE']->fe_user->user['name'];
		$surname_pos = strpos($username, ' ');
		$firstName = substr($username, 0, $surname_pos);
		$lastName = substr($username, $surname_pos, strlen($username) - $surname_pos);
		$firstName = trim($firstName);
		$lastName = trim($lastName);
		switch ($this->conf['namePrefill']) {
			case 'first_name': 	$nameFill = $firstName; break;
			case 'last_name':	$nameFill = $lastName;  break;
			case 'last_first':  $nameFill = $lastName . ' ' . $firstName; break;
			case 'first_last':
			default:			$nameFill = $firstName . ' ' . $lastName;
		}
		return $nameFill;
	}

	/**
	*==================================================================================
	*  clearCache -- clear cache for this extension only
	*
	* 	@return none
	*==================================================================================
	*/
	function clearCache() {
	  	if (t3lib_div::int_from_ver(TYPO3_version) >= 4003000) {
		  	// only use cachingFramework if initialized and configured in TYPO3
		   	if (t3lib_cache::isCachingFrameworkInitialized() && TYPO3_UseCachingFramework) {
		    	$pageCache = $GLOBALS['typo3CacheManager']->getCache('cache_pages');
		    	$pageCache->flushByTag('wec_discussion');
		   	} 
			else {
		    	$GLOBALS['TYPO3_DB']->exec_DELETEquery('cache_pages', 'reg1=416');
		   }
		}
		else {
		   	$GLOBALS['TYPO3_DB']->exec_DELETEquery('cache_pages', 'reg1=416');
		}
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wec_discussion/pi1/class.tx_wecdiscussion_pi1.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wec_discussion/pi1/class.tx_wecdiscussion_pi1.php']);
}

?>
