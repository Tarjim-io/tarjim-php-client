<?php
/**
 * Tarjim.io PHP Translation client
 * version: 1.4
 *
 * Requires PHP 5+
 * This file includes the Translationclient Class and
 * the _T() function definition
 *
 */
namespace Joylab\TarjimPhpClient; 
require_once __DIR__.'/functions.php';

class TarjimClient extends Tarjim {
	/**
	 * pass config params to construct
	 */
	public function __construct($config_file_path) {
		parent::__construct($config_file_path);
		$this->TarjimApiCaller = new TarjimApiCaller($config_file_path);
	}

	/**
	 *
	 */
	public function setActiveLanguage($language) {
		global $_T;
		$_T['meta']['active_language'] = $language;
	}

	/**
	 *
	 */
  public function setTranslations($language) {
		global $_T;

    ## Set translation keys
		$_T = $this->getTranslations();

		## for Cakex view translation (non-json encoded)
		$_T['results'] = $_T['results'];
		$_T['meta']['default_namespace'] = $this->default_namespace;
		$_T['meta']['config_file_path'] = $this->config_file_path;
		$this->setActiveLanguage($language);
  }

	/**
	 * Checks tarjim results_last_updated and compare with latest file in cache
	 * if tarjim result is newer than cache pull from tarjim and update cache
	 */
	public function getTranslations() {
		set_error_handler('tarjimErrorHandler');

		if (!file_exists($this->cache_file) || !filesize($this->cache_file) || is_null(file_get_contents($this->cache_file))) {
			$final = $this->TarjimApiCaller->getLatestFromTarjim();
			if ('fail' == $final['status']) {
				restore_error_handler();
				die('failed to get data from tarjim api check error logs for more details');
			}
			$final = $final['result'];
			$this->updateCache($final);
		}
		else {
			$ttl_in_minutes = 15;

			$time_now = time();
			$time_now_in_minutes = (int) ($time_now / 60);
			$locale_last_updated = filemtime($this->cache_file);
			$locale_last_updated_in_minutes = (int) ($locale_last_updated / 60);
			$diff = $time_now_in_minutes - $locale_last_updated_in_minutes;
			## If cache was updated in last $ttl_in_minutes min get data directly from cache
			if ((isset($diff) && $diff < $ttl_in_minutes)) {
				$cache_data = file_get_contents($this->cache_file);
				$final = json_decode($cache_data, true);
			}
			else {
				## Pull meta
				$tarjim_meta = $this->TarjimApiCaller->getMetaFromTarjim();
				if ('fail' == $tarjim_meta['status']) {
					## Get cached data
					$cache_data = file_get_contents($this->cache_file);
					$final = json_decode($cache_data, true);

					## Restore default error handler
					restore_error_handler();

					return $final;
				}
				
				$tarjim_meta = $tarjim_meta['result'];

				## Get cache meta tags
				$cache_meta = file_get_contents($this->cache_file);
				$cache_meta = json_decode($cache_meta, true);

				## If cache if older than tarjim get latest and update cache
				if ($cache_meta['meta']['results_last_update'] < $tarjim_meta['meta']['results_last_update']) {
					$apiResults = $this->TarjimApiCaller->getLatestFromTarjim();
					
					## Get cached data
					if ('fail' == $apiResults['status']) {
						$cache_data = file_get_contents($this->cache_file);
						$final = json_decode($cache_data, true);
					}
					else {
						$final = $apiResults['result'];
					}

					$this->updateCache($final);
				}
				else {
					## Update cache file timestamp
					touch($this->cache_file);
					$locale_last_updated = filemtime($this->cache_file);

					$cache_data = file_get_contents($this->cache_file);
					$final = json_decode($cache_data, true);
				}
			}
		}

		## Restore default error handler
		restore_error_handler();

		return $final;
	}

	/**
	 * Update cache files
	 */
	public function updateCache($latest) {
		set_error_handler('tarjimErrorHandler');
		if (file_exists($this->cache_file)) {
			$cache_backup = file_get_contents($this->cache_file);
			$this->writeToFile($this->cache_backup_file, $cache_backup);
		}

		$encoded = json_encode($latest);
		$this->writeToFile($this->cache_file, $encoded);

		## Restore default error handler
		restore_error_handler();
	}

	/**
	 *
	 */
	public function forceUpdateCache() {
    $result = $this->TarjimApiCaller->getLatestFromTarjim();

		if ('fail' == $result['status']) {
			return $result;
		}
		if (!empty($result['result'])) {
			$this->updateCache($result['result']);

			$this->writeToFile($this->update_cache_log_file, 'cache refreshed on '.date('Y-m-d H:i:s').PHP_EOL, FILE_APPEND);

			return ['status' => $result['status']];
		}
		else {
			$this->writeToFile($this->errors_file, date('Y-m-d H:i:s').' Empty result received '.__LINE__.PHP_EOL, FILE_APPEND);
			return ['status' => 'fail'];
		}
	}

	/**
	 * Upload image to tarjim.io
	 */
	public function uploadImage($key, $image_file, $language = '', $namespace = '') {
		return $this->TarjimApiCaller->uploadImage($key, $image_file, $language, $namespace);
	}
	
	/**
	 *
	 */
	public function softDeleteKeys($keys, $namespace = '') {
		return $this->TarjimApiCaller->softDeleteKeys($keys, $namespace);
	}

	/**
	 *
	 */
	public function addKey($data, $namespace = '') {
		return $this->TarjimApiCaller->upsert($data, $namespace);	
	}

	/**
	 *
	 */
	public function updateKey($data, $namespace = '') {
		return $this->TarjimApiCaller->upsert($data, $namespace);	
	}

	/**
	 *
	 */
	public function upsertKeys($data, $namespace = '') {
		return $this->TarjimApiCaller->upsert($data, $namespace);
	}

	/**
	 *
	 */
	public function getActivelanguages() {
		return $this->TarjimApiCaller->getActivelanguages();
	}

	/**
	 *
	 */
	public function searchKeys($search_keyword, $namespace = '') {
		return $this->TarjimApiCaller->searchKeys($search_keyword, $namespace);
	}

  /**
   *
   */
//  public function exportKeysFromView($file_path = null) {
//    $cli = false;
//
//    #check if the function called from api or cli
//		if (php_sapi_name() == 'cli') {
//      $cli = true;
//		}
//
//    ## Set translation keys
//    $active_languages = $this->getActivelanguages();
//
//    if(empty($active_languages)) {
//      if($cli) {
//        echo 'curl error';
//        exit();
//      }
//      else {
//        return 'curl error';
//      }
//    }
//
//    $active_languages = json_decode($active_languages, true);
//
//    if(isset($active_languages['result']['error'])){
//      if($cli) {
//        echo 'Error:'.$active_languages['result']['error'];
//        exit();
//      }
//      else {
//        return 'Error:'.$active_languages['result']['error'];
//      }
//    }
//
//    $active_languages = $active_languages['result']['data'];
//
//    #Dir or file name
//    $view_file = trim($file_path);
//
//    $path_to_file = ROOT.'/'.APP_DIR.'/views/'.$view_file ;
//    $path_to_tmp = ROOT.'/'.APP_DIR.'/tmp/tmp.txt';
//    $path_to_keys = ROOT.'/'.APP_DIR.'/tmp/keys.txt';
//    $path_to_csv = ROOT.'/'.APP_DIR.'/tmp/tarjim_Keys.csv';
//
//    #Get all the line that contains _T and put it in tmp file
//    shell_exec('grep -r _T '.$path_to_file.'>'.$path_to_tmp);
//
//    /*
//     * Put all the _T in new line
//     * From:
//     * text1 _T('key1') text2 _T('key2')
//     * To:
//     * text1
//     * _T('key1') string
//     * _T('key2')
//     */
//    shell_exec('sed -i -e \'s/_T/\n_T/g\' '.$path_to_tmp);
//
//    # Take all line that contains _T only from tmp (remove lines like "text1")
//    shell_exec('grep -r _T '.$path_to_tmp.'>'.$path_to_keys);
//
//    # Remove all before _T( or _TS or _TM(
//    shell_exec('sed -i  \'s/^.*_T[A-Z]\?(//\' '.$path_to_keys);
//
//    # Remove all after )
//    shell_exec('sed -i \'s/).*//\' '.$path_to_keys);
//    # Remove keys that contains $ ($title)
//    shell_exec('sed -i \'/\$/d\' '.$path_to_keys);
//
//    $keys = [];
//
//    # pattern to remove the second param in _T()
//    $pattern = '/\',\'|\' , \'|\', \'|\' ,\'|","|", "|" ,"|" , "/';
//
//    $keys_file = fopen($path_to_keys, "r");
//    if ($keys_file) {
//      while (($line = fgets($keys_file)) !== false) {
//
//        # Check if there are two parameters in _T() and remove second one
//        if (preg_match_all($pattern,$line)) {
//          $key = preg_split($pattern,$line)[0];
//          $keys[] = substr($key, 1);
//        }else{
//
//          # Check if the key is already in the array
//          if (!in_array($line, $keys)) {
//            $keys[] = preg_replace('~^[\'"]?(.*?)[\'"]?$~', '$1', $line);;
//          }
//        }
//      }
//      fclose($keys_file);
//
//
//      $csv_header = $active_languages;
//      array_unshift($csv_header, "key");
//
//      $header_length = count($csv_header);
//      $csv_object[] = $csv_header;
//
//      foreach ($keys as $key) {
//
//        $tmp = array_fill(0, $header_length, '');
//        $tmp[0] = $key;
//        $tmp[1] = $key;
//
//        $csv_object[] = $tmp;
//      }
//
//      // Save csv_object in file
//      $csv_output = fopen($path_to_csv, 'w');
//      foreach ($csv_object as $row) {
//        // generate csv lines from the inner arrays
//        fputcsv($csv_output, $row);
//      }
//
//      fclose($csv_output);
//
//    }
//    else {
//      die('There is no keys');
//    }
//    echo 'You can download CSV file from https://<YOPUR DOMAIN>/api/v1/export-keys-from-view'. PHP_EOL;
//  }
}
