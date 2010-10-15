<?php

class GoogleApps
{
	protected $error = '';

	protected $authToken = '';

	protected $googleService = 'apps';
	protected $googleAccountType = 'HOSTED';
	protected $googleDomain = '';

	protected $cache = null;

	public function __construct()
	{
		if (!function_exists('curl_init')) {
			die("Curl is required");
		}

		if ($this->cache === null) {
			$cacheType = Config::CACHE_TYPE;
			$this->cache = new $cacheType;
			if (!$this->cache->Connect('localhost', '11211')) {
				$this->cache = null;
				die('Cant connect to cache server');
			}
		}
	}

	function Login($user, $domain, $pass)
	{
		if ($this->authToken) {
			return true;
		}

		$token = $this->cache->get('GDATA_AUTH_TOKEN');

		if ($token !== false) {
			$this->authToken = $token;
			return true;
		}

		$this->googleDomain = $domain;

		$vars = array (
			'Email'			=> $user.'@'.$domain,
			'Passwd'		=> $pass,
			'accountType'	=> $this->googleAccountType,
			'service'		=> $this->googleService,
		);

		$result = $this->Request('https://www.google.com/accounts/ClientLogin', 'POST', $vars);
		if ($result === false) {
			echo $this->error;
			return false;
		}

		foreach (explode("\n", $result) as $line) {
			if (preg_match('#^SID=(?P<token>.+)$#', $line, $matches)) {
				$this->authToken = trim($matches['token']);
				$this->cache->set('GDATA_AUTH_TOKEN', $this->authToken);
				return true;
				break;
			}
		}
		return false;
	}

	function Request($Path, $type='GET', $Vars="", $timeout=10)
	{
		$result = null;

		// Use CURL if it's available
		$ch = curl_init($Path);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		if (!ini_get('safe_mode') && ini_get('open_basedir') === '') {
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		}
		if($timeout > 0 && $timeout !== false) {
			curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		}

		// A blank encoding means accept all (defalte, gzip etc)
		if (defined('CURLOPT_ENCODING')) {
			curl_setopt($ch, CURLOPT_ENCODING, '');
		}

		if ($type != 'GET') {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
		}

		if($type== 'POST' && $Vars != "") {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $Vars);
		}

		// curl_setopt($ch, CURLOPT_HEADER, true);

		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 2);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true);
		curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__).'/curl-ca-bundle.crt');

		if ($this->authToken) {
			$headers = array(
				'Content-Type: application/atom+xml',
				'Authorization: GoogleLogin auth='.$this->authToken,
			);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}

		$result = curl_exec($ch);

		if ($result === false) {
			trigger_error(curl_error($ch), E_USER_ERROR);
			$this->error = curl_error($ch);
		}

		return $result;
	}


	public function GetUserList($domain)
	{
		$url = 'https://apps-apis.google.com/a/feeds/'.$domain.'/user/2.0';

		$result = $this->Request($url);

		$xmlResult = simplexml_load_string($result);

		return $xmlResult;
	}

	public function GetReport($type)
	{
		$url = 'https://www.google.com/hosted/services/v1.0/reports/ReportingData';

		$xml = '<?xml version="1.0" encoding="UTF-8"?>
		<rest xmlns="google:accounts:rest:protocol"
		    xmlns:xsi=" http://www.w3.org/2001/XMLSchema-instance ">
		    <type>Report</type>
		    <token>'.$this->authToken.'</token>
		    <domain>'.$this->googleDomain.'</domain>
		    <date>'.date('Y-m-d', strtotime('yesterday')).'</date>
		    <page>1</page>
		    <reportType>daily</reportType>
		    <reportName>'.$type.'</reportName>
		</rest>';

		$result = $this->Request($url, 'POST', $xml);

		return $this->ParseCsvReport($result);
	}

	protected function ParseCsvReport($report)
	{
		$fields = array();

		$userData = array();

		$allUsers = array();


		$lines = explode("\n", $report);

		foreach ($lines as $line) {
			if (empty($line)) {
				continue;
			}

			$parsed = str_getcsv($line);

			if (empty($fields)) {
				$fields = $parsed;
				continue;
			}

			$userData = array();

			foreach ($fields as $index => $fieldname) {
				$userData[$fieldname] = $parsed[$index];
			}

			$allUsers[] = $userData;
		}

		return $allUsers;
	}

}
