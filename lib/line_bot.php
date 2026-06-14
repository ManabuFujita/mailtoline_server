<?php

use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use \LINE\LINEBot\Constant\HTTPHeader;
use LINE\LINEBot\MessageBuilder\Emoji;

// -----------------------------
// webhook処理
// -----------------------------
function handleWebhook()
{
  // リクエストヘッダーの x-line-signature を取得
  $signature = $_SERVER["HTTP_".HTTPHeader::LINE_SIGNATURE];

  // リクエストボディを取得
  $request_body = file_get_contents('php://input');

  // 署名が正しい場合
  if (validateSignature($request_body, $signature))
  {
    reply();
  }
}

// 署名を検証する関数
function validateSignature($body, $signature)
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
        // $replyMessage = $lineId;
        $replyMessage = '';

        // メールアドレスとフィルターが設定してあれば、設定を返す
        $db = new Mail_gmail;
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

        break;

      default:
        $replyMessage = '⬇下のメニューボタンから操作してください🏠ご意見ご質問等は気づいたときに確認します🍔';
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

  return $response->isSucceeded();
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
