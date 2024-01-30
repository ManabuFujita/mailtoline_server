#!/usr/local/php/8.2/lib/php
<?php

// Token
// $line_channel_access_token = 'JF/o5ZHzJtlJcnV6SP6UlADXHCSzlFqYXx1JzKZzxJjhQEmYyFrbn0M990e7LMqHMIZBIFGzXjx888RabDwwuB4m/u+EcnooAQef6jvF4fCZPiJCHxdfdyvGS+lq3gRWgo1kAuA7pIW+lDGexPVS7wdB04t89/1O/w1cDnyilFU=';
// $line_channel_secret = '269971a7bda5dc1dd5ee41e2a62055ef';

require_once("./Config.php");
Config::setConfigDirectory(__DIR__ . '/config');

$line_channel_access_token = Config::get('line_channel_access_token');
$line_channel_secret = Config::get('line_channel_secret');

// 対象のメールアドレス
// $targetMailAddress = 'admin@pa.e-kakushin.com';
// $targetMailAddress = 'notifications@github.com';
// 通知メッセージ
// $msgNotification = '安否確認が届いているかもです！（違ったらすみません。）';

//LINESDKの読み込み
require_once('vendor/autoload.php');
require_once('lib/FuncFile.php');
require_once('model/Mail_gmail.php');

use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use \LINE\LINEBot\Constant\HTTPHeader;

date_default_timezone_set('Asia/Tokyo');

//LINEから送られてきたらtrueになる（Webhook用）
if(isset($_SERVER["HTTP_".HTTPHeader::LINE_SIGNATURE]))
{
  reply();
  return;
}


// test
echo 'ok';
$db = new Mail_gmail;
$list = $db->getAllGmail();

// echo '<pre>';  
// print_r($list);
// echo '</pre>';

// 登録メールアドレスのトークン更新処理
// 無条件で更新する
foreach ($list as $l)
{
  $lineId = $l['line_id'];
  $email = $l['email'];
  $token = getToken($l);

  // echo '<pre>';  
  // print_r($token);
  // echo '</pre>';

  // トークンは更新しづづける
  updateToken($db, $lineId, $email, $token);
}

function getToken($l)
{
  // $lineId = $l['line_id'];
  // $email = $l['email'];

  $accessToken = $l['access_token']; 
  $refreshToken = $l['refresh_token'];
  $idToken = $l['id_token'];
  $expiresIn = $l['expires_in'];
  // $created_datetime = new Datetime($l['created']);
  // $created = $created_datetime->getTimestamp();

  if ($l['created'] != null)
  {
    $created = datetimeFormat2timestamp($l['created']);
  } else {
    $created = null;
  }

  $token = [
    'access_token' => $accessToken,
    'expires_in' => $expiresIn,
    'id_token' => $idToken,
    'created' => $created,
    'refresh_token' => $refreshToken,
  ];

  return $token;
}


echo '<br>';
echo 'tokenの更新処理後-------------------------------';
echo '<br>';


// 日付取得
$dateFormatSendLog = 'Y/m/d';
$dateFormatCronLog = 'Y/m/d H:i:s';

$dateStart = new DateTimeImmutable();
$dateEnd = new DateTimeImmutable();

echo "<br>";
echo "today:     " . $dateStart->format('Y-m-d H:i:s');
echo "<br>";

$dateStart = $dateStart->add(DateInterval::createFromDateString('-1 day'));
$dateEnd = $dateEnd->add(DateInterval::createFromDateString('+1 day'));

echo "dateStart: " . $dateStart->format('Y-m-d H:i:s');
echo "<br>";
echo "dateEnd:   " . $dateEnd->format('Y-m-d H:i:s');
echo "<br>";

// $todayYMD = date($dateFormatSendLog);
// $dateStart = date($dateFormatSendLog, strtotime('-1 day'));
// $dateEnd = date($dateFormatSendLog, strtotime('+1 day'));


// メールフィルター転送処理
$filters = $db->getAllFilterWithToken();


// $list = $db->getAllGmail();
foreach ($filters as $f)
{
  $gmailAddress = $f['email'];
  $lineId = $f['line_id'];
  $token = getToken($f);

  echo '<pre>';  
  print_r($token);
  echo '</pre>';

  // 必要があれば処理を続ける
  $client = getGmailClient($token);


  $filter_mailfrom = $f['mail_from'];
  $filter_subject = $f['subject'];
  echo $filter_mailfrom;
  echo $filter_subject;


  // ファイル処理
  // $fileSend = new FuncFile("logSend.txt");
  // $fileCron = new FuncFile("logCron.txt");

  // cron実行時, ログ書き出し
  // $dateTimeNow = date($dateFormatCronLog);
  // $fileCron->writeFileAdd($dateTimeNow);

  // 今日受信した対象メールを取得
  // $client = getClient();
  $service = new Google_Service_Gmail($client);

  $user = 'me';
  $optParams = [];




  // 昨日の対象メール数を取得
  // $filter = 'to:'.$email; // 自分のメールボックスでも、Toが自分とは限らないため、Toは設定しない
  $filter = '';
  if ($filter_mailfrom != null)
  {
    $filter .= ' from:' . $filter_mailfrom;
  }
  if ($filter_subject != null)
  {
    $filter .= ' subject:' . $filter_subject . '';
  }
  $filter .= ' after:' . $dateStart->format('Y/m/d') . ' before:' . $dateEnd->format('Y/m/d');

  $optParams['q'] = $filter;
  $filter_results = $service->users_messages->listUsersMessages($user, $optParams);
  $resultsCount = $filter_results['resultSizeEstimate'];

  // // 昨日の対象メール数を取得
  // $optParams['q'] = 'from:'.$targetMailAddress.' after:'.$yesterdayYMD.' before:'.$todayYMD;
  // $results = $service->users_messages->listUsersMessages($user, $optParams);
  // $cntTargetRecievedYesterday = $results['resultSizeEstimate'];

  // // 今日の対象メール数を取得
  // $optParams['q'] = 'from:'.$targetMailAddress.' after:'.$todayYMD.' before:'.$tomorrowYMD;
  // $results = $service->users_messages->listUsersMessages($user, $optParams);
  // $cntTargetRecievedToday = $results['resultSizeEstimate'];

  // $cntTargetRecievedTotal = $cntTargetRecievedYesterday
  //                         + $cntTargetRecievedToday;



  echo '<pre>';  
  print_r($filter_results);
  echo '</pre>';



  // 対象メールがなければ終了
  echo '<br>';
  echo '昨日〜今日の通知対象メール数：'.$resultsCount.'件<br>';
  if ($resultsCount == 0)
  {
    echo "今日は通知対象のメールがありません。";
  } else {

    $filter_list = [];
    $messages = '';
    foreach ($filter_results->getMessages() as $r)
    {
      $mailId = $r->getId();
      echo '<pre>';  
      print_r($mailId);
      echo '</pre>';

      // 送信済みチェック
      // 同一IDは再送しない
      if ($db->isSended($lineId, $gmailAddress, $mailId))
      {
        echo '<br>';
        echo '通知済み';
        echo '<br>';
      } else {

        // ToDo:メール内容取得
        $mail = $service->users_messages->get($user, $mailId);
        $headers = $mail->payload->headers;

        // 結果からデータを抽出
        // $subject_key = array_search('Subject', array_column($headers, 'name')); // ヘッダーオブジェクトの配列から件名オブジェクトの連番キーを取得
        // $subject = $headers[$subject_key]->value; // 件名のオブジェクトからvalueプロパティの値を取得（件名の取得）

        // $date_key = array_search('Date', array_column($headers, 'name'));
        // $date = $headers[$date_key]->value;

        // $from_key = array_search('From', array_column($headers, 'name'));
        // $from = $headers[$from_key]->value;

        // $to_key = array_search('To', array_column($headers, 'name'));
        // $to = $headers[$to_key]->value;

        //     // $date_str = "Fri, 26 Jan 2024 02:15:18 +0000 (UTC)";

        // $date = preg_replace('/\s\(\w{3}\)/', '', $date); // " (UTC)"を除く

        // // 配列にセット
        // array_push($filter_list, [
        //     'subject' => $subject,
        //     'date' => DateTime::createFromFormat(DateTimeInterface::RFC2822, $date)->format('Y/m/d H:i:s'),
        //     'from' => $from,
        //     'to' => $to,
        // ]);

        $data = getData($headers);

        if ($messages != '')
        {
          $messages .= "\n" . "\n" . '--------------------' . "\n" . "\n";
        }
        $messages
          .= '■Date:' . "\n". $data['date']
          . "\n" . '■From:' . "\n". $data['from']
          . "\n" . '■Subject:' . "\n". $data['subject']
          ;




        // DB登録
        echo '<br>';
        echo 'DB登録';
        echo '<br>';

        $title = '';
        $from = '';
        $body = '';

        // $db->insertSendlog($lineId, $gmailAddress, $mailId, $data['subject'], $data['from']);
      }

    }

            // Line通知
            echo '<br>';
            echo 'Line通知';
            echo '<br>';

            $messages 
              = '【メール通知】' . "\n" 
              . "\n" 
              . $messages;
    
            // echo '<pre>';  
            // print_r($filter_list);
            // echo '</pre>';
            push($lineId, $messages);
  }
}

function getData($headers)
{
  // 結果からデータを抽出
  $subject_key = array_search('Subject', array_column($headers, 'name')); // ヘッダーオブジェクトの配列から件名オブジェクトの連番キーを取得
  $subject = $headers[$subject_key]->value; // 件名のオブジェクトからvalueプロパティの値を取得（件名の取得）

  $date_key = array_search('Date', array_column($headers, 'name'));
  $date = $headers[$date_key]->value;
  $date = preg_replace('/\s\(\w{3}\)/', '', $date); // " (UTC)"を除く
  $date = DateTime::createFromFormat(DateTimeInterface::RFC2822, $date)->format('Y/m/d H:i:s');

  $from_key = array_search('From', array_column($headers, 'name'));
  $from = $headers[$from_key]->value;

  $to_key = array_search('To', array_column($headers, 'name'));
  $to = $headers[$to_key]->value;

      // $date_str = "Fri, 26 Jan 2024 02:15:18 +0000 (UTC)";

  return [
    'subject' => $subject,
    'date' => $date,
    'from' => $from,
    'to' => $to,
    ];
}

return;

// // メールフィルター転送処理
// $filters = $db->getAllFilterWithToken();


// $list = $db->getAllGmail();
// foreach ($list as $l)
// {
//   $token = getToken($l);


//   // 必要があれば処理を続ける
//   $client = getGmailClient($token);

//   $gmailAddress = $l['email'];
//   $lineId = $l['line_id'];



//   // ファイル処理
//   $fileSend = new FuncFile("logSend.txt");
//   $fileCron = new FuncFile("logCron.txt");

//   // cron実行時, ログ書き出し
//   $dateTimeNow = date($dateFormatCronLog);
//   $fileCron->writeFileAdd($dateTimeNow);

//   // 今日受信した対象メールを取得
//   // $client = getClient();
//   $service = new Google_Service_Gmail($client);

//   $user = 'me';
//   $optParams = [];

//   // 昨日の対象メール数を取得
//   $optParams['q'] = 'from:'.$targetMailAddress.' after:'.$yesterdayYMD.' before:'.$todayYMD;
//   $results = $service->users_messages->listUsersMessages($user, $optParams);
//   $cntTargetRecievedYesterday = $results['resultSizeEstimate'];

//   // 今日の対象メール数を取得
//   $optParams['q'] = 'from:'.$targetMailAddress.' after:'.$todayYMD.' before:'.$tomorrowYMD;
//   $results = $service->users_messages->listUsersMessages($user, $optParams);
//   $cntTargetRecievedToday = $results['resultSizeEstimate'];

//   $cntTargetRecievedTotal = $cntTargetRecievedYesterday
//                           + $cntTargetRecievedToday;



//                           echo '<pre>';  
//                           print_r($results);
//                           echo '</pre>';



//   // 対象メールがなければ終了
//   echo '<br>';
//   echo '昨日〜今日の通知対象メール数：'.$cntTargetRecievedTotal.'件<br>';
//   if ($cntTargetRecievedTotal == 0)
//   {
//     echo "今日は通知対象のメールがありません。";
//     return;
//   }

//   foreach ($results as $r)
//   {
//     $mailId = $r['id'];
//     echo '<br>';
//     echo $mailId;
//     echo '<br>';

//     // 送信済みチェック
//     // 同一IDは再送しない
//     if ($db->isSended($lineId, $gmailAddress, $mailId))
//     {
//       echo '<br>';
//       echo '通知済み';
//       echo '<br>';
//     } else {

//       // ToDo:メール内容取得

//       // Line通知
//       echo '<br>';
//       echo 'Line通知';
//       echo '<br>';
//       // push($lineId, 'test');


//       // DB登録
//       echo '<br>';
//       echo 'DB登録';
//       echo '<br>';

//       $title = '';
//       $from = '';
//       $body = '';

//       $db->insertSendlog($lineId, $gmailAddress, $mailId, $title, $from, $body);
//     }

//   }
// }

// $db->connect();
// $db->setTableName("gmails");
// $db->showTables();

return;

// // 日付取得
// $dateFormatSendLog = 'Y/m/d';
// $dateFormatCronLog = 'Y/m/d H:i:s';
// date_default_timezone_set('Asia/Tokyo');
// $todayYMD = date($dateFormatSendLog);
// $yesterdayYMD = date($dateFormatSendLog, strtotime('-1 day'));
// $tomorrowYMD = date($dateFormatSendLog, strtotime('+1 day'));

// // ファイル処理
// $fileSend = new FuncFile("logSend.txt");
// $fileCron = new FuncFile("logCron.txt");

// // cron実行時, ログ書き出し
// $dateTimeNow = date($dateFormatCronLog);
// $fileCron->writeFileAdd($dateTimeNow);

// // 今日受信した対象メールを取得
// $client = getClient();
// $service = new Google_Service_Gmail($client);

// $user = 'me';
// $optParams = [];

// // 昨日の対象メール数を取得
// $optParams['q'] = 'from:'.$targetMailAddress.' after:'.$yesterdayYMD.' before:'.$todayYMD;
// $results = $service->users_messages->listUsersMessages($user, $optParams);
// $cntTargetRecievedYesterday = $results['resultSizeEstimate'];

// // 今日の対象メール数を取得
// $optParams['q'] = 'from:'.$targetMailAddress.' after:'.$todayYMD.' before:'.$tomorrowYMD;
// $results = $service->users_messages->listUsersMessages($user, $optParams);
// $cntTargetRecievedToday = $results['resultSizeEstimate'];

// $cntTargetRecievedTotal = $cntTargetRecievedYesterday
//                         + $cntTargetRecievedToday;

// // 対象メールがなければ終了
// echo '<br>';
// echo '昨日〜今日の通知対象メール数：'.$cntTargetRecievedTotal.'件<br>';
// if ($cntTargetRecievedTotal == 0)
// {
//   echo "今日は通知対象のメールがありません。";
//   return;
// }

// // 通知ロク取得
// $fileSendArray = $fileSend->getFileArray();
// // 通知ログから昨日の通知数を取得
// $cntSendedYesterday = is_null($fileSendArray) ? 0 : countifArray($fileSendArray, $yesterdayYMD);
// // 通知ログから今日の通知数を取得
// $cntSendedToday = is_null($fileSendArray) ? 0 : countifArray($fileSendArray, $todayYMD);

// $cntSendedTotal = $cntSendedYesterday
//                 + $cntSendedToday;

// echo '昨日〜今日の送信済みの通知数：'.$cntSendedTotal.'件<br>';
// echo '↓<br>';

// // 新規対象メールがあるかチェック
// if($cntTargetRecievedTotal > $cntSendedTotal)
// {
//   // 未通知あり
//   // broadcast($msgNotification);
//   echo 'LINE通知を送信しました。';
// } else {
//   // 全て通知済み
//   echo '昨日〜今日は全て通知済みです。';
//   return;
// }

// // 未通知が昨日のメールの場合、通知ログに昨日の日付を追記
// if($cntTargetRecievedYesterday > $cntSendedYesterday)
// {
//   writeSendLog($yesterdayYMD);
//   return;
// }

// // 未通知が今日のメールの場合、通知ログに今日に日付を追記
// if($cntTargetRecievedToday > $cntSendedToday)
// {
//   writeSendLog($todayYMD);
//   return;
// }

return;

// -----------------------------------------------------------------------------

function writeSendLog($message)
{
  global $fileSend;

  // cronログ書き出し
  $fileSend->writeFileAdd($message);
}

function reply()
{
  global $line_channel_access_token;
  global $line_channel_secret;

  //LINEBOTにPOSTで送られてきた生データの取得
  $inputData = file_get_contents("php://input");

  $fileSend->writeFileAdd($inputData);

  //LINEBOTSDKの設定
  $httpClient = new CurlHTTPClient($line_channel_access_token);
  $bot = new LINEBot($httpClient, ['channelSecret' => $line_channel_secret]);
  $signature = $_SERVER["HTTP_".HTTPHeader::LINE_SIGNATURE];
  $events = $bot->parseEventRequest($inputData, $signature);

  //大量にメッセージが送られると複数分のデータが同時に送られてくるため、foreachをしている。
  foreach($events as $event)
  {
    $sendMessage = new MultiMessageBuilder();
    // $textMessageBuilder = new TextMessageBuilder("test！");
    $message = $event->getText();
    $textMessageBuilder = new TextMessageBuilder($message);
    $sendMessage->add($textMessageBuilder);
    $bot->replyMessage($event->getReplyToken(), $sendMessage);
  }
}

// プッシュメッセージ
function push($lineId, $message)
{
  global $line_channel_access_token;
  global $line_channel_secret;

  //LINEBOTSDKの設定
  $httpClient = new CurlHTTPClient($line_channel_access_token);
  $bot = new LINEBot($httpClient, ['channelSecret' => $line_channel_secret]);

  $sendMessage = new MultiMessageBuilder();
  // $textMessageBuilder = new TextMessageBuilder('一斉送信のテスト');
  $textMessageBuilder = new TextMessageBuilder($message);
  $sendMessage->add($textMessageBuilder);
  // $bot->broadcast($sendMessage);
  $response = $bot->pushMessage($lineId, $sendMessage);

  // echo '<pre>';  
  // print_r($response);
  // echo '</pre>';  
}

// ブロードキャスト
function broadcast($message)
{
  global $line_channel_access_token;
  global $line_channel_secret;

  //LINEBOTSDKの設定
  $httpClient = new CurlHTTPClient($line_channel_access_token);
  $bot = new LINEBot($httpClient, ['channelSecret' => $line_channel_secret]);

  $sendMessage = new MultiMessageBuilder();
  // $textMessageBuilder = new TextMessageBuilder('一斉送信のテスト');
  $textMessageBuilder = new TextMessageBuilder($message);
  $sendMessage->add($textMessageBuilder);
  $bot->broadcast($sendMessage);
}

function newGmailClient()
{
  $client = new Google_Client();
  $client->setApplicationName('Gmail API PHP Quickstart');
  $client->setScopes(Google_Service_Gmail::GMAIL_READONLY);
  $client->setAuthConfig('credentials.json');
  $client->setAccessType('offline');
  $client->setPrompt('select_account consent');

  return $client;
}

function updateToken($db, $lineId, $email, $token)
{
  $client = newGmailClient();

  $client->setAccessToken($token);

  echo "<br>";
  echo "---------<br>";
  echo $email;

  
  echo '<br>';
  echo '更新前';
  echo '<pre>';
  print_r($token);
  echo '</pre>';
  echo '<br>';

  // If there is no previous token or it's expired.
  if ($client->isAccessTokenExpired()) 
  {
    // Refresh the token if possible, else fetch a new one.
    // if ($refreshToken != null) 
    // {



        $client->fetchAccessTokenWithRefreshToken($token['refresh_token']);

        $token = $client->getAccessToken();

        echo '<br>';
        echo '更新後';
        echo '<pre>';
        print_r($token);
        echo '</pre>';
        echo '<br>';



        $accessToken = $token['access_token'];
        $refreshToken = $token['refresh_token'];
        $idToken = $token['id_token'];
        $expiresIn = $token['expires_in'];
        // $created = date('Y-m-d H:i:s', $token['created']);
        $created = timestamp2datetime($token['created']);



        // echo '<br>';
        // echo $accessToken;
        // echo '<br>';

        // echo '<pre>';
        // print_r($token);
        // echo '</pre>';
        // echo '<br>';
        // echo $refreshToken;
        // echo '<br>';

        $db->updateToken($lineId, $email, $accessToken, $refreshToken, $idToken, $expiresIn, $created);
        echo '<br>';
        echo 'tokenを更新しました。';
        echo '<br>';
    // }
  } else {
    echo '<br>';
    echo 'tokenの更新不要。';
    echo '<br>';
  }
}

function getGmailClient($token)
{
    // $client = new Google_Client();
    // $client->setApplicationName('Gmail API PHP Quickstart');
    // $client->setScopes(Google_Service_Gmail::GMAIL_READONLY);
    // $client->setAuthConfig('credentials.json');
    // $client->setAccessType('offline');
    // $client->setPrompt('select_account consent');

    $client = newGmailClient();

    // Load previously authorized token from a file, if it exists.
    // The file token.json stores the user's access and refresh tokens, and is
    // created automatically when the authorization flow completes for the first
    // time.
    // $tokenPath = 'token.json';
    // if (file_exists($tokenPath)){
    //     $accessToken = json_decode(file_get_contents($tokenPath), true);
    //     $client->setAccessToken($accessToken);
    // }

    $client->setAccessToken($token);

    // If there is no previous token or it's expired.
    if ($client->isAccessTokenExpired()) {
        // Refresh the token if possible, else fetch a new one.
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            $client->setAccessToken($accessToken);

            // Check to see if there was an error.
            if (array_key_exists('error', $accessToken)) {
                throw new Exception(join(', ', $accessToken));
            }
        }
        // Save the token to a file.
        // if (!file_exists(dirname($tokenPath))) {
        //     mkdir(dirname($tokenPath), 0700, true);
        // }
        // file_put_contents($tokenPath, json_encode($client->getAccessToken()));

        echo '<br>';
        echo 'tokenを更新しました。';
        echo '<br>';
    } else {
      echo '<br>';
      echo 'tokenの更新不要。';
      echo '<br>';
    }
    return $client;
}








/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient_bak()
{
    $client = new Google_Client();
    $client->setApplicationName('Gmail API PHP Quickstart');
    $client->setScopes(Google_Service_Gmail::GMAIL_READONLY);
    $client->setAuthConfig('credentials.json');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    // Load previously authorized token from a file, if it exists.
    // The file token.json stores the user's access and refresh tokens, and is
    // created automatically when the authorization flow completes for the first
    // time.
    $tokenPath = 'token.json';
    if (file_exists($tokenPath)){
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }

    // If there is no previous token or it's expired.
    if ($client->isAccessTokenExpired()) {
        // Refresh the token if possible, else fetch a new one.
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            $client->setAccessToken($accessToken);

            // Check to see if there was an error.
            if (array_key_exists('error', $accessToken)) {
                throw new Exception(join(', ', $accessToken));
            }
        }
        // Save the token to a file.
        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
    }
    return $client;
}

function countifArray($array, $find)
{
  $cntMatch = 0;
  foreach($array as $elem)
  {
    if(trim($elem) == $find) $cntMatch++;
  }
  return $cntMatch;
}

function datetimeFormat2timestamp($datetime_format)
{
  $datetime = new Datetime($datetime_format);
  return datetime2timestamp($datetime);
}

function datetime2timestamp($datetime)
{
  return $datetime->getTimestamp();
}

function timestamp2datetime($timestamp)
{
  return date('Y-m-d H:i:s', $timestamp);
}

?>
