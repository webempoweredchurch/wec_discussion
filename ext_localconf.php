<?php
if (!defined ("TYPO3_MODE")) 	die ("Access denied.");

  ## Extending TypoScript from static template uid=43 to set up userdefined tag:
t3lib_extMgm::addTypoScript($_EXTKEY,"editorcfg","
	tt_content.CSS_editor.ch.tx_wecdiscussion_pi1 = < plugin.tx_wecdiscussion_pi1.CSS_editor
",43);


t3lib_extMgm::addPItoST43($_EXTKEY,"pi1/class.tx_wecdiscussion_pi1.php","_pi1","list_type",1);

t3lib_extMgm::addUserTSConfig('options.saveDocNew.tx_wecdiscussion_post=1');
t3lib_extMgm::addUserTSConfig('options.saveDocNew.tx_wecdiscussion_category=1');

/* Allow embed (Youtube) HTML tags in the RTE */
t3lib_extMgm::addPageTSConfig('
	RTE.default.proc {
	  allowTags := addToList(object,param,embed) 
	  allowTagsOutside := addToList(object,embed)
	  entryHTMLparser_db.allowTags < RTE.default.proc.allowTags
	}
');

/* Include a custom userFunc for checking whether we are USER OR USER_INT  */
require_once(t3lib_extMgm::extPath('wec_discussion') . 'pi1/class.tx_wecdiscussion_isNotCached.php');
?>