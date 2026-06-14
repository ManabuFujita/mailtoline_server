<?php

// 対象時刻を過ぎていて、まだ実行していない場合のみtrueを返す
// ※BATCH_RUN_TIMESに23:59など日付が変わる直前の時刻を指定すると、
//   日付をまたぐタイミングで実行されない場合があるため避けること
function shouldRunBatch()
{
  $now = new DateTimeImmutable();
  $today = $now->format('Y-m-d');

  $lastRunFile = new FileStore(BATCH_LAST_RUN_FILE);
  $lastSlot = trim((string)$lastRunFile->getFile());

  // 既に過ぎている時刻の中で、一番遅い時刻を対象とする
  $targetSlot = null;

  foreach (BATCH_RUN_TIMES as $time)
  {
    $scheduled = DateTimeImmutable::createFromFormat('Y-m-d H:i', $today . ' ' . $time);

    // まだ対象時刻を過ぎていない場合はスキップ
    if ($now < $scheduled)
    {
      continue;
    }

    // 同じ時刻の実行済みチェック用キー（日付＋時刻）
    $targetSlot = $today . '-' . $time;
  }

  // 対象時刻がない、またはすでに実行済みの場合は実行しない
  if ($targetSlot === null || $lastSlot === $targetSlot)
  {
    return false;
  }

  $lastRunFile->writeFileOverWrite($targetSlot);
  return true;
}

// -----------------------------
// バッチ処理
// -----------------------------
function runBatch()
{
  // ログ出力
  writeLog(LOG_RUN, 'cron実行');
  debugEcho('cron実行', true);

  // 定時時刻を過ぎていて、まだ実行していない場合のみ処理を実行する
  if (!shouldRunBatch())
  {
    return;
  }

  writeLog(LOG_RUN, 'バッチ処理開始');
  debugEcho('バッチ処理開始');

  // メールアドレスリスト取得
  $db = new GmailRepository;
  $emailList = $db->getAllGmail();

  // 登録メールアドレスのトークン更新処理
  // 全メールアドレスについて、期限が切れてたら更新する
  updateTokens($db, $emailList);

  // 日付取得（前日〜翌日を対象とする）
  $now = new DateTimeImmutable();
  $dateStart = $now->modify('-1 day');
  $dateEnd = $now->modify('+1 day');

  // メイン処理
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

  // ログ出力
  writeLog(LOG_RUN, 'バッチ処理終了');
  debugEcho('バッチ処理終了');
}
