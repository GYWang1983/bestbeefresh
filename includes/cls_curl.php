<?php

/**
 * Class CurlHelper
 */
class Curl
{
	protected $supported_formats   = array(
		'xml' => 'application/xml',
		'json' => 'application/json',
		'serialize' => 'application/vnd.php.serialized',
		'php' => 'text/plain',
		'csv' => 'text/csv'
	);
	protected $auto_detect_formats = array(
		'application/xml' => 'xml',
		'text/xml' => 'xml',
		'application/json' => 'json',
		'text/json' => 'json',
		'text/csv' => 'csv',
		'application/csv' => 'csv',
		'application/vnd.php.serialized' => 'serialize'
	);

	protected $rest_server;
	protected $format;
	protected $mime_type;
	protected $http_auth = null;
	protected $http_user = null;
	protected $http_pass = null;

	protected $api_name        = 'X-API-KEY';
	protected $api_key         = null;
	protected $ssl_verify_peer = null;
	protected $ssl_cainfo      = null;
	protected $send_cookies    = null;
	protected $response_string;

	/**
	 * cURL
	 * @var SimplecURL
	 */
	protected $curl = null;

	/**
	 * 构造函数
	 *
	 * @param NeoFrame $neo Neo Frame
	 */
	public function __construct($config = array())
	{
		$this->curl = new SimplecURL();
		$this->initCURL($config);
	}

	/**
	 * 初始化 cURL 环境
	 *
	 * @param array $config
	 */
	public function initCURL($config = array())
	{
		// If a URL was passed to the library
		empty($config) OR $this->initialize($config);
	}

	function __destruct()
	{
		$this->curl->set_defaults();
	}

	/**
	 * initialize
	 *
	 * @access  public
	 * @author  Phil Sturgeon
	 * @author  Chris Kacerguis
	 * @version 1.0
	 */
	public function initialize($config)
	{
		$this->rest_server = @$config['server'];
		if (substr($this->rest_server, -1, 1) != '/')
		{
			$this->rest_server .= '/';
		}

		isset($config['send_cookies']) && $this->send_cookies = $config['send_cookies'];

		isset($config['api_name']) && $this->api_name = $config['api_name'];
		isset($config['api_key']) && $this->api_key = $config['api_key'];

		isset($config['http_auth']) && $this->http_auth = $config['http_auth'];
		isset($config['http_user']) && $this->http_user = $config['http_user'];
		isset($config['http_pass']) && $this->http_pass = $config['http_pass'];
		isset($config['ssl_verify_peer']) && $this->ssl_verify_peer = $config['ssl_verify_peer'];
		isset($config['ssl_cainfo']) && $this->ssl_cainfo = $config['ssl_cainfo'];
	}

	/**
	 * get
	 *
	 * @access  public
	 * @author  Phil Sturgeon
	 * @version 1.0
	 */
	public function get($uri, $params = array(), $format = NULL)
	{
		if ($params)
		{
			$uri .= '?' . (is_array($params) ? http_build_query($params) : $params);
		}
		return $this->_call('get', $uri, NULL, $format);
	}

	/**
	 * post
	 *
	 * @access  public
	 * @author  Phil Sturgeon
	 * @version 1.0
	 */
	public function post($uri, $params = array(), $format = NULL)
	{
		return $this->_call('post', $uri, $params, $format);
	}

	/**
	 * put
	 *
	 * @access  public
	 * @author  Phil Sturgeon
	 * @version 1.0
	 */
	public function put($uri, $params = array(), $format = NULL)
	{
		return $this->_call('put', $uri, $params, $format);
	}

	/**
	 * patch
	 *
	 * @access  public
	 * @author  Dmitry Serzhenko
	 * @version 1.0
	 */
	public function patch($uri, $params = array(), $format = NULL)
	{
		return $this->_call('patch', $uri, $params, $format);
	}

	/**
	 * delete
	 *
	 * @access  public
	 * @author  Phil Sturgeon
	 * @version 1.0
	 */
	public function delete($uri, $params = array(), $format = NULL)
	{
		return $this->_call('delete', $uri, $params, $format);
	}

	/**
	 * api_key
	 *
	 * @access  public
	 * @author  Phil Sturgeon
	 * @version 1.0
	 */
	public function api_key($key, $name = FALSE)
	{
		$this->api_key = $key;

		if ($name !== FALSE)
		{
			$this->api_name = $name;
		}

	}

	/**
	 * language
	 *
	 * @access  public
	 * @author  Phil Sturgeon
	 * @version 1.0
	 */
	public function language($lang)
	{
		if (is_array($lang))
		{
			$lang = implode(', ', $lang);
		}
		$this->curl->http_header('Accept-Language', $lang);
	}

	/**
	 * header
	 *
	 * @access  public
	 * @author  David Genelid
	 * @version 1.0
	 */
	public function header($header)
	{
		$this->curl->http_header($header);
	}

	/**
	 * _call
	 *
	 * @access  protected
	 * @author  Phil Sturgeon
	 * @version 1.0
	 */
	protected function _call($method, $uri, $params = array(), $format = NULL)
	{
		if ($format !== NULL)
		{
			$this->format($format);
		}
		$this->http_header('Accept', $this->mime_type);
		// Initialize cURL session
		$this->curl->create($this->rest_server . $uri);
		// If using ssl set the ssl verification value and cainfo
		// contributed by: https://github.com/paulyasi
		if ($this->ssl_verify_peer === FALSE)
		{
			$this->curl->ssl(FALSE);
		}
		elseif ($this->ssl_verify_peer === TRUE)
		{
			$this->ssl_cainfo = getcwd() . $this->ssl_cainfo;
			$this->curl->ssl(TRUE, 2, $this->ssl_cainfo);
		}
		// If authentication is enabled use it
		if ($this->http_auth != '' && $this->http_user != '')
		{
			$this->curl->http_login($this->http_user, $this->http_pass, $this->http_auth);
		}

		// If we have an API Key, then use it
		if ($this->api_key != '')
		{
			$this->curl->http_header($this->api_name, $this->api_key);
		}
		// Send cookies with curl
		if ($this->send_cookies != '')
		{

			$this->curl->set_cookies($_COOKIE);

		}

		// Set the Content-Type (contributed by https://github.com/eriklharper)
		$this->http_header('Content-type', $this->mime_type);

		// We still want the response even if there is an error code over 400
		$this->curl->option('failonerror', FALSE);
		// Call the correct method with parameters
		$this->curl->{$method}($params);
		// Execute and return the response from the REST server
		$response = $this->curl->execute();
		// Format and return
		return $this->_format_response($response);
	}

	/**
	 * initialize
	 *
	 * If a type is passed in that is not supported, use it as a mime type
	 *
	 * @access  public
	 * @author  Phil Sturgeon
	 * @version 1.0
	 */
	public function format($format)
	{
		if (array_key_exists($format, $this->supported_formats))
		{
			$this->format = $format;
			$this->mime_type = $this->supported_formats[$format];
		}
		else
		{
			$this->mime_type = $format;
		}
		return $this;
	}

	/**
	 * debug
	 *
	 * @access  public
	 * @author  Phil Sturgeon
	 * @version 1.0
	 */
	public function debug()
	{
		$request = $this->curl->debug_request();
		echo "=============================================<br/>\n";
		echo "<h2>REST Test</h2>\n";
		echo "=============================================<br/>\n";
		echo "<h3>Request</h3>\n";
		echo $request['url'] . "<br/>\n";
		echo "=============================================<br/>\n";
		echo "<h3>Response</h3>\n";
		if ($this->response_string)
		{
			echo "<code>" . nl2br(htmlentities($this->response_string)) . "</code><br/>\n\n";
		}
		else
		{
			echo "No response<br/>\n\n";
		}
		echo "=============================================<br/>\n";
		if ($this->curl->error_string)
		{
			echo "<h3>Errors</h3>";
			echo "<strong>Code:</strong> " . $this->curl->error_code . "<br/>\n";
			echo "<strong>Message:</strong> " . $this->curl->error_string . "<br/>\n";
			echo "=============================================<br/>\n";
		}
		echo "<h3>Call details</h3>";
		echo "<pre>";
		print_r($this->curl->info);
		echo "</pre>";
	}
	/**
	 * status
	 *
	 * @access  public
	 * @author  Phil Sturgeon
	 * @version 1.0
	 */
	// Return HTTP status code
	public function status()
	{
		return $this->info('http_code');
	}

	/**
	 * info
	 *
	 * Return curl info by specified key, or whole array
	 *
	 * @access  public
	 * @author  Phil Sturgeon
	 * @version 1.0
	 */
	public function info($key = null)
	{
		return $key === null ? $this->curl->info : @$this->curl->info[$key];
	}
	/**
	 * option
	 *
	 * Set custom CURL options
	 *
	 * @access  public
	 * @author  Phil Sturgeon
	 * @version 1.0
	 */
	//
	public function option($code, $value)
	{
		$this->curl->option($code, $value);
	}

	/**
	 * http_header
	 *
	 * @access  public
	 * @author  Phil Sturgeon
	 * @version 1.0
	 */
	public function http_header($header, $content = NULL)
	{
		// Did they use a single argument or two?
		$params = $content ? array($header, $content) : array($header);
		// Pass these attributes on to the curl library
		call_user_func_array(array($this->curl, 'http_header'), $params);
	}

	/**
	 * _format_response
	 *
	 * @access  public
	 * @author  Phil Sturgeon
	 * @version 1.0
	 */
	protected function _format_response($response)
	{
		$this->response_string =& $response;
		// It is a supported format, so just run its formatting method
		if (array_key_exists($this->format, $this->supported_formats))
		{
			return $this->{"_" . $this->format}($response);
		}
		// Find out what format the data was returned in
		$returned_mime = @$this->curl->info['content_type'];
		// If they sent through more than just mime, strip it off
		if (strpos($returned_mime, ';'))
		{
			list($returned_mime) = explode(';', $returned_mime);
		}
		$returned_mime = trim($returned_mime);
		if (array_key_exists($returned_mime, $this->auto_detect_formats))
		{
			return $this->{'_' . $this->auto_detect_formats[$returned_mime]}($response);
		}
		return $response;
	}

	/**
	 * _xml
	 *
	 * Format XML for output
	 *
	 * @access  public
	 * @author  Phil Sturgeon
	 * @version 1.0
	 */
	protected function _xml($string)
	{
		return $string ? (array) simplexml_load_string($string, 'SimpleXMLElement', LIBXML_NOCDATA) : array();
	}

	/**
	 * _csv
	 *
	 * Format HTML for output.  This function is DODGY! Not perfect CSV support but works
	 * with my REST_Controller (https://github.com/philsturgeon/codeigniter-restserver)
	 *
	 * @access  public
	 * @author  Phil Sturgeon
	 * @version 1.0
	 */
	protected function _csv($string)
	{
		$data = array();
		// Splits
		$rows = explode("\n", trim($string));
		$headings = explode(',', array_shift($rows));
		foreach ($rows as $row)
		{
			// The substr removes " from start and end
			$data_fields = explode('","', trim(substr($row, 1, -1)));
			if (count($data_fields) === count($headings))
			{
				$data[] = array_combine($headings, $data_fields);
			}
		}
		return $data;
	}

	/**
	 * _json
	 *
	 * Encode as JSON
	 *
	 * @access  public
	 * @author  Phil Sturgeon
	 * @version 1.0
	 */
	protected function _json($string)
	{
		return json_decode(trim($string));
	}

	/**
	 * _serialize
	 *
	 * Encode as Serialized array
	 *
	 * @access  public
	 * @author  Phil Sturgeon
	 * @version 1.0
	 */
	protected function _serialize($string)
	{
		return unserialize(trim($string));
	}

	/**
	 * _php
	 *
	 * Encode raw PHP
	 *
	 * @access  public
	 * @author  Phil Sturgeon
	 * @version 1.0
	 */
	protected function _php($string)
	{
		$string = trim($string);
		$populated = array();
		eval("\$populated = \"$string\";");
		return $populated;
	}
}


class SimplecURL
{
	private   $last_response = '';       // Contains the cURL last response for debug
	protected $response      = '';       // Contains the cURL response for debug
	protected $session;             // Contains the cURL handler for a session
	protected $url;                 // URL of the session
	protected $options       = array();   // Populates curl_setopt_array
	protected $headers       = array();   // Populates extra HTTP headers
	public    $error_code;             // Error code returned as an int
	public    $error_string;           // Error message returned as a string
	public    $info;                   // Returned after request (elapsed time, etc)

	function __construct($url = '')
	{
		$url AND $this->create($url);
	}

	public function __call($method, $arguments)
	{
		if (in_array($method, array('simple_get', 'simple_post', 'simple_put', 'simple_delete', 'simple_patch')))
		{
			// Take off the "simple_" and past get/post/put/delete/patch to _simple_call
			$verb = str_replace('simple_', '', $method);
			array_unshift($arguments, $verb);
			return call_user_func_array(array($this, '_simple_call'), $arguments);
		}
	}

	/* =================================================================================
	 * SIMPLE METHODS
	 * Using these methods you can make a quick and easy cURL call with one line.
	 * ================================================================================= */

	public function _simple_call($method, $url, $params = array(), $options = array())
	{
		// Get acts differently, as it doesnt accept parameters in the same way
		if ($method === 'get')
		{
			// If a URL is provided, create new session
			$this->create($url . ($params ? '?' . http_build_query($params, NULL, '&') : ''));
		}

		else
		{
			// If a URL is provided, create new session
			$this->create($url);

			$this->{$method}($params);
		}

		// Add in the specific options provided
		$this->options($options);

		return $this->execute();
	}

	public function simple_ftp_get($url, $file_path, $username = '', $password = '')
	{
		// If there is no ftp:// or any protocol entered, add ftp://
		if (!preg_match('!^(ftp|sftp)://! i', $url))
		{
			$url = 'ftp://' . $url;
		}

		// Use an FTP login
		if ($username != '')
		{
			$auth_string = $username;

			if ($password != '')
			{
				$auth_string .= ':' . $password;
			}

			// Add the user auth string after the protocol
			$url = str_replace('://', '://' . $auth_string . '@', $url);
		}

		// Add the filepath
		$url .= $file_path;

		$this->option(CURLOPT_BINARYTRANSFER, TRUE);
		$this->option(CURLOPT_VERBOSE, TRUE);

		return $this->execute();
	}

	/* =================================================================================
	 * ADVANCED METHODS
	 * Use these methods to build up more complex queries
	 * ================================================================================= */

	public function post($params = array(), $options = array())
	{
		// If its an array (instead of a query string) then format it correctly
		if (is_array($params))
		{
			$params = http_build_query($params, NULL, '&');
		}

		// Add in the specific options provided
		$this->options($options);

		$this->http_method('post');

		$this->option(CURLOPT_POST, TRUE);
		$this->option(CURLOPT_POSTFIELDS, $params);
	}

	public function put($params = array(), $options = array())
	{
		// If its an array (instead of a query string) then format it correctly
		if (is_array($params))
		{
			$params = http_build_query($params, NULL, '&');
		}

		// Add in the specific options provided
		$this->options($options);

		$this->http_method('put');
		$this->option(CURLOPT_POSTFIELDS, $params);

		// Override method, I think this overrides $_POST with PUT data but... we'll see eh?
		$this->option(CURLOPT_HTTPHEADER, array('X-HTTP-Method-Override: PUT'));
	}

	public function patch($params = array(), $options = array())
	{
		// If its an array (instead of a query string) then format it correctly
		if (is_array($params))
		{
			$params = http_build_query($params, NULL, '&');
		}

		// Add in the specific options provided
		$this->options($options);

		$this->http_method('patch');
		$this->option(CURLOPT_POSTFIELDS, $params);

		// Override method, I think this overrides $_POST with PATCH data but... we'll see eh?
		$this->option(CURLOPT_HTTPHEADER, array('X-HTTP-Method-Override: PATCH'));
	}

	public function delete($params, $options = array())
	{
		// If its an array (instead of a query string) then format it correctly
		if (is_array($params))
		{
			$params = http_build_query($params, NULL, '&');
		}

		// Add in the specific options provided
		$this->options($options);

		$this->http_method('delete');

		$this->option(CURLOPT_POSTFIELDS, $params);
	}

	public function set_cookies($params = array())
	{
		if (is_array($params))
		{
			$params = http_build_query($params, NULL, '&');
		}

		$this->option(CURLOPT_COOKIE, $params);
		return $this;
	}

	public function http_header($header, $content = NULL)
	{
		$this->headers[] = $content ? $header . ': ' . $content : $header;
		return $this;
	}

	public function http_method($method)
	{
		$this->options[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
		return $this;
	}

	public function http_login($username = '', $password = '', $type = 'any')
	{
		$this->option(CURLOPT_HTTPAUTH, constant('CURLAUTH_' . strtoupper($type)));
		$this->option(CURLOPT_USERPWD, $username . ':' . $password);
		return $this;
	}

	public function proxy($url = '', $port = 80)
	{
		$this->option(CURLOPT_HTTPPROXYTUNNEL, TRUE);
		$this->option(CURLOPT_PROXY, $url . ':' . $port);
		return $this;
	}

	public function proxy_login($username = '', $password = '')
	{
		$this->option(CURLOPT_PROXYUSERPWD, $username . ':' . $password);
		return $this;
	}

	public function ssl($verify_peer = TRUE, $verify_host = 2, $path_to_cert = NULL)
	{
		if ($verify_peer)
		{
			$this->option(CURLOPT_SSL_VERIFYPEER, TRUE);
			$this->option(CURLOPT_SSL_VERIFYHOST, $verify_host);
			if (isset($path_to_cert))
			{
				$path_to_cert = realpath($path_to_cert);
				$this->option(CURLOPT_CAINFO, $path_to_cert);
			}
		}
		else
		{
			$this->option(CURLOPT_SSL_VERIFYPEER, FALSE);
			$this->option(CURLOPT_SSL_VERIFYHOST, FALSE);
		}
		return $this;
	}

	public function options($options = array())
	{
		// Merge options in with the rest - done as array_merge() does not overwrite numeric keys
		foreach ($options as $option_code => $option_value)
		{
			$this->option($option_code, $option_value);
		}

		// Set all options provided
		curl_setopt_array($this->session, $this->options);

		return $this;
	}

	public function option($code, $value, $prefix = 'opt')
	{
		if (is_string($code) && !is_numeric($code))
		{
			$code = constant('CURL' . strtoupper($prefix) . '_' . strtoupper($code));
		}

		$this->options[$code] = $value;
		return $this;
	}

	// Start a session from a URL
	public function create($url)
	{
		$this->url = $url;
		$this->session = curl_init($this->url);

		return $this;
	}

	// End a session and return the results
	public function execute()
	{
		// Set two default options, and merge any extra ones in
		if (!isset($this->options[CURLOPT_TIMEOUT]))
		{
			$this->options[CURLOPT_TIMEOUT] = 30;
		}
		if (!isset($this->options[CURLOPT_RETURNTRANSFER]))
		{
			$this->options[CURLOPT_RETURNTRANSFER] = TRUE;
		}
		if (!isset($this->options[CURLOPT_FAILONERROR]))
		{
			$this->options[CURLOPT_FAILONERROR] = TRUE;
		}

		// Only set follow location if not running securely
		if (!ini_get('safe_mode') && !ini_get('open_basedir'))
		{
			// Ok, follow location is not set already so lets set it to true
			if (!isset($this->options[CURLOPT_FOLLOWLOCATION]))
			{
				$this->options[CURLOPT_FOLLOWLOCATION] = TRUE;
			}
		}

		if (!empty($this->headers))
		{
			$this->option(CURLOPT_HTTPHEADER, $this->headers);
		}

		$this->options();

		// Execute the request & and hide all output
		$this->response = curl_exec($this->session);
		$this->info = curl_getinfo($this->session);

		// Request failed
		if ($this->response === FALSE)
		{
			$errno = curl_errno($this->session);
			$error = curl_error($this->session);

			curl_close($this->session);
			$this->set_defaults();

			$this->error_code = $errno;
			$this->error_string = $error;

			return FALSE;
		}

		// Request successful
		else
		{
			curl_close($this->session);
			$this->last_response = $this->response;
			$this->set_defaults();
			return $this->last_response;
		}
	}

	public function is_enabled()
	{
		return function_exists('curl_init');
	}

	public function debug()
	{
		echo "=============================================<br/>\n";
		echo "<h2>CURL Test</h2>\n";
		echo "=============================================<br/>\n";
		echo "<h3>Response</h3>\n";
		echo "<code>" . nl2br(htmlentities($this->last_response)) . "</code><br/>\n\n";

		if ($this->error_string)
		{
			echo "=============================================<br/>\n";
			echo "<h3>Errors</h3>";
			echo "<strong>Code:</strong> " . $this->error_code . "<br/>\n";
			echo "<strong>Message:</strong> " . $this->error_string . "<br/>\n";
		}

		echo "=============================================<br/>\n";
		echo "<h3>Info</h3>";
		echo "<pre>";
		print_r($this->info);
		echo "</pre>";
	}

	public function debug_request()
	{
		return array(
			'url' => $this->url
		);
	}

	public function set_defaults()
	{
		$this->response = '';
		$this->headers = array();
		$this->options = array();
		$this->error_code = NULL;
		$this->error_string = '';
		$this->session = NULL;
	}

}
