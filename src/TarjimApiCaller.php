<?php

namespace Joylab\TarjimPhpClient; 

class TarjimApiCaller extends Tarjim {

	public function __construct($config_file_path) {
		parent::__construct($config_file_path);
	}

	/**
	 *
	 */
	public function getMetaFromTarjim() {
		$endpoint = '/api/v1/translationkeys/json/meta/'.$this->project_id;
		$result = $this->doCurlCall($endpoint, 'GET', ['apikey' => $this->apikey]); 
		return $result;
	} 

	/**
	 * Get full results from tarjim
	 */
	public function getLatestFromTarjim() {
		set_error_handler('tarjimErrorHandler');
		$endpoint = '/api/v1/translationkeys/jsonByNameSpaces';
		$post_params = [
			'project_id' => $this->project_id,
			'apikey' => $this->apikey,	
			'namespaces' => $this->namespaces,
		];

		$timeout = $this->get_latest_from_tarjim_timeout;
		$result = $this->doCurlCall($endpoint, 'POST', $post_params, $timeout); 

		restore_error_handler();

		return $result;
	}

	/**
	 *
	 */
	public function uploadImage($key, $image_file, $language, $namespace) {
		set_error_handler('tarjimErrorHandler');
		$endpoint = '/api/v1/uploads/image';

		if (empty($namespace)) {
			$namespace = $this->default_namespace;
		}

		$post_params = [
			'project_id' => $this->project_id,
			'apikey' => $this->apikey,
			'namespace' => $namespace,
			'key' => $key,
			'image_file' => $image_file 
		];

		$languages = [];
		if (!empty($language)) {
			if (is_array($language)) {
				$languages = $language;
			}
			if (is_string($language)) {
				$languages[] = $language;
			}

			if (!empty($languages)) {
				$post_params['languages'] = json_encode($languages);
			}
		}

		$result = $this->doCurlCall($endpoint, 'POST', $post_params, null, false); 
		restore_error_handler();
		return $result;
	}

	/**
	 *
	 */
	public function softDeleteKeys($keys, $namespace) {
		set_error_handler('tarjimErrorHandler');
		$endpoint = '/api/v1/keys/soft-delete';	

		if (empty($namespace)) {
			$namespace = $this->default_namespace;
		}
		
		$data = [];
		if (is_array($keys)) {
			$data = $keys;
		}
		if (is_string($keys)) {
			$data[] = $keys;
		}

		$post_params = [
			'project_id' => $this->project_id,
			'apikey' => $this->apikey,
			'namespace' => $namespace,
			'data' => $data,
		];

		$result = $this->doCurlCall($endpoint, 'POST', $post_params);
		restore_error_handler();
		return $result;
	}

	/**
	 *
	 */
	public function upsert($data, $namespace) {
		set_error_handler('tarjimErrorHandler');

		$endpoint = '/api/v1/keysValues/upsert';	

		if (empty($namespace)) {
			$namespace = $this->default_namespace;
		}

		$post_params = [
			'project_id' => $this->project_id,
			'apikey' => $this->apikey,
			'namespace' => $namespace,
			'data' => $data,
		];

		$result = $this->doCurlCall($endpoint, 'POST', $post_params);
		restore_error_handler();
		return $result;
	}

	/**
	 *
	 */
	public function searchKeys($search_keyword, $namespace) {
		set_error_handler('tarjimErrorHandler');

		$endpoint = '/api/v1/keys/search-for-keys';	

		if (empty($namespace)) {
			$namespace = $this->default_namespace;
		}

		$post_params = [
			'project_id' => $this->project_id,
			'apikey' => $this->apikey,
			'namespace' => $namespace,
			'search_keyword' => $search_keyword,
		];

		$result = $this->doCurlCall($endpoint, 'POST', $post_params);
		restore_error_handler();
		return $result;
	
	}
}
