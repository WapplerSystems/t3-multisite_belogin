CREATE TABLE tx_multisitebelogin_token (
	token_hash varchar(64) DEFAULT '' NOT NULL,
	user_id int(11) unsigned DEFAULT '0' NOT NULL,
	login_group varchar(64) DEFAULT '' NOT NULL,
	mfa smallint(1) unsigned DEFAULT '0' NOT NULL,
	switch_user_id int(11) unsigned DEFAULT '0' NOT NULL,
	expires int(11) unsigned DEFAULT '0' NOT NULL,

	PRIMARY KEY (token_hash),
	KEY expires (expires),
	KEY login_group (login_group)
);
