<?php
if (!defined ("TYPO3_MODE")) 	die ("Access denied.");

// get extension configuration
$confArr = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['wec_discussion']);

// load tt_content for wecdiscussion post
t3lib_div::loadTCA("tt_content");
t3lib_extMgm::allowTableOnStandardPages("tx_wecdiscussion_post");

$TCA["tx_wecdiscussion_post"] = Array (
	"ctrl" => Array (
		"title" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_post",
		"label" => $confArr['label'] ? $confArr['label'] : "subject",
		"label_alt" => $confArr['label_alt'] ? $confArr['label_alt'] : "",
		"tstamp" => "tstamp",
		"crdate" => "crdate",
		"cruser_id" => "cruser_id",
		"languageField" => "sys_language_uid",
		"transOrigPointerField" => "l18n_parent",
		"transOrigDiffSourceField" => "l18n_diffsource",		
		"default_sortby" => "ORDER BY tstamp DESC",
		"delete" => "deleted",
		'enablecolumns' => Array (
			'disabled' => 'hidden',
			"starttime" => "starttime",
			"endtime" => "endtime",
		),
		"shadowColumnsForNewPlaceholders" => "sys_language_uid,l18n_parent",		
		"dynamicConfigFile" => t3lib_extMgm::extPath($_EXTKEY)."tca.php",
		"iconfile" => t3lib_extMgm::extRelPath($_EXTKEY)."res/icon_tx_wecdiscussion_post.gif",
	),
	"feInterface" => Array (
		"fe_admin_fieldList" => "useruid, name, email, reply_uid, subject, message, category, image, image_caption, attachment,moderationQueue,hidden,starttime,endtime",
	)
);

t3lib_extMgm::allowTableOnStandardPages("tx_wecdiscussion_category");
t3lib_extMgm::addToInsertRecords("tx_wecdiscussion_category");

$TCA["tx_wecdiscussion_category"] = Array (
	"ctrl" => Array (
		"title" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_category",
		"label" => "name",
		"tstamp" => "tstamp",
		"crdate" => "crdate",
		"cruser_id" => "cruser_id",
		"languageField" => "sys_language_uid",
		"transOrigPointerField" => "l18n_parent",
		"transOrigDiffSourceField" => "l18n_diffsource",		
		"default_sortby" => "ORDER BY name ASC",
		"delete" => "deleted",
		'enablecolumns' => Array (
			'disabled' => 'hidden',
		),
		"shadowColumnsForNewPlaceholders" => "sys_language_uid,l18n_parent",		
		"dynamicConfigFile" => t3lib_extMgm::extPath($_EXTKEY)."tca.php",
		"iconfile" => t3lib_extMgm::extRelPath($_EXTKEY)."res/icon_tx_wecdiscussion_category.gif",
	),
	"feInterface" => Array (
		"fe_admin_fieldList" => "parent_uid, name, description, sort_order, image, private_group, hidden",
	)
);

t3lib_extMgm::allowTableOnStandardPages("tx_wecdiscussion_group");
t3lib_extMgm::addToInsertRecords("tx_wecdiscussion_group");

$TCA["tx_wecdiscussion_group"] = Array (
	"ctrl" => Array (
		"title" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_group",
		"label" => "user_name",
		"tstamp" => "tstamp",
		"crdate" => "crdate",
		"cruser_id" => "cruser_id",
		"default_sortby" => "ORDER BY crdate DESC",
		"sortby" => "sorting",
		"dynamicConfigFile" => t3lib_extMgm::extPath($_EXTKEY)."tca.php",
		"iconfile" => t3lib_extMgm::extRelPath($_EXTKEY)."res/icon_tx_wecdiscussion_group.gif",
	),
	"feInterface" => Array (
		"fe_admin_fieldList" => "user_uid, user_email, user_name",
	)
);


t3lib_div::loadTCA("tt_content");
$TCA["tt_content"]["types"]["list"]["subtypes_excludelist"][$_EXTKEY."_pi1"]="layout,select_key,pages,recursive";

t3lib_extMgm::addPlugin(Array("LLL:EXT:wec_discussion/locallang_db.xml:tt_content.list_type_pi1", $_EXTKEY."_pi1"),"list_type");

$TCA["tt_content"]["types"]["list"]["subtypes_addlist"][$_EXTKEY."_pi1"]="pi_flexform";
t3lib_extMgm::addPiFlexFormValue($_EXTKEY."_pi1", "FILE:EXT:wec_discussion/flexform_ds.xml");

t3lib_extMgm::addStaticFile($_EXTKEY,"static/ts","WEC Discussion Forum (old) Template");
t3lib_extMgm::addStaticFile($_EXTKEY,"static/tsnew","WEC Discussion Forum Template");
t3lib_extMgm::addStaticFile($_EXTKEY, 'static/rss2/', 'WEC Discussion RSS 2.0 Feed' );

if (TYPO3_MODE=="BE") $TBE_MODULES_EXT["xMOD_db_new_content_el"]["addElClasses"]["tx_wecdiscussion_pi1_wizicon"] = t3lib_extMgm::extPath($_EXTKEY).'pi1/class.tx_wecdiscussion_pi1_wizicon.php';

?>
