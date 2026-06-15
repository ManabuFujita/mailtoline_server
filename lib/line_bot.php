<?php

use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use \LINE\LINEBot\Constant\HTTPHeader;
use LINE\LINEBot\MessageBuilder\Emoji;

/**
 * LINEからのWebhookリクエストを受け取り、署名検証後に返信処理を行う
 */
function handleWebhook(): void
{
  // リクエストヘッダーの x-line-signature を取得
  $signature = $_SERVER["HTTP_".HTTPHeader::LINE_SIGNATURE];

  // リクエストボディを取得
  // php://inputは一度しか読み取れない場合があるため、ここで読み取った内容をreply()に渡す
  $request_body = file_get_contents('php://input');

  // 署名が正しい場合
  if (validateSignature($request_body, $signature))
  {
    reply($request_body, $signature);
  } else {
    // 署名検証エラー（チャネルシークレット不一致の可能性）
    writeLog(LOG_ERROR, '[LINE Webhook署名検証エラー] x-line-signature=' . $signature . ' body=' . $request_body);
  }
}

/**
 * リクエストボディとチャネルシークレットから算出した署名が、
 * リクエストヘッダーの署名と一致するか検証する
 */
function validateSignature(string $body, string $signature): bool
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


/**
 * LINEからのメッセージイベントに応じて返信メッセージを作成し、返信する
 */
function reply(string $inputData, string $signature): void
{
  global $line_channel_access_token;
  global $line_channel_secret;

  //LINEBOTSDKの設定
  $httpClient = new CurlHTTPClient($line_channel_access_token);
  $bot = new LINEBot($httpClient, ['channelSecret' => $line_channel_secret]);
  $events = $bot->parseEventRequest($inputData, $signature);

  //大量にメッセージが送られると複数分のデータが同時に送られてくるため、foreachをしている。
  // （同一人とは限らないため注意）
  foreach($events as $event)
  {
    $sendMessage = new MultiMessageBuilder();
    // $textMessageBuilder = new TextMessageBuilder("test！");
    $message = $event->getText();
    $type = $event->getType();
    $lineId = $event->getUserId();

    // $array = json_decode($event, true);

    // 返信メッセージ作成
    switch ($message)
    {
      case '設定確認':
        $db = new GmailRepository;
        $replyMessage = buildSettingsMessage($db, $lineId);
        break;

      default:
        $replyMessage = '⬇下のメニューボタンから操作してください🏠';
        break;
    }

    // 送信処理
    $textMessageBuilder = new TextMessageBuilder($replyMessage);
    $sendMessage->add($textMessageBuilder);
    $response = $bot->replyMessage($event->getReplyToken(), $sendMessage);

    if (!$response->isSucceeded())
    {
      writeLog(LOG_ERROR, '[LINE返信エラー] ' . $response->getHTTPStatus() . ' : ' . $response->getRawBody());
    }
  }
}

/**
 * 「設定確認」に対する返信メッセージを組み立てる
 * 登録済みのメールアドレスとフィルターを一覧表示し、未登録の場合は登録案内を返す
 */
function buildSettingsMessage(GmailRepository $db, string $lineId): string
{
  $replyMessage = '';

  // メールアドレスとフィルターが設定してあれば、設定を返す
  $emailList = $db->getMyGmail($lineId);
  foreach ($emailList as $l)
  {
    $lineId = $l['line_id'];
    $email = $l['email'];

    $filters = $db->getMyFilter($lineId, $email);
    foreach ($filters as $f)
    {
      $mailFrom = $f['mail_from'];
      $subject = $f['subject'];

      if ($replyMessage == '')
      {
        $replyMessage .= "➡設定フィルター📨\n";
      } else {
        $replyMessage .= "\n";
      }

      $replyMessage .= "To: " . $email
        . "\n" . "From: " . $mailFrom
        . "\n" . "Subject: " . $subject
        . "\n";
    }
  }

  // 未設定のメッセージ
  if ($replyMessage == '')
  {
    $replyMessage = '💡設定が登録されていません' . "\n" . 'webサイト（下のメニューボタン）から登録してください🏠';
  }

  return $replyMessage;
}

/**
 * 指定したLINEユーザーにプッシュメッセージを送信する
 *
 * @return bool 送信が成功したかどうか
 */
function push(string $lineId, string $message): bool
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

  return $response->isSucceeded();
}

/**
 * 全友達にメッセージをブロードキャスト送信する
 */
function broadcast(string $message): void
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
