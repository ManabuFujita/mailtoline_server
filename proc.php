<?php

require_once(__DIR__ . "/config.php");
Config::setConfigDirectory(__DIR__ . '/config');

$line_channel_access_token = Config::get('line_channel_access_token');
$line_channel_secret = Config::get('line_channel_secret');

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
  // リクエストヘッダーの x-line-signature を取得
  $signature = $_SERVER["HTTP_".HTTPHeader::LINE_SIGNATURE];

  // リクエストボディを取得
  $request_body = file_get_contents('php://input');

  // 署名が正しい場合
  if (validate_signature($request_body, $signature)) 
  {
    reply();
  }

  return;
}

// メールアドレスリスト取得
$db = new Mail_gmail;
$emailList = $db->getAllGmail();
// echo '<pre>';  
// print_r($list);
// echo '</pre>';


// 登録メールアドレスのトークン更新処理
// 全メールアドレスについて、期限が切れてたら更新する
updateTokens($db, $emailList);
// foreach ($list as $l)
// {
//   $lineId = $l['line_id'];
//   $email = $l['email'];
//   $token = getToken($l);

//   // echo '<pre>';  
//   // print_r($token);
//   // echo '</pre>';

//   // トークンは更新しづづける
//   updateToken($db, $lineId, $email, $token);
// }




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
foreach ($filters as $f)
{
  $gmailAddress = $f['email'];
  $lineId = $f['line_id'];
  $token = getToken($f);

  echo "<br>";
  echo "*******************************************<br>";
  echo "mail:" . $gmailAddress;
  echo "<br>";

  // echo '<pre>';  
  // print_r($token);
  // echo '</pre>';

  // 必要があれば処理を続ける
  $client = getGmailClient($token);


  $filter_mailfrom = $f['mail_from'];
  $filter_subject = $f['subject'];



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

  // echo '<pre>';  
  // print_r($filter_results);
  // echo '</pre>';



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
      echo "<br>";
      echo "mailId:" . $mailId;

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

        // データを処理しやすい形に抽出
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
        // echo '<br>';
        // echo 'DB登録';
        // echo '<br>';
        $db->insertSendlog($lineId, $gmailAddress, $mailId, $data['subject'], $data['from']);
      }

    }

    // Line通知
    if ($messages != '')
    {
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

// -----------------------------------------------------------------------------

function writeSendLog($message)
{
  global $fileSend;

  // cronログ書き出し
  $fileSend->writeFileAdd($message);
}

// 署名を検証する関数
function validate_signature($body, $signature) 
{
  global $line_channel_access_token;
  global $line_channel_secret;

  // ダイジェスト値を計算
  $hash = hash_hmac('sha256', $body, $line_channel_secret, true);
  // Base64エンコード
  $base64_hash = base64_encode($hash);
  // 署名と一致するかチェック
  return $base64_hash === $signature;
}


function reply()
{
  global $line_channel_access_token;
  global $line_channel_secret;

  //LINEBOTにPOSTで送られてきた生データの取得
  $inputData = file_get_contents("php://input");

  // $fileSend->writeFileAdd($inputData);

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

    $source = $event->getSource();
    $userId = $source->getUserId();

    // 返信メッセージ作成
    switch ($message)
    {
    
      case '転送フィルター設定確認':
        // $replyMessage = $event['source']['userId'];
        break;

      case 'あ':
        $replyMessage = $userId;
        break;

      default:
        $replyMessage = '2下のメニューボタンから操作してください。';
        break;
    }

    // 送信処理
    $textMessageBuilder = new TextMessageBuilder($replyMessage);
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

function updateTokens($db, $emailList)
{
  foreach ($emailList as $l)
  {
    $lineId = $l['line_id'];
    $email = $l['email'];
    $token = getToken($l);

    // トークンは更新しづづける
    updateToken($db, $lineId, $email, $token);
  }
}

function updateToken($db, $lineId, $email, $token)
{
  $client = newGmailClient();

  $client->setAccessToken($token);

  echo "<br>";
  echo "---------<br>";
  echo $email;

  
  // echo '<br>';
  // echo '更新前';
  // echo '<pre>';
  // print_r($token);
  // echo '</pre>';
  // echo '<br>';

  // If there is no previous token or it's expired.
  if ($client->isAccessTokenExpired()) 
  {
    // Refresh the token if possible, else fetch a new one.
    // if ($refreshToken != null) 
    // {



        $client->fetchAccessTokenWithRefreshToken($token['refresh_token']);

        $token = $client->getAccessToken();

        // echo '<br>';
        // echo '更新後';
        // echo '<pre>';
        // print_r($token);
        // echo '</pre>';
        // echo '<br>';



        $accessToken = $token['access_token'];
        $refreshToken = $token['refresh_token'];
        $idToken = $token['id_token'];
        $expiresIn = $token['expires_in'];
        // $created = date('Y-m-d H:i:s', $token['created']);
        $created = timestamp2datetime($token['created']);



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

function getGmailClient($token)
{
    $client = newGmailClient();
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
        echo 'tokenを更新しました。(getGmailClient)';
        echo '<br>';
    } else {
      echo '<br>';
      echo 'tokenの更新不要。(getGmailClient)';
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

// function countifArray($array, $find)
// {
//   $cntMatch = 0;
//   foreach($array as $elem)
//   {
//     if(trim($elem) == $find) $cntMatch++;
//   }
//   return $cntMatch;
// }

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
