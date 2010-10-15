<?php
class KVS
{
	protected $data = array();

	function Connect($host, $port)
	{
		return true;
	}

	function get($key)
	{
		if (!isset($this->data[$key])) {
			return false;
		}

		return $this->data[$key];
	}

	function set($key, $val)
	{
		$this->data[$key] = $val;
	}
}