<?php

class Config
{
	// Google Apps Settings
	const GOOGLE_APPS_DOMAIN = '';
	const GOOGLE_APPS_USER = '';
	const GOOGLE_APPS_PASS = '';

	// Active Directory Settings
	const LDAP_SERVER_URI = '';
	const LDAP_BIND_DN = '';
	const LDAP_PASS = '';
	const LDAP_BASE_DN = '';
	const LDAP_DEBUG = true;

	const TIMEZONE = 'Australia/Sydney';
	const INACTIVE_DAYS_THRESHOLD = 90;
	const CACHE_TYPE = 'KVS';
}

