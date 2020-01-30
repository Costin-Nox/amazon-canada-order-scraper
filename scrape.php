<?php
/**
 * Bootstrap and run.
 * v1.0.1
 * @author  Costin Ghiocel <me@costingcl.com>
 */

require 'vendor/autoload.php';
require 'Colors.php';
require 'AmazonScraper.php';
require 'DataParser.php';

/**
 * This is needed in other objects too.
 * @param  string    $msg     [description]
 * @param  bool|null $isError [description]
 * @return [type]             [description]
 */
function _log(string $msg, ?bool $isError = null) {
    if ($isError)
        echo Colors::getColoredString("[ERROR] {$msg} \n", 'red');
    else
        echo Colors::getColoredString("[INFO] {$msg} \n", 'green');
}

/**
 * dump and die
 * @param  [type] $data [description]
 * @return [type]       [description]
 */
function dd($data) {
	dump($data);
	die;
}

/**
 * Get ini file.
 * @var [type]
 */
$init = parse_ini_file("session.ini");

if (empty($init['cookie'])) {
	_log('Please provide your session cookie inside sessions.ini.', true);
	die;
}
if (empty($init['agent'])) {
	_log('Please provide your agent inside sessions.ini.', true);
	die;
}
/**
 * Get args from shell
 * @var [type]
 */
$options = getopt("y:hrf:");

if (!isset($options['r'])) {
	_log('Run using:  $> php scrape -r  (this will default to 6 month history and will autoname output file)', true);
	_log("To set year:  $> php scrape -r -y=2020 -f='output.csv'");

	die;
}

$filter = empty($options['y']) ? null : (int) $options['y'];
$file = empty($options['f']) ? 'output'.$filter.'.csv' : $options['f'];

/**
 * Run
 */
$amzn   = new AmazonScraper($init['cookie'], $init['agent'], $filter);
$parser = new DataParser($amzn->getResult(), $file);

$filterHuman = $filter ? "for the year {$filter}." : 'for the last 6 months.';
echo Colors::getColoredString("\n*** Purchase statistics {$filterHuman} ***\n\n", 'cyan', 'black');

$stats = $parser->getComputed();
echo Colors::getColoredString("\t Total value of all orders with taxes, exlcuding refunds: \$ {$stats['totalSpent']}\n", 'yellow', 'black');
echo Colors::getColoredString("\t Total value of all items purchased, without taxes or shipping fees: \$ {$stats['totalSpentItems']}\n", 'yellow', 'black');
echo Colors::getColoredString("\t Total number of orders: {$stats['totalOrders']}\n", 'light_purple', 'black');
echo Colors::getColoredString("\t Number of packages shipped to your house: {$stats['packagedReceived']}\n", 'light_purple', 'black');
echo Colors::getColoredString("\t Number of individual items purchased: {$stats['totalItems']}\n", 'light_purple', 'black');
echo Colors::getColoredString("\t Number of individual items returned: {$stats['refundedItemsCount']}\n", 'light_red', 'black');
echo Colors::getColoredString("\t Total value without taxes of refunded items: \$ {$stats['totalRefunded']}\n\n", 'light_red', 'black');
echo Colors::getColoredString("\t Output saved to {$file}\n", 'light_green', 'black');

?>