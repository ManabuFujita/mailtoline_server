#!/usr/local/php/8.2/lib/php
<?php
ini_set("display_errors", 1);

require_once(__DIR__ . "/config.php");
Config::setConfigDirectory(__DIR__ . '/config');

$line_channel_access_token = Config::get('line_channel_access_token');
$line_channel_secret = Config::get('line_channel_secret');

define('ADMIN_EMAIL', Config::get('mail_admin'));
define('LOG_RUN', Config::get('batch_log_run'));
define('LOG_ERROR', Config::get('batch_log_error'));
define('BATCH_LAST_RUN_FILE', Config::get('batch_last_run_file'));
define('BATCH_RUN_TIMES', Config::get('batch_run_times'));

require_once('vendor/autoload.php');
require_once('model/FileStore.php');
require_once('model/GmailRepository.php');
require_once('lib/batch_log.php');
require_once('lib/gmail_token.php');
require_once('lib/mail_filter.php');
require_once('lib/line_bot.php');
require_once('lib/batch.php');

use \LINE\LINEBot\Constant\HTTPHeader;

date_default_timezone_set('Asia/Tokyo');

// -----------------------------------------------------------------------------
// エントリーポイント
// -----------------------------------------------------------------------------

//LINEから送られてきたらWebhook処理、それ以外はcronによるバッチ処理
if(isset($_SERVER["HTTP_".HTTPHeader::LINE_SIGNATURE]))
{
  handleWebhook();
} else {
  runBatch();
}

?>
