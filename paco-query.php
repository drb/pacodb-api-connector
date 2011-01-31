<?php


class PacoQuery
{
	private $conn;
	private $obj = array();
	private $operators = array('>', '<', '=', '>=', '<=', '>:', '<:', 'lt', 'gt', 'lte', 'gte', 'in');

	public function __construct(PacoConnector $conn_obj, $tbl)
	{
		$this->conn = $conn_obj;

		$this->add('service_id', 	'connector_' . PACO_SERVICE_VERSION);
		//$this->add('host', 		$_SERVER['SERVER_NAME']);

		if (!is_null($tbl))
		{
			$this->add('id', 		$tbl);
		}
	}

	public function add($key, $value)
	{
		if ($this->get($key) !== false)
		{
			$this->remove($key);
		}
		$this->obj[$key] = $value;
	}

	public function remove($key)
	{
		if ($this->get($key) !== false)
		{
			unset($this->obj[$key]);
		}
	}

	public function where(array $filter)
	{
		$f = '';
		foreach(array_keys($filter) as $key)
		{
			$val = $filter[$key];
			if (!is_array($val))
			{
				$f .= "$key{:}$val,";
			}
			else
			{
				list ($operator, $value) = $val;
				if (in_array($operator, $this->operators))
				{
					$operator = str_replace('=', ':', $operator);
					$f .= $key . '{' . $operator . '}' . $value . ",";
				} else
				{
					throw new Exception('Unexpected operator "' . $operator . '" in where filter.');
				}

			}
		}

		$this->add('filter', rtrim($f, ','));

		return $this;
	}

	public function sort(array $sort)
	{
		$s = '';
		foreach($sort as $key=>$value)
		{
			$s .= "$key{:}$value,";
		}

		$this->add('sort', rtrim($s, ','));

		return $this;
	}

	public function limit($int)
	{
		if (is_numeric($int))
		{
			$this->obj['limit'] = $int;
		}
		return $this;
	}

	private function _sort(array $array)
	{
		ksort($array);
		return $array;
	}

	public function get($key=null)
	{
		if (is_null($key))
		{
			return $this->obj;
		}
		else
		{
			if (isset($this->obj[$key]))
			{
				return $this->obj[$key];
			}
		}

		return false;
	}

	public function sign($secret)
	{
		if ($this->get('method'))
		{
			$this->add('api_hash', md5($secret . $this->get('method')));
		}
	}

	/**
	 * Chains
	 */
	public function chain ($delimiter='|')
	{
		$keys = $this->_sort($this->get());

		$str = '';
		foreach (array_keys($keys) as $k)
		{
			$item = $keys[$k];

			//print_r($k . " " . $item . "<br/>");

			// array types need to be iterated over
			if (is_array($item))
			{
				$val = implode($delimiter, $item);
			}
			$str .= "$k=" . urlencode($item) . "&";
			//$str .= "$k=" . $item . "&";
		}
		return rtrim($str, '&');
	}

	public function execute()
	{
		$this->_execute($this);
	}
}
?>
