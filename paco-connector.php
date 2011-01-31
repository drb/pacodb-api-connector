<?php
/**
 * PacoCMS connector library
 *
 * Requisites:
 *	- A PacoCMS account with admin privileges
 * 	- At least one site set up under your account, and with API access enabled on the site
 * 	- Your API access credentials (key and secret)
 *
 * @author Dave Bullough <support@pacocms.com>
 */
require_once('paco-messaging.php');
require_once('paco-query.php');

define('PACO_SERVICE', 		'http://api.pacocms-dev.com/rest/1.0');
define('PACO_SERVICE_VERSION', 	0.4);
define('PACO_JSON', 		1);
define('PACO_OBJECT', 		2);
define('PACO_STRING', 		3);

class PacoConnector
{
	private 	$api_key, $api_secret, $host, $q_obj, $api_service;
	public 		$html;

	public function __construct($key=null, $secret=null)
	{
		if (!is_null($key) && !is_null($secret))
		{
			if (strlen($key . $secret) != 64)
			{
				throw new Exception('API access credentials supplied to the connector are invalid');
			}
		}
		else
		{
			//echo 'Require crednetials';
		}



		$this->api_service	= PACO_SERVICE;
		$this->api_key 		= $key;
		$this->host 		= $_SERVER['SERVER_NAME'];
		$this->api_secret 	= $secret;

		//print($this->api_service);
	}

	public function get_user()
	{
		return $this->api_key;
	}


	public function get_secret()
	{
		return $this->api_secret;
	}

	/**
	 * Set the API service so we can call none live site
	 */
	public function set_service($service_url)
	{
		$this->api_service = $service_url;
	}


	/**
	 * Sets the host name so queries can be run against other sites
	 */
	public function set_host($host)
	{
		$this->host = $host;
	}

	/**
	 * Used for making direct method calls instead of bucket queries
	 */
	public function get($method=null, array $keypairs=array())
	{
		if (is_null($method))
		{
			throw new Exception('Cannot use call on the API with an empty method');
		}

		if (preg_match("/([a-z]+)\.([a-z]+)/", $method))
		{
			$obj = new PacoQuery($this, null);

			// key always sent
			$obj->add('api_key', 	$this->api_key);
			$obj->add('method', 	$method);

			// loop over the supplied elements and send to the api in our connector object
			if ($keypairs)
			{

				foreach($keypairs as $key=>$val)
				{
					$obj->add($key, $val);
				}
			}

			return $obj;
		}
		else
		{
			throw new Exception('Method defined [' . $method . '] is not valid');
		}
	}

	public function call($method, array $conditions=array())
	{
		$response = $this->execute(
				$this->get($method, $conditions),
				PACO_JSON
		);

		return $response;
	}

	/**
	 * Used for querying buckets
	 */
	public function query($name)
	{
		$obj = new PacoQuery($this, $name);

		// key always sent
		$obj->add('api_key', 	$this->api_key);
		$obj->add('method', 	'bucket.query');

		// return instance of the data object
		return $obj;
	}

	/**
	 * Makes the call back to the Paco webservice
	 */
	private function http_request($service, $params)
	{
		$header = array(
			'errno'=>'',
			'errmsg'=>'',
			'content'=>''
		);

		$url = $service . '?' . $params;

		//print("<p><a style='color:blue;' target='_blank' href='" . $url . "'>Open API query directly</a></p>");

		if  (in_array('curl', get_loaded_extensions()))
		{
			$options = array
			(
				CURLOPT_RETURNTRANSFER => true,     // return web page
				CURLOPT_HEADER         => false,     // don't return headers
				CURLOPT_FOLLOWLOCATION => true,     // follow redirects
				CURLOPT_ENCODING       => "",       // handle all encodings
				CURLOPT_USERAGENT      => "PacoConnector",
				CURLOPT_AUTOREFERER    => true,     // set referer on redirect
				CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
				CURLOPT_TIMEOUT        => 120,      // timeout on response
				CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
			);

			$ch      = curl_init($url);
			curl_setopt_array($ch, $options);
			$content = curl_exec($ch);
			$err     = curl_errno($ch);
			$errmsg  = curl_error($ch);
			$header  = curl_getinfo($ch);
			curl_close($ch);

			$header['errno']   = $err;
			$header['errmsg']  = $errmsg;
			$header['content'] = $content;
		}
		else if (function_exists('file_get_contents'))
		{
			$request = @file_get_contents($url);

			if ($request !== false)
			{
				$header['content'] = $request;
			}
			else
			{
				$header['errmsg'] = 'Failed to make connection';
			}
		}

		/*
		print($params);
		print('<pre>');
		print_r($header['content']);
		print('</pre>');*/

		return $header;
	}

	/**
	 * Createa an HTML form
	 */
	public function form($bucket_id, $class=null)
	{
		global $paco_errors;

		// get the bucket data for the supplied bucket_id
		$response = $this->execute(
				$this->get('bucket.describe', array('id'=>$bucket_id)),
				PACO_JSON
		);


		$commit = false;
		$errors = array();
		$bucket = $response['bucket'][0];
		$fields = $bucket['fields'];

		if ($_POST)
		{
			//$postdata = $this->keypair_array($_POST);
			$postdata = $_POST;

			foreach($postdata as $key=>$val)
			{
				if (is_array($val))
				{
					$postdata[$key] = implode(',', $val);
				}
			}

			// do the insert
			$insert = $this->execute(
				$this->get('bucket.insert', $postdata),
				PACO_JSON
			);

			/*print('<pre>');
			print_r($insert);
			print('</pre>');*/

			if (isset($insert['insert']))
			{
				$errors = $insert['insert']['validation_errors'];
				if (sizeof($errors) > 0)
				{
					echo '<ul>';
					foreach($errors as $key=>$error)
					{
						if (isset($paco_errors['errors'][$key][$error[0]]))
						{
							$msg = $paco_errors['errors'][$key][$error[0]];
						} else
						{
							 $msg = $key . ' failed the ' . $error[0] . ' test';
						}
						echo '<li>' . $msg . '</li>';
					}
					echo '</ul>';
				}
				else
				{
					if ($insert['insert'] == 1)
					{
						$commit = true;

						print("DONE!");
					}
				}
			}
		}

		//print('<pre>');
		//print_r($response);
		//print('</pre>');

		if (!$commit)
		{
			echo '<form action="" method="post">';
			echo '<input type="hidden" name="id" value="' . $bucket['id'] .  '" />';
			echo '<ul>';
			foreach($fields as $field)
			{
				// extract a unique id
				$id = $bucket['id'] . '-' . $field['name'];

				echo '<li>';

				// print labels
				echo '<label for="' . $id . '">' . $field['name'] . '</label>';

				// decide the type of form item
				switch ($field['type'])
				{
				case 'input':

					echo '<input type="input" value="' . $this->globals_extract($field['name']) . '" name="' . $field['name'] . '" id="' . $id . '" />';
					break;
				case 'textarea':

					echo '<textarea name="' . $field['name'] . '" id="' . $id . '">' . $this->globals_extract($field['name']) . '</textarea>';
					break;
				case 'select':
					echo '<select name="' . $field['name'] . '" id="' . $id . '">';
					foreach($field['options'] as $option)
					{
						$selected = '';
						if ($option == $this->globals_extract($field['name']))
						{
							$selected = " selected='selected'";
						}
						echo '<option value="' . $option . '"' . $selected . '>' . $option . '</option>';
					}
					echo '</select>';
					break;
				case 'checkbox':
					foreach($field['options'] as $option)
					{
						$selected = '';
						$choices = $this->globals_extract($field['name']);
						if (is_array($choices))
						{
							if (in_array($option, $choices))
							{
								$selected = " checked='checked'";
							}
						}
						echo '<input type="checkbox" name="' . $field['name'] . '[]" value="' . $option . '"' . $selected . ' /> ' . $option . '<br/>';
					}
					break;
				case 'radio':
					foreach($field['options'] as $option)
					{
						$selected = '';
						if ($option == $this->globals_extract($field['name']))
						{
							$selected = " checked='checked'";
						}
						echo '<input type="radio" value="' . $option . '" name="' . $field['name'] . '"' . $selected . '" /> ' . $option . '<br/>';
					}
					break;
				case 'tags':
					echo '<input type="input" value="' . $this->globals_extract($field['name']) . '" name="' . $field['name'] . '" id="' . $id . '" />';
					break;
				}

				echo '</li>';
			}
			echo '<input type="submit" />';
			echo '</ul>';
			echo '</form>';
		}
	}

	private function globals_extract($key)
	{
		if (isset($_POST[$key]))
		{
			return $_POST[$key];
		}
		return '';
	}

	/**
	 * Resets custom settings
	 */
	public function reset()
	{
		$this->host = $_SERVER['SERVER_NAME'];
	}

	/**
	 * Executes the API calls
	 */
	public function execute(PacoQuery $q, $return_type=PACO_STRING)
	{
		// set the hostname
		$q->add('host', $this->host);

		// sign the api call
		$q->sign($this->api_secret);

		// do the call to the server
		$http = $this->http_request(
			$this->api_service,
			$q->chain()
		);

		switch($return_type)
		{
		case PACO_STRING:
			return $http['content'];
			break;
		case PACO_JSON:
		case PACO_OBJECT:
			return json_decode($http['content'], ($return_type == PACO_OBJECT ? false : true));
			break;
		}
	}
}
?>
