<?php

use Joylab\TarjimPhpClient\TarjimClient;

/**
 * Tarjim error handler
 */
function tarjimErrorHandler($errno, $errstr, $errfile, $errline) {
  global $_T;
  $Tarjim = new TarjimClient($_T['meta']['config_file_path']);
  $Tarjim->writeToFile($Tarjim->errors_file, date('Y-m-d H:i:s').' Tarjim client error file '.$errfile.' (line '.$errline.'): '.$errstr.PHP_EOL, FILE_APPEND);
}

/**
 * Tarjim.io Translation helper
 * N.B: if calling _T() inside Javascript code, pass the do_addslashes as true
 *
 * Read from the global $_T
 */
///////////////////////////////
function _T($key, $config = [], $debug = false) {
	## Sanity
	if (empty($key)) {
		return;
	}

	if (isset($config['SEO']) && $config['SEO']) {
    return _TSEO($key, $config);
	}


	set_error_handler('tarjimErrorHandler');

	## Check for mappings
	if (isset($config['mappings'])) {
		$mappings = $config['mappings'];
	}

	$namespace = '';
	if (isset($config['namespace'])) {
		$namespace = $config['namespace'];
	}


	$result = getTarjimValue($key, $namespace);
	$value = $result['value'];
	$assign_tarjim_id = $result['assign_tarjim_id'];
	$tarjim_id = $result['tarjim_id'];
	$full_value = $result['full_value'];

	## Check config keys and skip assigning tid and wrapping in a span for certain keys
	# ex: page title, input placeholders, image hrefs...
	if (
		(isset($config['is_page_title']) || in_array('is_page_title', $config)) ||
		(isset($config['skip_assign_tid']) || in_array('skip_assign_tid', $config)) ||
		(isset($config['skip_tid']) || in_array('skip_tid', $config)) ||
		(isset($full_value['skip_tid']) && $full_value['skip_tid'])
	) {
		$assign_tarjim_id = false;
	}

	## Debug mode
	if (!empty($debug)) {
		echo $mode ."\n";
		echo $key . "\n" .$value;
	}


	if (isset($config['do_addslashes']) && $config['do_addslashes']) {
		$result = addslashes($value);
	}

	$sanitized_value = sanitizeResult($key, $value);

	if (isset($mappings)) {
		$sanitized_value = injectValuesIntoTranslation($sanitized_value, $mappings);
	}

	## Restore default error handler
	restore_error_handler();

	if ($assign_tarjim_id) {
		$final_value = assignTarjimId($tarjim_id, $sanitized_value);
		return $final_value;
	}
	else {
		return strip_tags($sanitized_value);
	}
}

/**
 * return dataset with all languages for key
 */
function _TD($key, $config = []) {
	global $_T;
	$namespace = $_T['meta']['default_namespace'];
	$original_active_language = $_T['meta']['active_language'];
	$Tarjim = new TarjimClient($_T['meta']['config_file_path']);

	if (isset($config['namespace'])) {
		$namespace = $config['namespace'];
	}

	$dataset = [];
	$original_key = $key;
	$key_case = $_T['meta']['key_case'];
	switch ($key_case) {
			case 'lower':
				$key = strtolower($original_key);
				break;
			case 'original':
			case 'preserve':
				$key = $original_key;
				break;
			default:
				$key = strtolower($original_key);
				break;
	}

	$translations = $_T['results'];
	if ('all_namespaces' == $namespace) {
		foreach ($translations as $namespace => $namespace_translations) {
			if ('meta' == $namespace) {
				continue;
			};
			foreach ($namespace_translations as $language => $language_translations) {
				$dataset[$namespace][$language] = '';
				if (isset($language_translations[$key])) {
					$Tarjim->setActiveLanguage($language);
					$sanitized_value = sanitizeResult($key, $language_translations[$key]['value']);
					$dataset[$namespace][$language] = $sanitized_value;
				}
			}
		}
	}
	else {
		$namespace_translations = $translations[$namespace];
		foreach ($namespace_translations as $language => $language_translations) {
			$dataset[$language] = '';
			if (isset($language_translations[$key])) {
				$Tarjim->setActiveLanguage($language);
				$sanitized_value = sanitizeResult($key, $language_translations[$key]['value']);
				$dataset[$language] = $sanitized_value;
			}
		}
	}

	$Tarjim->setActiveLanguage($original_active_language);
	return $dataset;
}

/**
 * Shorthand for _T($key, ['skip_tid'])
 * Skip assigning data-tid and wrapping in span
 * used with images, placeholders, title, select/dropdown
 */
function _TS($key, $config = []) {
	$config['skip_tid'] = true;
	return _T($key, $config);
}

/**
 * Alias for _TM()
 */
function _TI($key, $attributes) {
	return _TM($key, $attributes);
}

/**
 * Used for media
 * @param String $key key for media
 * @param Array $attributes attributes for media eg: class, id, width...
 * If received key doesn't have type:image return _T($key) instead
 */
function _TM($key, $attributes=[]) {
	## Sanity
	if (empty($key)) {
		return;
	}

	set_error_handler('tarjimErrorHandler');

	$namespace = '';
	if (isset($attributes['namespace'])) {
		$namespace = $attributes['namespace'];
		unset($attributes['namespace']);
	}

	$result = getTarjimValue($key, $namespace);
	$value = $result['value'];
	$tarjim_id = $result['tarjim_id'];
	$full_value = $result['full_value'];

	$attributes_from_remote = [];
	$sanitized_value = sanitizeResult($key, $value);
	$final_value = 'src='.$sanitized_value.' data-tid='.$tarjim_id;

	if (array_key_exists('attributes', $full_value)) {
		$attributes_from_remote = $full_value['attributes'];
	}

	## Merge attributes from tarjim.io and those received from view
	# for attributes that exist in both arrays take the value from tarjim.io
	$attributes = array_merge($attributes, $attributes_from_remote);
	if (!empty($attributes)) {
		foreach ($attributes as $attribute => $attribute_value) {
			$final_value .= ' ' .$attribute . '="' . $attribute_value .'"';
		}
	}

	## Restore default error handler
	restore_error_handler();
	return $final_value;
}

/**
 * Format numbers/prices
 */
function _TF($value, $type = null, $config = []) {
	if (empty($type)) {
		$bt = debug_backtrace();
		$caller = array_shift($bt);
		die('_TF requires "type" of "price", "phone" '.$caller['file'].' on line '.$caller['line']); 
	}

	$language = detectLanguage();

	switch ($type) {
		case 'price':
			return _TPrice($value, $language, $config);	
		case 'phone':
			return _TPhone($value, $config);
		case 'datetime':
			$config['show_time'] = true;
			return _TDateTime($value, $language, $config);
		case 'date':
			return _TDateTime($value, $language, $config);
		default:
			$bt = debug_backtrace();
			$caller = array_shift($bt);
			die('Received unknown type "'.$type.'" '.$caller['file'].' on line '.$caller['line']);
	}
}

/**
 *
 */
function _TPrice($value, $language, $config) {
	if (!is_numeric($value)) {
		if (preg_match('/,/', $value)) {
			$stripped_value = str_replace(',', '', $value);
			if (!is_numeric($stripped_value)) {
				return $value;
			}
			else {
				$value = $stripped_value;
			}
		}
	}
	
	$currency_symbol = '';
	$decimal_places = 0;
	$space_char = '&nbsp;';
	if (!empty($config)) {
		extract($config);
//		if (!empty($config['currency_symbol'])) {
//			$currency_symbol = $config['currency_symbol'];
//		}
//		if (!empty($config['decimal_places'])) {
//			$decimal_places = $config['decimal_places'];
//		}
	}

	if ('fr' == $language) {
		return trim(number_format($value, $decimal_places, ',', $space_char).$space_char.$currency_symbol);
	}

	if ('en' == $language) {
		return $currency_symbol.number_format($value, $decimal_places, '.', ',');
	}	
}

/**
 * for regex explanation: https://stackoverflow.com/questions/4708248/formatting-phone-numbers-in-php
 */
function _TPhone($value, $config) {
	$show_intl_code = false;
	$intl_code = '';
	if (!empty($config)) {
		extract($config);
	}

	$format = '($1) $2-$3';
	if ($show_intl_code && !empty($intl_code)) {
		$format = $intl_code.' '.$format;
	}

	return preg_replace('~.*(\d{3})[^\d]{0,7}(\d{3})[^\d]{0,7}(\d{4}).*~', $format, $value);
}

/**
 *
 */
function _TDateTime($value, $language, $config) {
	$english_days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
	$french_days = array('lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche');

	$english_months = array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
	$french_months = array('janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre');

	$date_time_separator = ' at ';
	if ('fr' == $language) {
		$date_time_separator = ' à ';
	}
	
	$show_time = false;
	$uppercase_date = true;
	if ('fr' == $language) {
		$uppercase_date = false;
	}
	if (!empty($config)) {
		extract($config);
	}
	
	$value = strtotime($value);
	$date = date("j F Y", $value);

	$formatted_datetime = $date;
	if ('fr' == $language) {
		$formatted_datetime = str_replace($english_months, $french_months, $formatted_datetime);
		$formatted_datetime = str_replace($english_days, $french_days, $formatted_datetime);
	}

	if ($uppercase_date) {
		$formatted_datetime = ucwords($formatted_datetime);
	}
	else {
		$formatted_datetime = strtolower($formatted_datetime);
	}

	if ($show_time) {
		$formatted_datetime = $formatted_datetime.$date_time_separator._TTime($value, $config);	
	}

	return $formatted_datetime;
}

/**
 *
 */
function _TTime($value, $config) {
	$hour_minute_separator = ' h ';
	$time_format = '24';
	if (!empty($config)) {
		extract($config);
	}

	$hour = date("H", $value);
	$minute = date("i", $value);
	$period = '';

	if ('12' == $time_format) {
		$hour = date('h', $value);
		$period = ' '.date('a', $value);
	}
	
	$formatted_time = $hour.$hour_minute_separator.$minute.$period;
	return $formatted_time;
}

/**
 * Used for meta tags and site description
 **/
function _TSEO($key, $config = []) {

  if (empty($key)) {
    return;
  }

	if (!isset($config['SEO']) || empty($config['SEO'])) {
    return $key;
	}
  switch ($config['SEO']) {
  case "page_title":
    return _TTT($key);
  case "open_graph":
    return _TMT($key);
  case "twitter_card":
    return _TMT($key);
  case "page_description":
    return _TMT($key);
    break;
  default:
    return $key;
  }

}

/**
 * Used for meta tags like twitter card and Open Graph
 * @param String $key key for media
 */
function _TMT($key) {
  ## Sanity
  if (empty($key)) {
    return;
  }
  set_error_handler('tarjimErrorHandler');

  $result = getTarjimValue($key);
  $value = $result['value'];
  /**
  $tarjim_id = $result['tarjim_id'];
  $full_value = $result['full_value'];
   */

  $sanitized_value = sanitizeResult($key, $value);
  $final_value = '';

  if (json_decode($sanitized_value)) {
    $sanitized_value = json_decode($sanitized_value);
    foreach($sanitized_value as $property => $content ) {
      if (!empty($content)) {
        $final_value .= '<meta property="'.$property.'" content="'.$content.'" />';
      }
    }
  }

  ## Restore default error handler
  restore_error_handler();
  return $final_value;
}

/**
 * Used for title tags like twitter card and Open Graph
 * @param String $key key for media
 */
function _TTT($key) {
  ## Sanity
  if (empty($key)) {
    return;
  }
  set_error_handler('tarjimErrorHandler');

  $result = getTarjimValue($key);
  $value = $result['value'];
  /**
  $tarjim_id = $result['tarjim_id'];
  $full_value = $result['full_value'];
   */

  $sanitized_value = sanitizeResult($key, $value);
  $final_value .= '<title>'.$sanitized_value.'</title>';

  ## Restore default error handler
  restore_error_handler();
  return $final_value;
}

/**
 * Used for description meta tags like twitter card and Open Graph
 * @param String $key key for media
 */
function _TMD($key) {
  ## Sanity
  if (empty($key)) {
    return;
  }
  set_error_handler('tarjimErrorHandler');

  $result = getTarjimValue($key);
  $value = $result['value'];
  /**
  $tarjim_id = $result['tarjim_id'];
  $full_value = $result['full_value'];
   */

  $sanitized_value = sanitizeResult($key, $value);
  $final_value .= '<meta name="description" content="'.$sanitized_value.'">';

  ## Restore default error handler
  restore_error_handler();
  return $final_value;
}

/**
 * Get value for key from global $_T object
 * returns array with
 * value => string to render or media src
 * tarjim_id => id to assign to data-tid
 * assign_tarjim_id => boolean
 * full_value => full object for from $_T to retreive extra attributes if needed
 */
function getTarjimValue($key, $namespace = '') {
	set_error_handler('tarjimErrorHandler');
	global $_T;

	if (empty($namespace)) {
		$namespace = $_T['meta']['default_namespace'];
	}

	$original_key = $key;
	$key_case = $_T['meta']['key_case'];
	switch ($key_case) {
			case 'lower':
				$key = strtolower($original_key);
				break;
			case 'original':
			case 'preserve':
				$key = $original_key;
				break;
			default:
				$key = strtolower($original_key);
				break;
	}

	$active_language = $_T['meta']['active_language'];
	$assign_tarjim_id = false;
	$tarjim_id = '';
	$full_value = [];
	$translations = $_T['results'];

	## Direct match
	if (isset($translations[$namespace][$active_language][$key]) && !empty($translations[$namespace][$active_language][$key])) {
		$mode = 'direct';
		if (is_array($translations[$namespace][$active_language][$key])) {
			if (!empty($translations[$namespace][$active_language][$key]['value'])) {
				$value = $translations[$namespace][$active_language][$key]['value'];
			}
			else {
				$mode = 'empty_value_fallback';
				$value = $original_key;
			}
			$tarjim_id = $translations[$namespace][$active_language][$key]['id'];
			$assign_tarjim_id = true;
			$full_value = $translations[$namespace][$active_language][$key];
		}
		else {
			$value = $translations[$namespace][$active_language][$key];
		}
	}

	## Fallback key
	if (isset($translations[$namespace][$active_language][$key]) && empty($translations[$namespace][$active_language][$key])) {
		$mode = 'key_fallback';
		$value = $original_key;
	}

	## Empty fall back (return key)
	if (!isset($translations[$namespace][$active_language][$key])) {
		$mode = 'empty_key_fallback';
		$value = $original_key;
	}

	$result = [
		'value' => $value,
		'tarjim_id' => $tarjim_id,
		'assign_tarjim_id' => $assign_tarjim_id,
		'full_value' => $full_value,
	];

	## Restore default error handler
	restore_error_handler();

	return $result;
}

/**
 *
 */
function assignTarjimId($id, $value) {
  $result = sprintf('<span data-tid=%s>%s</span>', $id, $value);
  return $result;
}

/**
 * Remove <script> tags from translation value
 * Prevent js injection
 */
function sanitizeResult($key, $result) {
  global $_T;
  $unacceptable_tags = ['script'];
  $unacceptable_attribute_values = [
    'function',
    '{.*}',
  ];

  if ($result != strip_tags($result)) {
    $Tarjim = new TarjimClient($_T['meta']['config_file_path']);
    ## Get meta from cache
    $cache_data = file_get_contents($Tarjim->cache_file);
    $cache_data = json_decode($cache_data, true);
    $cache_results_checksum = $cache_data['meta']['results_checksum'];

    ## Get active language
    if (isset($_T['meta']) && isset($_T['meta']['active_language'])) {
      $active_language = $_T['meta']['active_language'];
    }
    elseif (isset($_SESSION['Config']['language'])) {
      $active_language = $_SESSION['Config']['language'];
    }

    if (file_exists($Tarjim->sanitized_html_cache_file) && filesize($Tarjim->sanitized_html_cache_file) && isset($active_language)) {
      $sanitized_html_cache_file = $Tarjim->sanitized_html_cache_file;
      $cache_file = $Tarjim->cache_file;

      ## Get sanitized cache
      $sanitized_cache = file_get_contents($sanitized_html_cache_file);
      $sanitized_cache = json_decode($sanitized_cache, true);
      $sanitized_cache_checksum = $sanitized_cache['meta']['results_checksum'];

      if (isset($sanitized_cache['results'][$active_language])) {
        $sanitized_cache_results = $sanitized_cache['results'][$active_language];

        ## If locale haven't been updated and key exists in sanitized cache
        # Get from cache
        if ($cache_results_checksum == $sanitized_cache_checksum && array_key_exists($key, $sanitized_cache_results)) {
          return $sanitized_cache['results'][$active_language][$key];
        }
      }
    }

    $dom = new DOMDocument;
    $dom->loadHTML('<?xml encoding="utf-8" ?>'.$result, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    ## Remove unawanted nodes
    foreach ($unacceptable_tags as $tag) {
      ## Get unwanted nodes
      $unwanted_nodes = $dom->getElementsByTagName($tag);
      ## Copy unwanted nodes to loop over without updating length on removal of nodes
      $unwanted_nodes_copy = iterator_to_array($unwanted_nodes);
      foreach ($unwanted_nodes_copy as $unwanted_node) {
        ## Delete node
        $unwanted_node->parentNode->removeChild($unwanted_node);
      }
    }

    $nodes = $dom->getElementsByTagName('*');

    foreach ($nodes as $node) {
      ## Remove unwanted attributes
      if ($node->hasAttributes()) {
        $attributes_copy = iterator_to_array($node->attributes);
        foreach ($attributes_copy as $attr) {
          foreach ($unacceptable_attribute_values as $value) {
            $regex = '/'.$value.'/is';
            if (preg_match_all($regex, $attr->nodeValue)) {
              $node->removeAttribute($attr->nodeName);
              break;
            }
          }
        }
      }
    }

    $sanitized = $dom->saveHTML($dom);
    $stripped = str_replace(['<p>', '</p>'], '', $sanitized);
    cacheSanitizedHTML($key, $stripped, $cache_results_checksum);
    return $stripped;
  }

  return $result;
}

/**
 *
 */
function cacheSanitizedHTML($key, $sanitized, $cache_results_checksum) {
  global $_T;
  $Tarjim = new TarjimClient($_T['meta']['config_file_path']);
  $sanitized_html_cache_file = $Tarjim->sanitized_html_cache_file;

  ## Get active language
  if (isset($_T['meta']) && isset($_T['meta']['active_language'])) {
    $active_language = $_T['meta']['active_language'];
  }
  elseif (isset($_SESSION['Config']['language'])) {
    $active_language = $_SESSION['Config']['language'];
  }
  else {
    return;
  }

  if (file_exists($sanitized_html_cache_file) && filesize($sanitized_html_cache_file)) {
    $sanitized_html_cache = file_get_contents($sanitized_html_cache_file);
    $sanitized_html_cache = json_decode($sanitized_html_cache, true);

    ## If translation cache checksum is changed overwrite sanitized cache
    if ($sanitized_html_cache['meta']['results_checksum'] != $cache_results_checksum) {
      $sanitized_html_cache = [];
    }
  }

  $sanitized_html_cache['meta']['results_checksum'] = $cache_results_checksum;
  $sanitized_html_cache['results'][$active_language][$key] = $sanitized;
  $encoded_sanitized_html_cache = json_encode($sanitized_html_cache);
  $cmd = 'chmod 777 '.$Tarjim->sanitized_html_cache_file;
  exec($cmd);
  $Tarjim->writeToFile($sanitized_html_cache_file, $encoded_sanitized_html_cache);
}

/**
 *
 */
function injectValuesIntoTranslation($translation_string, $mappings) {
  ## Get all keys to replace and save into matches
  $matches = [];
  preg_match_all('/%%.*?%%/', $translation_string, $matches);

  ## Inject values into result
  foreach ($matches[0] as $match) {
    $match_stripped = str_replace('%', '', $match);
    $regex = '/'.$match.'/';
		$translation_string = preg_replace_callback($regex, function ($matches) use($match_stripped, $mappings) {
			return $mappings[$match_stripped];
		}, $translation_string);
		//$translation_string = preg_replace($regex, $mappings[$match_stripped], $translation_string);
  }

  return $translation_string;
}

/**
 *
 */
function detectLanguage() {
	global $_T;
	$Tarjim = new TarjimClient($_T['meta']['config_file_path']);
	$active_language = $_T['meta']['active_language'];

	if (in_array($active_language, $Tarjim->french_language_codes)) {
		return 'fr';
	}

	if (in_array($active_language, $Tarjim->english_language_codes)) {
		return 'en';
	}
}


/**
 * Helper function to create a keys file
 */
function InjectViewKeysIntoTranslationTable() {
  ## TODO 1. Exec the command, and inject the keys into the translations DB (indicating which namespace & language)
  #$cmd = 'grep -ohriE "_T\('.*'\)" ./views/* > keys';
  #exec ($cmd);

}

