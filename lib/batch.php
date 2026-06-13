<?php

// -----------------------------
// バッチ処理
// -----------------------------
function runBatch()
{
  writeLog(LOG_RUN, 'バッチ開始');

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




  // echo '<br>';
  // echo 'tokenの更新処理後-------------------------------';
  // echo '<br>';


  // 日付取得
  $dateFormatSendLog = 'Y/m/d';
  $dateFormatCronLog = 'Y/m/d H:i:s';

  $dateStart = new DateTimeImmutable();
  $dateEnd = new DateTimeImmutable();

  // echo "<br>";
  // echo "today:     " . $dateStart->format('Y-m-d H:i:s');
  // echo "<br>";

  $dateStart = $dateStart->add(DateInterval::createFromDateString('-1 day'));
  $dateEnd = $dateEnd->add(DateInterval::createFromDateString('+1 day'));

  // echo "dateStart: " . $dateStart->format('Y-m-d H:i:s');
  // echo "<br>";
  // echo "dateEnd:   " . $dateEnd->format('Y-m-d H:i:s');
  // echo "<br>";

  // $todayYMD = date($dateFormatSendLog);
  // $dateStart = date($dateFormatSendLog, strtotime('-1 day'));
  // $dateEnd = date($dateFormatSendLog, strtotime('+1 day'));


  // MAIN:メールフィルター転送処理
  $filters = $db->getAllFilterWithToken();
  foreach ($filters as $f)
  {
    try {
      processFilter($f, $db, $dateStart, $dateEnd);
    } catch (Exception $e) {
      writeLog(LOG_ERROR, '[バッチエラー] ' . $f['email'] . ' : ' . $e->getMessage());
      notifyAdmin($f['email'], $e->getMessage());
      continue;
    }
  }

  writeLog(LOG_RUN, 'バッチ終了');
}
