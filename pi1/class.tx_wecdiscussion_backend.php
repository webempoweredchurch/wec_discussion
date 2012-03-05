<?php

class tx_wecdiscussion_backend {
	function getDefaultName() {
		if (TYPO3_MODE=="BE")
			return $GLOBALS['BE_USER']->user['realName'];
	}

	function getDefaultEmail() {
		if(TYPO3_MODE=="BE")
			return $GLOBALS['BE_USER']->user['email'];
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wec_discussion/pi1/class.tx_wecdiscussion_backend.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wec_discussion/pi1/class.tx_wecdiscussion_backend.php']);
}

?>