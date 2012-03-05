<?php
if (!defined ("TYPO3_MODE")) 	die ("Access denied.");

if (!class_exists('tx_wecdiscussion_backend',false)) {
  $ext_path = t3lib_extMgm::extPath('wec_discussion');
  require_once($ext_path.'pi1/class.tx_wecdiscussion_backend.php');
}

$TCA["tx_wecdiscussion_post"] = Array (
	"ctrl" => $TCA["tx_wecdiscussion_post"]["ctrl"],
	"interface" => Array (
		"showRecordFieldList" => "useruid,hidden,name,email,subject,message,category,post_datetime,post_lastedit_time,toplevel_uid,reply_uid,image,image_caption,attachment,moderationQueue,starttime,endtime,ipAddress"
	),
	"feInterface" => $TCA["tx_wecdiscussion_post"]["feInterface"],
	"columns" => Array (
		'sys_language_uid' => Array (
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.php:LGL.language',
			'config' => Array (
				'type' => 'select',
				'foreign_table' => 'sys_language',
				'foreign_table_where' => 'ORDER BY sys_language.title',
				'items' => Array(
					Array('LLL:EXT:lang/locallang_general.php:LGL.allLanguages',-1),
					Array('LLL:EXT:lang/locallang_general.php:LGL.default_value',0)
				)
			)
		),
		'l18n_parent' => Array (
			'displayCond' => 'FIELD:sys_language_uid:>:0',
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.php:LGL.l18n_parent',
			'config' => Array (
				'type' => 'select',
				'items' => Array (
					Array('', 0),
				),
				'foreign_table' => 'tx_wecdiscussion_post',
				'foreign_table_where' => 'AND tx_wecdiscussion_post.pid=###CURRENT_PID### AND tx_wecdiscussion_post.sys_language_uid IN (-1,0)',
			)
		),
		'l18n_diffsource' => Array (
			'config' => Array (
				'type' => 'passthrough'
			)
		),		
		"useruid" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_post.useruid",
			"config" => Array (
				"type" => "input",
				"size" => "4",
				"max" => "4",
				"eval" => "int",
				"checkbox" => "0",
				"range" => Array (
					"upper" => "100000",
					"lower" => "1"
				),
				"default" => 0
			)
		),
		"name" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_post.name",
			"config" => Array (
				"type" => "input",
				"size" => "32",
				"max" => "64",
				"default" => tx_wecdiscussion_backend::getDefaultName(),
			)
		),
		"email" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_post.email",
			"config" => Array (
				"type" => "input",
				"size" => "32",
				"max" => "64",
				"default" => tx_wecdiscussion_backend::getDefaultEmail(),
			)
		),
		"post_datetime" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_post.post_datetime",
			"config" => Array (
				"type" => "input",
				"size" => "12",
				"max" => "20",
				"eval" => "datetime",
				"checkbox" => "0",
				"default" => "0"
			)
		),
		"post_lastedit_time" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_post.post_lastedit_time",
			"config" => Array (
				"type" => "input",
				"size" => "12",
				"max" => "20",
				"eval" => "datetime",
				"checkbox" => "0",
				"default" => "0"
			)
		),
		"reply_uid" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_post.reply_uid",
			"config" => Array (
				"type" => "input",
				"size" => "4",
				"max" => "4",
				"eval" => "int",
				"checkbox" => "0",
				"range" => Array (
					"upper" => "100000",
					"lower" => "1"
				),
				"default" => 0
			)
		),
		'hidden' => Array (
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.php:LGL.hidden',
			'config' => Array (
				'type' => 'check',
				'default' => '0'
			)
		),
		"subject" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_post.subject",
			"config" => Array (
				"type" => "input",
				"size" => "32",
				"max" => "64",
			)
		),
		"message" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_post.message",
			"config" => Array (
				"type" => "text",
				"cols" => "30",
				"rows" => "5",
			)
		),
		"category" => Array (
			"exclude" => 1,
			"l10n_mode" => "exclude",
			"label" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_post.category",
			"config" => Array (
				"type" => "select",
				"foreign_table" => "tx_wecdiscussion_category",
				"foreign_table_where" => "AND tx_wecdiscussion_category.pid=###CURRENT_PID### ORDER BY tx_wecdiscussion_category.uid",
				"size" => 3,
				"autoSizeMax" => 10,
				"minitems" => 0,
				"maxitems" => 100,
				"wizards" => Array(
					"_PADDING" => 2,
					"_VERTICAL" => 1,
					"add" => Array(
						"type" => "script",
						"title" => "Create new category",
						"icon" => "add.gif",
						"params" => Array(
							"table"=>"tx_wecdiscussion_category",
							"pid" => "###CURRENT_PID###",
							"setValue" => "set"
						),
						"script" => "wizard_add.php",
					),
					"edit" => Array(
							"type" => "popup",
							"title" => "Edit category",
							"script" => "wizard_edit.php",
							"popup_onlyOpenIfSelected" => 1,
							"icon" => "edit2.gif",
							"JSopenParams" => "height=350,width=580,status=0,menubar=0,scrollbars=1",
					),
				),
			)
		),
		"image" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_post.image",
			"config" => Array (
				"type" => "group",
				"internal_type" => "file",
				"allowed" => "gif,png,jpeg,jpg",
				"max_size" => 200,
				"uploadfolder" => "uploads/tx_wecdiscussion",
				"size" => 1,
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
		"image_caption" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_post.image_caption",
			"config" => Array (
				"type" => "input",
				"size" => "30",
			)
		),
		"attachment" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_post.attachment",
			"config" => Array (
				"type" => "group",
				"internal_type" => "file",
				"allowed" => "",
				"disallowed" => "php,php3,sh",
				"max_size" => 500,
				"uploadfolder" => "uploads/tx_wecdiscussion",
				"size" => 1,
				"minitems" => 0,
				"maxitems" => 4,
			)
		),
		"toplevel_uid" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_post.toplevel_uid",
			"config" => Array (
				"type" => "input",
				"size" => "4",
				"max" => "4",
				"eval" => "int",
				"checkbox" => "0",
				"range" => Array (
					"upper" => "100000",
					"lower" => "1"
				),
				"default" => 0
			)
		),
		"moderationQueue" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_post.moderationQueue",
			"config" => Array (
				"type" => "input",
				"size" => "4",
				"max" => "4",
				"eval" => "int",
				"checkbox" => "0",
				"range" => Array (
					"upper" => "100000",
					"lower" => "1"
				),
				"default" => 0
			)
		),
		"starttime" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:lang/locallang_general.php:LGL.starttime",
			"config" => Array (
				"type" => "input",
				"size" => "8",
				"max" => "20",
				"eval" => "date",
				"default" => "0",
				"checkbox" => "0"
			)
		),
		"endtime" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:lang/locallang_general.php:LGL.endtime",
			"config" => Array (
				"type" => "input",
				"size" => "8",
				"max" => "20",
				"eval" => "date",
				"checkbox" => "0",
				"default" => "0",
				"range" => Array (
					"upper" => mktime(0,0,0,12,31,2020),
					"lower" => mktime(0,0,0,date("m")-1,date("d"),date("Y"))
				)
			)
		),		
		"ipAddress" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_post.ipAddress",
			"config" => Array (
				"type" => "input",
				"size" => "10",
				"max" => "20",
				"eval" => "string",
				"checkbox" => "0",
				"default" => "0",
			)
		),		
	),
	"types" => Array (
		"0" => Array("showitem" => "sys_language_uid;;;;3-3-3, l18n_parent, l18n_diffsource,useruid;;;;1-1-1, name, email, subject, message;;;richtext[paste|bold|italic|underline|formatblock|class|left|center|right|orderedlist|unorderedlist|outdent|indent|link|image]:rte_transform[mode=ts], post_datetime, post_lastedit_time, toplevel_uid, reply_uid, category, image, image_caption, attachment, moderationQueue,hidden,starttime,endtime,ipAddress")
	),
	"palettes" => Array (
		"1" => Array("showitem" => "")
	)
);


$TCA["tx_wecdiscussion_category"] = Array (
	"ctrl" => $TCA["tx_wecdiscussion_category"]["ctrl"],
	"interface" => Array (
		"showRecordFieldList" => "name,descripton,image,hidden"
	),
	"feInterface" => $TCA["tx_wecdiscussion_category"]["feInterface"],
	"columns" => Array (
		'sys_language_uid' => Array (
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.php:LGL.language',
			'config' => Array (
				'type' => 'select',
				'foreign_table' => 'sys_language',
				'foreign_table_where' => 'ORDER BY sys_language.title',
				'items' => Array(
					Array('LLL:EXT:lang/locallang_general.php:LGL.allLanguages',-1),
					Array('LLL:EXT:lang/locallang_general.php:LGL.default_value',0)
				)
			)
		),
		'l18n_parent' => Array (
			'displayCond' => 'FIELD:sys_language_uid:>:0',
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.php:LGL.l18n_parent',
			'config' => Array (
				'type' => 'select',
				'items' => Array (
					Array('', 0),
				),
				'foreign_table' => 'tx_wecdiscussion_category',
				'foreign_table_where' => 'AND tx_wecdiscussion_category.pid=###CURRENT_PID### AND tx_wecdiscussion_category.sys_language_uid IN (-1,0)',
			)
		),
		'l18n_diffsource' => Array (
			'config' => Array (
				'type' => 'passthrough'
			)
		),		
		"name" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_category.name",
			"config" => Array (
				"type" => "input",
				"size" => "32",
				"max" => "64",
			)
		),
		"description" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_category.description",
			"config" => Array (
				"type" => "text",
				"cols" => "35",
				"rows" => "4",
			)
		),
		"sort_order" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_category.sort_order",
			"config" => Array (
				"type" => "input",
				"size" => "4",
				"max" => "4",
				"eval" => "int",
				"checkbox" => "0",
				"range" => Array (
					"upper" => "100000",
					"lower" => "1"
				),
				"default" => 0
			)
		),
		"image" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_post.image",
			"config" => Array (
				"type" => "group",
				"internal_type" => "file",
				"allowed" => "gif,png,jpeg,jpg",
				"max_size" => 200,
				"uploadfolder" => "uploads/tx_wecdiscussion",
				"size" => 1,
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
		"private_group" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_category.private_group",
			"config" => Array (
				"type" => "input",
				"size" => "4",
				"max" => "4",
				"eval" => "int",
				"checkbox" => "0",
				"range" => Array (
					"upper" => "100000",
					"lower" => "1"
				),
				"default" => 0
			)
		),
		'hidden' => Array (
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.php:LGL.hidden',
			'config' => Array (
				'type' => 'check',
				'default' => '0'
			)
		),
	),
	"types" => Array (
		"0" => Array("showitem" => "sys_language_uid;;;;3-3-3, l18n_parent, l18n_diffsource, name, description, sort_order, image, private_group, hidden")
	),
	"palettes" => Array (
		"1" => Array("showitem" => "")
	)
);



$TCA["tx_wecdiscussion_group"] = Array (
	"ctrl" => $TCA["tx_wecdiscussion_group"]["ctrl"],
	"interface" => Array (
		"showRecordFieldList" => "name,description,userlist"
	),
	"feInterface" => $TCA["tx_wecdiscussion_group"]["feInterface"],
	"columns" => Array (
		"user_uid" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_group.user_uid",
			"config" => Array (
				"type" => "input",
				"size" => "4",
				"max" => "4",
				"eval" => "int",
				"checkbox" => "0",
				"range" => Array (
					"upper" => "100000",
					"lower" => "1"
				),
				"default" => 0
			)
		),
		"user_email" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_group.user_email",
			"config" => Array (
				"type" => "input",
				"size" => "24",
				"max" => "48",
			)
		),
		"user_name" => Array (
			"exclude" => 1,
			"label" => "LLL:EXT:wec_discussion/locallang_db.xml:tx_wecdiscussion_group.user_name",
			"config" => Array (
				"type" => "input",
				"size" => "24",
				"max" => "48",
			)
		),
	),
	"types" => Array (
		"0" => Array("showitem" => "user_uid, user_email, user_name")
	),
	"palettes" => Array (
		"1" => Array("showitem" => "")
	)
);


?>