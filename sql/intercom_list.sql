CREATE TABLE /*_*/intercom_list (
	userid int unsigned NOT NULL,
	list varbinary(255) NOT NULL,
	PRIMARY KEY (userid, list)
) /*$wgDBTableOptions*/;