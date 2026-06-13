<?php

// ログ書き出し処理
function writeLog($file, $message)
{
  error_log('[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", 3, $file);
}

// 管理者に通知する処理
function notifyAdmin($email, $errorMessage)
{
    $to = ADMIN_EMAIL; // 管理者メールアドレス
    $subject = '[mailtoline:エラー] バッチ処理でエラーが発生しました';
    $body = "対象メールアドレス: " . $email . "\n"
          . "エラー内容: " . $errorMessage . "\n"
          . "発生日時: " . date('Y-m-d H:i:s');

    mail($to, $subject, $body);
}
