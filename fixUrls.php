<?php
/**
 * Fix staging paths in database content
 *
 * The script looks for instances of absolute links with the staging URL and replaces these with /
 * For example DB content with the following HTML: <img src="http://staging.domain.com/assets/img/person.jpg" alt="The team">
 * Will be translated to: <img src="/assets/img/person.jpg" alt="The team">
 *
 * Basic usage:
 * php fixUrls.php absoluteUrl [table .. table]
 *
 * Example usage:
 * Fixes content with absolute links to staging.studio24.net in the ExpressionEngine content table
 *
 * php fixUrls.php staging.studio24.net exp_channel_data
 *
 * See full help docs: php fixUrls.php --help
 *
 * @author Simon R Jones <simon@studio24.net>
 * @copyright Studio 24 Ltd
 * @license MIT License (MIT)
 */

ini_set('display_errors', true);
error_reporting(E_ALL);

require __DIR__.'/vendor/autoload.php';

use S24\Tool\MyApplication;

$application = new MyApplication();
$application->run();
