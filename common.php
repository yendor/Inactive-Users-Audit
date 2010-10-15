<?php

date_default_timezone_set(Config::TIMEZONE);

// w32tm /ntte 129302168303660369
// w32tm /ntte 129288237415671418

function PwdLastSetTime2Epoch($stamp)
{
	// return $stamp/10000000 - 11644560000;
	return bcsub(bcdiv($stamp, '10000000'), '11644473600');
}

function LastLogonStamp2Epoch($stamp)
{
	return bcsub(bcdiv($stamp, '10000000'), '11644473600');
}

function message($msg, $level='FATAL')
{
	error_log($msg);
	if ($level == 'FATAL') {
		die($msg);
	}
}


