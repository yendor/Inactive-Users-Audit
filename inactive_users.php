<?php
require_once dirname(__FILE__).'/config.php';
require_once dirname(__FILE__).'/common.php';
require_once dirname(__FILE__).'/google_apps.php';
require_once dirname(__FILE__).'/kvs.php';

if (Config::LDAP_DEBUG) {
	ldap_set_option(NULL, LDAP_OPT_DEBUG_LEVEL, 7);
}

$link = ldap_connect(Config::LDAP_SERVER_URI);


if(!$link) {
	message("No connection");
}

ldap_set_option($link, LDAP_OPT_REFERRALS, 0);
ldap_set_option($link, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option(NULL, LDAP_OPT_DEBUG_LEVEL, 0);

if(!ldap_bind($link, Config::LDAP_BIND_DN, Config::LDAP_PASS)) {
	message("Could not bind: ". ldap_error($link));
}

$result = ldap_search($link, Config::LDAP_BASE_DN, "(objectClass=user)", array('dn', 'sAMAccountName', 'lastLogonTimestamp'));
$entries = ldap_get_entries($link, $result);

if(!isset($entries[0])) {
	message("Invalid user or user not found: ".ldap_error($link));
}

$threshold = strtotime(Config::INACTIVE_DAYS_THRESHOLD.' days ago');

unset($entries['count']);

$inactiveADUsers = array();

foreach ($entries as $user) {
	if (empty($user['lastlogontimestamp'])) {
		continue;
	}

	$lastLogon = LastLogonStamp2Epoch($user['lastlogontimestamp'][0]);
	if ($lastLogon < $threshold) {
		if ($lastLogon < 0) {
			// echo $user['dn']." - Never Logged In\n";
		}
		else {
			// echo $user['dn'].' - '.date('r', $lastLogon)."\n";
		}
		$inactiveADUsers[] = $user['samaccountname'][0];
	}
}

$api = new GoogleApps();

$loggedIn = $api->Login(Config::GOOGLE_APPS_USER, Config::GOOGLE_APPS_DOMAIN, Config::GOOGLE_APPS_PASS);

$report = $api->GetReport('accounts');

$inactiveGAusers = array();

foreach ($report as $user) {
	if ($user['status'] != 'ACTIVE') {
		continue;
	}

	$lastLoginStamp = strtotime($user['last_login_date']);
	$lastWebmailStamp = strtotime($user['last_web_mail_date']);
	$lastPopStamp = strtotime($user['last_pop_date']);

	if ($lastLoginStamp > $threshold) {
		continue;
	}

	if ($lastWebmailStamp > $threshold) {
		continue;
	}

	if ($lastPopStamp > $threshold) {
		continue;
	}

	$accountNameParts = explode('@', $user['account_name'], 2);
	$inactiveGAusers[] = $accountNameParts[0];
}

$inactiveUsers = array_intersect($inactiveADUsers, $inactiveGAusers);

if (empty($inactiveUsers)) {
	echo "There are no inactive users\n";
}
else {
	echo str_repeat('=', 72)."\n";
	echo "The following users have not logged in in the last ".Config::INACTIVE_DAYS_THRESHOLD." days \n";
	echo "(since ".date('r', $threshold).")\n";
	echo str_repeat('=', 72)."\n";
	foreach ($inactiveUsers as $username) {
		echo "$username\n";
	}

}