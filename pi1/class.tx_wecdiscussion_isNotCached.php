<?php

/**
 * userFunc conditional to determine if we're in a form based
 * on the POST variables. Used to dynamically switch to a USER_INT for
 * form handling
 *
 * @return 		boolean		True if we should not be cached
 */

function user_isDiscussionNotCached() {
	$postVars = t3lib_div::_GP('tx_wecdiscussion');

	if ($postVars['single'] || t3lib_div::_GP('submitsubscribe') || t3lib_div::_GP('submitunsubscribe') || $postVars['editMsg'] || $postVars['edit_msg'] ||
		$postVars['deleteMsg'] || $postVars['deleteAllMsg'] || $postVars['processmoderated'] || $postVars['moderate'] || t3lib_div::_GP('submitabuse') ||
		$postVars['is_reply'] || $postVars['ispreview'] || $postVars['showreply'] || $postVars['show_cat'] || $postVars['pg'] || 
		$postVars['searchwords'] || $postVars['archive'] || $postVars['show_date'] || $postVars['abs'] || $postVars['sub'] || 
		$postVars['captcha_response'] || 
		t3lib_div::_GP('admin') || t3lib_div::_GP('sp') || t3lib_div::_GP('type')) {
		return true; 
	}
	else {
		return false;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wec_discussion/pi1/class.tx_wecdiscussion_isNotCached.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wec_discussion/pi1/class.tx_wecdiscussion_isNotCached.php']);
}

?>
