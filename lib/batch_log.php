<?php

/**
 * ログを指定ファイルに書き出す
 */
function writeLog(string $file, string $message): void
{
  error_log('[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", 3, $file);
}

/**
 * 開発環境のみechoするデバッグ用処理
 * $breakBeforeにtrueを指定すると、出力の前に改行を入れる
 */
function debugEcho(string $message, bool $breakBefore = false): void
{
  if (!Config::isProd())
  {
    $prefix = $breakBefore ? '<br>' : '';
    echo $prefix . '[' . date('Y-m-d H:i:s') . '] ' . $message . "<br>\n";
  }
}

/**
 * 管理者にエラー内容をメールで通知する
 */
function notifyAdmin(string $email, string $errorMessage): void
{
    $to = ADMIN_EMAIL; // 管理者メールアドレス
    $subject = '[mailtoline:エラー] バッチ処理でエラーが発生しました';
    $body = "対象メールアドレス: " . $email . "\n"
          . "エラー内容: " . $errorMessage . "\n"
          . "発生日時: " . date('Y-m-d H:i:s');

    mail($to, $subject, $body);
}
