<?php

function processFilter($f, $db, $dateStart, $dateEnd)
{
  $gmailAddress = $f['email'];
  $lineId = $f['line_id'];
  $token = getToken($f);

  // echo "<br>";
  // echo "*******************************************<br>";
  // echo "mail:" . $gmailAddress;
  // echo "<br>";

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


  $filter = buildFilter($f, $dateStart, $dateEnd);



  $optParams['q'] = $filter;
  $filter_results = $service->users_messages->listUsersMessages($user, $optParams);
  $resultsCount = $filter_results['resultSizeEstimate'];

  // echo '<pre>';
  // print_r($filter_results);
  // echo '</pre>';



  // 対象メールがなければ終了
  // echo '<br>';
  // echo '昨日〜今日の通知対象メール数：'.$resultsCount.'件<br>';
  if ($resultsCount == 0)
  {
    // echo "今日は通知対象のメールがありません。";
  } else {

    $filter_list = [];

    $result = buildMessages($filter_results, $service, $user, $db, $lineId, $gmailAddress);
    $messages = $result['messages'];
    $sendLogs = $result['sendLogs'];

    // Line通知
    if ($messages != '')
    {
      $messages
        = '💡メール通知' . "\n"
        . "\n"
        . $messages;

      // echo '<pre>';
      // print_r($filter_list);
      // echo '</pre>';

      // LINEに通知
      $isSucceeded = push($lineId, $messages);

      // 送信が正常に終了してからDB登録
      logSentMessages($lineId, $gmailAddress, $sendLogs, $db, $isSucceeded);
    }
  }
}

function buildFilter($f, $dateStart, $dateEnd)
{
    // 昨日の対象メール数を取得
  // $filter = 'to:'.$email; // 自分のメールボックスでも、Toが自分とは限らないため、Toは設定しない
  $filter = '';
  if ($f['mail_from'] != null)
  {
    $filter .= ' from:' . $f['mail_from'];
  }
  if ($f['subject'] != null)
  {
    $filter .= ' subject:' . $f['subject'] . '';
  }
  $filter .= ' after:' . $dateStart->format('Y/m/d') . ' before:' . $dateEnd->format('Y/m/d');

  return $filter;
}

function buildMessages($filter_results, $service, $user, $db, $lineId, $gmailAddress)
{
  $messages = '';
  $sendLogs = [];
  foreach ($filter_results->getMessages() as $r)
  {
    $mailId = $r->getId();
    // echo "<br>";
    // echo "mailId:" . $mailId;

    // 送信済みチェック
    // 同一mailIDは再送しない
    if ($db->isSended($lineId, $gmailAddress, $mailId))
    {
      // 通知済みのメールはスキップ
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
      $messages .= formatMessage($data);

      // DB登録（送信後にまとめて登録するため、ここではデータを集めるだけ）
      $now = new DateTimeImmutable();
      $sendLogs[] = ['mailId' => $mailId, 'subject' => $data['subject'], 'from' => $data['from'], 'now' => $now];
    }
  }

  return ['messages' => $messages, 'sendLogs' => $sendLogs];
}

// 送信が成功した場合のみ、送信履歴をDBに登録する
function logSentMessages($lineId, $gmailAddress, $sendLogs, $db, $isSucceeded)
{
  if (!$isSucceeded)
  {
    return;
  }

  foreach ($sendLogs as $log)
  {
    $db->insertSendlog($lineId, $gmailAddress, $log['mailId'], $log['subject'], $log['from'], $log['now']);
  }
}

// 通知メッセージのフォーマット
function formatMessage($data)
{
  return '■Date:' . "\n". $data['date']
    . "\n" . '■From:' . "\n". $data['from']
    . "\n" . '■Subject:' . "\n". $data['subject']
    ;
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
