CREATE TABLE /*_*/intercom_message (
	id int NOT NULL PRIMARY KEY auto_increment,
	summary tinyblob,
	message mediumblob,
	author int unsigned NOT NULL,
	list varbinary(255) NOT NULL,
	timestamp varbinary(14),
	expires varbinary(14),
	parsed boolean NOT NULL default true
) /*$wgDBTableOptions*/;