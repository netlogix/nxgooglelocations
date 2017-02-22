#
# Table structure for table 'tx_nxgooglelocations_domain_model_batch'
#
CREATE TABLE tx_nxgooglelocations_domain_model_batch (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,

	state varchar(255) DEFAULT 'new' NOT NULL,
	amount int(11) DEFAULT '0' NOT NULL,
	position int(11) DEFAULT '0' NOT NULL,

	api_key varchar(255) DEFAULT '' NOT NULL,
	storage_page_id int(11) DEFAULT '0' NOT NULL,
	backend_user_id int(11) DEFAULT '0' NOT NULL,
	file_name varchar(255) DEFAULT '' NOT NULL,
	file_hash varchar(255) DEFAULT '' NOT NULL,
	file_content longblob,

	type varchar(255) DEFAULT '' NOT NULL,
	tstamp int(11) unsigned DEFAULT '0' NOT NULL,
	crdate int(11) unsigned DEFAULT '0' NOT NULL,
	cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
	deleted tinyint(4) unsigned DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid),
	KEY parent (pid)
);