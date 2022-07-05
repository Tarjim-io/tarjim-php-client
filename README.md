# README #
## Setup
1. run composer require joylab/tarjim-php-client
2. create php tarjim config file in your project containing
```
<?php
## Required
$project_id = '';
$cache_dir = full path to tarjim cache dir;
$logs_dir = full path to tarjim logs dir;
$apikey = '';
$default_namespace = '';

## Optional
$additional_namespaces = [];
## curl timeout for update cache api calls
$updpate_cache_timeout = 30;
```
3. create tarjim cache and log files in the dir specified above
```
cd CACHE_DIR; touch translations.json translations_backup.json sanitized_html.json;
cd LOGS_DIR; touch errors.log update_cache.log; 
```
4. give permissions for cache and logs and config file
```
chmod -R 777 CACHE_DIR;
chmod -R 777 LOGS_DIR;
chmod 777 CONFIG_FILE; 
```


## Usage
### _T()

* For page titles add config = ['is_page_title' => true];
ex: 
```
<title><?=_T($title_for_layout, ['is_page_title' => true])?> | Panda7</title>
```
and if title was set in controller remove call to _T() from controller

* For placeholders, dropdown/select options, email subject, and swal pass in config skip_assign_tid = true
```
<input placeholder=<?=_T('placeholder', ["skip_assign_tid" => true])?> />
```
skip_assign_tid can also be used for page titles


### To use variables in translation value
* In tarjim.io add the variables you want as %%variable_name%%
* In view pass the mapping in config 
```
_T($key, [
	'mappings' => [
		'var1' => 'var1 value',
	]
]);
```

### Using tarjim for media
* call _TM($key, $attributes=[]) function
* _TI() is an alias of _TM()
* usage ex:
```
// optional
$attributes = [
	class => 'img-class-name',
	width => '100px'
]
<img <?=_TM($key, $attributes)?> />

renders <img src='src' class='img-class-name' width='100px' />
```
* **Important note for media attributes**: 
attributes received from tarjim.io will overwrite attributes received from the function call if same attribute exists in both
so in previous example if this key has attributes: {class: 'class-from-tarjim', height:'200px'} __TM will return 
```
<img src='src' class='class-from-tarjim' width='100px' height='200px'/>
```
notice that width and height are both added

### Using tarjim for datasets
* _TD($key, $config = []);
* returns values for all languages for a key ex: 
```
[
	'en' => 'en values,
	'fr' => 'fr value'
]
```
* config can be ['namespace' => $namespace] if $namespace == 'all_namespaces' returns the values for all namespaces ex:
```
[
	'namespace 1' => [
		'en' => 'en values,
		'fr' => 'fr value'
	],
	'namespace 2' => [
		'en' => 'en value',
		'fr' => 'fr value'
	]
]
```