#
# Table structure for table 'tx_wecdiscussion_post'
#
CREATE TABLE tx_wecdiscussion_post (
	uid int(11) unsigned DEFAULT '0' NOT NULL auto_increment,
	pid int(11) unsigned DEFAULT '0' NOT NULL,
	tstamp int(11) unsigned DEFAULT '0' NOT NULL,
	crdate int(11) unsigned DEFAULT '0' NOT NULL,
	cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
	deleted tinyint(4) unsigned DEFAULT '0' NOT NULL,
  	hidden tinyint(4) unsigned DEFAULT '0' NOT NULL,
	starttime int(11) unsigned DEFAULT '0' NOT NULL,
	endtime int(11) unsigned DEFAULT '0' NOT NULL,
	useruid int(11) DEFAULT '0' NOT NULL,
	name tinytext NOT NULL,
	email tinytext NOT NULL,
	post_datetime int(11) DEFAULT '0' NOT NULL,
	post_lastedit_time int(11) DEFAULT '0' NOT NULL,
	reply_uid int(11) DEFAULT '0' NOT NULL,
	subject tinytext NOT NULL,
	message text NOT NULL,
	category int(11) DEFAULT '0' NOT NULL,
	image blob NOT NULL,
	image_caption tinytext NOT NULL,
	attachment blob NOT NULL,
    toplevel_uid int(11) DEFAULT '0' NOT NULL,
    moderationQueue int(4) DEFAULT '0' NOT NULL,
	ipAddress tinytext NOT NULL,
	email_author_replies tinyint(4) unsigned DEFAULT '0' NOT NULL,
	
	sys_language_uid int(11) DEFAULT '0' NOT NULL,
	l18n_parent int(11) DEFAULT '0' NOT NULL,
	l18n_diffsource mediumblob NOT NULL,

	PRIMARY KEY (uid),
	KEY parent (pid)
);



#
# Table structure for table 'tx_wecdiscussion_category'
#
CREATE TABLE tx_wecdiscussion_category (
	uid int(11) unsigned DEFAULT '0' NOT NULL auto_increment,
	pid int(11) unsigned DEFAULT '0' NOT NULL,
	tstamp int(11) unsigned DEFAULT '0' NOT NULL,
	crdate int(11) unsigned DEFAULT '0' NOT NULL,
	cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
	deleted tinyint(4) unsigned DEFAULT '0' NOT NULL,
  	hidden tinyint(4) unsigned DEFAULT '0' NOT NULL,
	name tinytext NOT NULL,
	description text NOT NULL,
	image blob NOT NULL,
	sort_order int(11) DEFAULT '0' NOT NULL,
	private_group int(11) DEFAULT '0' NOT NULL,

	sys_language_uid int(11) DEFAULT '0' NOT NULL,
	l18n_parent int(11) DEFAULT '0' NOT NULL,
	l18n_diffsource mediumblob NOT NULL,

	PRIMARY KEY (uid),
	KEY parent (pid)
);



#
# Table structure for table 'tx_wecdiscussion_group'
#
CREATE TABLE tx_wecdiscussion_group (
	uid int(11) unsigned DEFAULT '0' NOT NULL auto_increment,
	pid int(11) unsigned DEFAULT '0' NOT NULL,
	tstamp int(11) unsigned DEFAULT '0' NOT NULL,
	crdate int(11) unsigned DEFAULT '0' NOT NULL,
	cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
	sorting int(10) unsigned DEFAULT '0' NOT NULL,
	user_uid int(11) DEFAULT '0' NOT NULL,
	user_email tinytext NOT NULL,
	user_name tinytext NOT NULL,

	PRIMARY KEY (uid),
	KEY parent (pid)
);

