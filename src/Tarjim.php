<?php 

namespace Joylab\TarjimPhpClient; 
/**
 *
 */
class Tarjim {

		public $apikey, $tarjim_base_url, $project_id, $default_namespace, $additional_namespaces, $cache_dir, $logs_dir, $namespaces, $cache_backup_file, $cache_file, $sanitized_html_cache_file, $errors_file, $update_cache_log_file, $config_file_path, $get_latest_from_tarjim_timeout, $TarjimApiCaller, $key_case;

	public $french_language_codes = [
		'fr',
		'fr_BE',
		'fr_BJ',
		'fr_BF',
		'fr_BI',
		'fr_CM',
		'fr_CA',
		'fr_CF',
		'fr_TD',
		'fr_KM',
		'fr_CG',
		'fr_CD',
		'fr_CI',
		'fr_DJ',
		'fr_GQ',
		'fr_FR',
		'fr_GA',
		'fr_GP',
		'fr_GN',
		'fr_LU',
		'fr_MG',
		'fr_ML',
		'fr_MQ',
		'fr_MC',
		'fr_NE',
		'fr_RW',
		'fr_RE',
		'fr_BL',
		'fr_MF',
		'fr_SN',
		'fr_CH',
		'fr_TG',
	];

	public $english_language_codes = [
		'en',
		'en_AS',
		'en_AU',
		'en_BE',
		'en_BZ',
		'en_BW',
		'en_CA',
		'en_GU',
		'en_HK',
		'en_IN',
		'en_IE',
		'en_IL',
		'en_JM',
		'en_MT',
		'en_MH',
		'en_MU',
		'en_NA',
		'en_NZ',
		'en_MP',
		'en_PK',
		'en_PH',
		'en_SG',
		'en_ZA',
		'en_TT',
		'en_UM',
		'en_VI',
		'en_GB',
		'en_US',
		'en_ZW',
	];

	/**
	 *
	 */
	public function __construct($config_file_path) {
		global $_T;
		if (!isset($_T['meta'])) {
			$_T['meta'] = [
				'config_file_path' => $config_file_path,
			];
		}

		$config = $this->validateConfigVars($config_file_path);

		
		$this->config_file_path = $config_file_path;

		$this->tarjim_base_url = $config['tarjim_base_url'];
		$this->project_id = $config['project_id'];
		$this->apikey = $config['apikey'];
		$this->default_namespace = $config['default_namespace'];
		$this->additional_namespaces = $config['additional_namespaces'];
		$this->cache_dir = $config['cache_dir'];
		$this->logs_dir = $config['logs_dir'];
		$this->get_latest_from_tarjim_timeout = $config['get_latest_from_tarjim_timeout'];
		$this->key_case = $config['key_case'];
		
		if (empty($this->additional_namespaces) || !is_array($this->additional_namespaces)) {
			$this->additional_namespaces = [];
		}
		
		## Set namespaces
		$this->namespaces = $this->additional_namespaces;
		array_unshift($this->namespaces, $this->default_namespace);

		## Set cache files 
		$this->cache_backup_file = $this->cache_dir.'/translations_backup.json';
		$this->cache_file = $this->cache_dir.'/translations.json';
		$this->sanitized_html_cache_file = $this->cache_dir.'/sanitized_html.json';

		## Set log files
		$this->errors_file = $this->logs_dir.'/errors.log';
		$this->update_cache_log_file = $this->logs_dir.'/update_cache.log';

	}

	/**
	 *
	 */
	private function validateConfigVars($config_file_path) {
		$config_file_ext = pathinfo($config_file_path, PATHINFO_EXTENSION);

		if ('php' == $config_file_ext) {
			require($config_file_path);
		}
		else if ('json' == $config_file_ext) {
			$config_vars = json_decode(file_get_contents($config_file_path), true);
			extract($config_vars);	
		}

		if (!isset($project_id)) {
			die('project_id not set');
		}
		if (!isset($apikey)) {
			die('apikey not set');
		}
		if (!isset($default_namespace)) {
			die('default_namespace not set');
		}
		if (!isset($cache_dir)) {
			die('cache_dir not set');
		}
		if (!isset($logs_dir)) {
			die('logs_dir not set');
		}

		if (isset($additional_namespaces) && !is_array($additional_namespaces)) {
			die('additional_namespaces must be an array');
		}

		$get_latest_from_tarjim_timeout = 30;
		if (isset($update_cache_timeout)) {
			if(!is_numeric($update_cache_timeout)) {
				die('update_cache_timeout must be numeric');
			}
			$get_latest_from_tarjim_timeout = $update_cache_timeout;
		}

		$config = [
			'project_id' => $project_id,
			'apikey' => $apikey,
			'default_namespace' => $default_namespace,
			'cache_dir' => $cache_dir,
			'logs_dir' => $logs_dir,
			'additional_namespaces' => isset($additional_namespaces) ? $additional_namespaces : [],
			'get_latest_from_tarjim_timeout' => $get_latest_from_tarjim_timeout,
		];

		if (isset($tarjim_base_url)) {
			$config['tarjim_base_url'] = $tarjim_base_url;
		}
		else {
			$config['tarjim_base_url'] = 'https://app.tarjim.io';
		}

		if (isset($key_case)) {
			$config['key_case'] = $key_case;
		}
		else {
			$config['key_case'] = 'lower';
		}

		return $config;
	}

	/**
	 *
	 */
	public function writeToFile($file, $content, $options = null) {
		if (file_exists($file)) {
			if (is_writable($file)) {
				if (!empty($options)) {
					file_put_contents($file, $content, $options);
				}
				else {
					file_put_contents($file, $content);
				}
			}
			else {
				$error_details = $file.' is not writable';
				$this->reportErrorToApi('file_error', $error_details);
			}
		}
		else {
			$error_details = $file.' does not exist';
			$this->reportErrorToApi('file_error', $error_details);
		}
	}

	/**
	 *
	 */
	public function reportErrorToApi($error_type, $error_details) {
		$endpoint = '/api/v1/report-client-error/';

		if (php_sapi_name() != 'cli') {
			$domain = $_SERVER['HTTP_HOST']; 
		}
		else {
			$domain = 'cli';
		}

		$post_params = [
			'domain' => $domain,
			'project_id' => $this->project_id,
			'apikey' => $this->apikey,	
			'error_type' => $error_type,
			'error_details' => $error_details,
		];

		$result = $this->doCurlCall($endpoint, 'POST', $post_params); 
		return $result;
	}

	/**
	 *
	 */
	public function doCurlCall($endpoint, $method = null, $data = [], $timeout = null, $encode_post_params = true) {
		$api_endpoint = $this->tarjim_base_url.'/'.$endpoint;
		
		$ch = curl_init();
		if ('GET' == $method) {
			$api_endpoint = $api_endpoint.'?'.http_build_query($data, '', '&');
		}
		else {
			$data_encoded = $data;
			if ($encode_post_params) {
				$data_encoded = json_encode($data);
			}

			if ('POST' == $method) {
				curl_setopt($ch, CURLOPT_POST, true);
			}
			else {
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
			}

			curl_setopt($ch, CURLOPT_POSTFIELDS, $data_encoded);
			curl_setopt($ch, CURLOPT_POSTREDIR, 3);
		}

		if (empty($timeout)) {
			$timeout = 10;
		}

		curl_setopt($ch, CURLOPT_URL, $api_endpoint);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		$response = curl_exec($ch);

		if (curl_error($ch)) {
			$this->writeToFile($this->errors_file, date('Y-m-d H:i:s').' Curl error line '.__LINE__.': ' . curl_error($ch).PHP_EOL, FILE_APPEND);
			return ['status' => 'fail'];
		}

		if (empty($response)) {
			$this->writeToFile($this->errors_file, date('Y-m-d H:i:s').' Empty response received '.__LINE__.PHP_EOL, FILE_APPEND);
			return ['status' => 'fail'];
		}

		$decoded = json_decode($response, true);
		if (empty($decoded) || !isset($decoded['status']) || 'fail' == $decoded['status']) {
			$this->writeToFile($this->errors_file, date('Y-m-d H:i:s').' Tarjim Error'.__LINE__.' endpoint: '.$api_endpoint.PHP_EOL.'tarjim response: ' . json_encode($decoded).PHP_EOL, FILE_APPEND);

			if (!empty($decoded) && is_array($decoded) && isset($decoded['result']['error']['message'])) {
				$error_details = $decoded['result']['error']['message'];
				$this->reportErrorToApi('api_error', $error_details);
			}

			return ['status' => 'fail'];
		}

		## Forward compatibility
		if (array_key_exists('result', $decoded)) {
			$decoded = $decoded['result']['data'];
		}

		return ['status' => 'success', 'result' => $decoded];
	}
}
