<?php
// absolute filesystem path to this web root
define('WWW_DIR', dirname(__FILE__));

// absolute filesystem path to the application root
define('APP_DIR', WWW_DIR . '/../../app');

// absolute filesystem path to the libraries
define('LIBS_DIR', WWW_DIR . '/../../libs');
define('FB', FALSE);
define('LANG', 2);
define('SUB', TRUE);
define('CZ', FALSE);
define('EN', TRUE);
define('DIFF', 2);
// uncomment this line if you must temporarily take down your site for maintenance
// require APP_DIR . '/templates/maintenance.phtml';

// load bootstrap file
/*print_R($_REQUEST);
print_r($_POST);
print_r($_GET);*/
require APP_DIR . '/bootstrap.php';
