<?php

/**
 * 1件のフィルター設定に対し、対象期間のメールを検索してLINEに通知する
 */
function processFilter(array $f, GmailRepository $db, DateTimeImmutable $dateStart, DateTimeImmutable $dateEnd): void
{
  $gmailAddress = $f['email'];
  $lineId = $f['line_id'];

  // リフレッシュトークンが無効化されている場合（再認可が必要）は処理しない
  if (empty($f['refresh_token']))
  {
    return;
  }

  debugEcho('　フィルター処理開始: ' . $gmailAddress);

  $token = getToken($f);

  // 今月のLINE送信回数が上限に達していれば、これ以上送信しない
  $now = new DateTimeImmutable();
  $remaining = MONTHLY_SEND_LIMIT - $db->getSendCountThisMonth($lineId, $now);
  if ($remaining <= 0)
  {
    return;
  }

  // echo "<br>";
  // echo "*******************************************<br>";
  // echo "mail:" . $gmailAddress;
  // echo "<br>";

  // echo '<pre>';
  // print_r($token);
  // echo '</pre>';

  $client = getGmailClient($token);


  $filter_mailfrom = $f['mail_from'];
  $filter_subject = $f['subject'];



  // 今日受信した対象メールを取得
  // $client = getClient();
  $service = new Google_Service_Gmail($client);

  $user = 'me';
  $optParams = [];


  $filter = buildFilter($f, $dateStart, $dateEnd);

  debugEcho('　　filter: ' . $filter . ' (' . $dateStart->format('Y-m-d H:i:s') . ' 〜 ' . $dateEnd->format('Y-m-d H:i:s') . ')');

  $optParams['q'] = $filter;
  $filter_results = $service->users_messages->listUsersMessages($user, $optParams);
  $resultsCount = $filter_results['resultSizeEstimate'];

  debugEcho('　　抽出結果: ' . $resultsCount . '件');

  // 対象メールがなければ終了
  if ($resultsCount == 0)
  {
    // echo "今日は通知対象のメールがありません。";
  } else {

    $filter_list = [];

    $result = buildMessages($filter_results, $service, $user, $db, $lineId, $gmailAddress);
    $messages = $result['messages'];
    $sendLogs = $result['sendLogs'];
    $formattedMessages = $result['formattedMessages'];

    debugEcho('　　メッセージ内容:');
    foreach ($formattedMessages as $formattedMessage)
    {
      debugEcho('　　 ' . $formattedMessage);
    }

    // Line通知
    if ($messages != '')
    {
      $messages
        = '💡メール通知' . "\n"
        . "\n"
        . $messages;

      // 今回の送信（1回分）で今月の上限に達する場合、上限到達のメッセージを一緒に送信する
      if ($remaining <= 1)
      {
        $messages .= "\n" . "\n" . '⚠️今月の送信上限（' . MONTHLY_SEND_LIMIT . '件）に達しました。来月まで通知は送信されません。';
      }

      // LINEに通知
      $isSucceeded = push($lineId, $messages);

      // 送信が正常に終了してからDB登録・送信回数を更新
      logSentMessages($lineId, $gmailAddress, $sendLogs, $db, $isSucceeded);
      if ($isSucceeded)
      {
        $db->incrementSendCount($lineId, $now);
      }
    }
  }
}

/**
 * フィルター設定と対象期間からGmail検索クエリを組み立てる
 */
function buildFilter(array $f, DateTimeImmutable $dateStart, DateTimeImmutable $dateEnd): string
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
  // after:/before:に日付のみを指定するとUTCの日境界で評価されJSTとずれるため、
  // Unixタイムスタンプで指定して正確な時刻境界で絞り込む
  $filter .= ' after:' . $dateStart->getTimestamp() . ' before:' . $dateEnd->getTimestamp();

  return $filter;
}

/**
 * 検索結果のメールから未送信分の通知メッセージと送信ログ用データを組み立てる
 *
 * @return array{messages: string, sendLogs: array, formattedMessages: array<string>} 通知メッセージ本文・送信ログ用データ・メールごとの本文の配列
 */
function buildMessages(Google_Service_Gmail_ListMessagesResponse $filter_results, Google_Service_Gmail $service, string $user, GmailRepository $db, string $lineId, string $gmailAddress): array
{
  $messages = '';
  $sendLogs = [];
  $formattedMessages = [];
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
      $formattedMessage = formatMessage($data);
      $messages .= $formattedMessage;
      $formattedMessages[] = $formattedMessage;

      // DB登録（送信後にまとめて登録するため、ここではデータを集めるだけ）
      $now = new DateTimeImmutable();
      $sendLogs[] = ['mailId' => $mailId, 'subject' => $data['subject'], 'from' => $data['from'], 'now' => $now];
    }
  }

  return ['messages' => $messages, 'sendLogs' => $sendLogs, 'formattedMessages' => $formattedMessages];
}

/**
 * 送信が成功した場合のみ、送信履歴をDBに登録する
 */
function logSentMessages(string $lineId, string $gmailAddress, array $sendLogs, GmailRepository $db, bool $isSucceeded): void
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

/**
 * 通知メッセージのフォーマット
 */
function formatMessage(array $data): string
{
  return '■Date:' . "\n". $data['date']
    . "\n" . '■From:' . "\n". $data['from']
    . "\n" . '■Subject:' . "\n". $data['subject']
    ;
}

/**
 * メールヘッダーから件名・日時・From・Toを抽出する
 */
function getData(array $headers): array
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
