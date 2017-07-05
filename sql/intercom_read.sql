CREATE TABLE /*_*/intercom_read (
	userid int unsigned NOT NULL,
	messageid int unsigned NOT NULL,
	PRIMARY KEY (userid, messageid)
) /*$wgDBTableOptions*/;