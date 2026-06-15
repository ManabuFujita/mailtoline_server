<?php

/**
 * Gmail API用のGoogleクライアントを生成する
 */
function newGmailClient(): Google_Client
{
  $client = new Google_Client();
  $client->setApplicationName('Gmail API PHP Quickstart');
  $client->setScopes(Google_Service_Gmail::GMAIL_READONLY);
  $client->setAuthConfig('credentials.json');
  $client->setAccessType('offline');
  $client->setPrompt('select_account consent');

  return $client;
}

/**
 * 登録済みの全メールアドレスについて、Gmailトークンを更新する
 */
function updateTokens(GmailRepository $db, array $emailList): void
{
  foreach ($emailList as $l)
  {
    try {
      $lineId = $l['line_id'];
      $email = $l['email'];
      $token = getToken($l);

      // リフレッシュトークンが無効化されている場合（再認可が必要）は処理しない
      if (isRefreshTokenCleared($token))
      {
        debugEcho('　リフレッシュトークンが削除済みのため処理をスキップします。: ' . $email);
      } else {
        // トークンは更新しづづける
        debugEcho('トークン更新処理開始: ' . $email);
        updateToken($db, $lineId, $email, $token);
        debugEcho('トークン更新処理終了: ' . $email);
      }

    } catch (Exception $e) {
      // writeLog(LOG_ERROR, '[トークン更新エラー] ' . $l['email'] . ' : ' . $e->getMessage());
      continue;
    }
  }
}

/**
 * リフレッシュトークンが削除済み(再認可が必要)かどうかを判定する
 */
function isRefreshTokenCleared(array $token): bool
{
  return empty($token['refresh_token']);
}

/**
 * Gmailトークンが期限切れの場合、リフレッシュしてDBを更新する
 */
function updateToken(GmailRepository $db, string $lineId, string $email, array $token): void
{
  $client = newGmailClient();

  $client->setAccessToken($token);

  // トークンが期限切れでなければ更新不要
  if (!$client->isAccessTokenExpired())
  {
    debugEcho('　Gmailトークンの更新不要。: ' . $email);
    return;
  }

  $creds = $client->fetchAccessTokenWithRefreshToken($token['refresh_token']);

  // リフレッシュトークンが無効な場合、エラーがcredsに含まれる（例外は発生しない）
  if (isset($creds['error']))
  {
    debugEcho('　リフレッシュトークンが無効です（' . $creds['error'] . '）。リフレッシュトークンをクリアします。: ' . $email);
    $db->clearToken($lineId, $email);
    return;
  }

  saveRefreshedToken($db, $lineId, $email, $client->getAccessToken());

  debugEcho('　Gmailトークンを更新しました。: ' . $email);
}

/**
 * リフレッシュ後のトークンをDBに保存する
 */
function saveRefreshedToken(GmailRepository $db, string $lineId, string $email, array $token): void
{
  $accessToken = $token['access_token'];
  $refreshToken = $token['refresh_token'];
  $idToken = $token['id_token'];
  $expiresIn = $token['expires_in'];
  $created = timestamp2datetime($token['created']);

  $db->updateToken($lineId, $email, $accessToken, $refreshToken, $idToken, $expiresIn, $created);
}

/**
 * DBから取得したアカウント情報からGmailトークンの配列を組み立てる
 */
function getToken(array $l): array
{
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

/**
 * トークンをセットしたGmail APIクライアントを取得する
 * トークンが期限切れの場合は、リフレッシュトークンで更新する（無ければ認可フローを実行する）
 */
function getGmailClient(array $token): Google_Client
{
    $client = newGmailClient();
    $client->setAccessToken($token);

    // If there is no previous token or it's expired.
    if ($client->isAccessTokenExpired()) {
        // Refresh the token if possible, else fetch a new one.
        if ($client->getRefreshToken()) {
            $creds = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());

            // リフレッシュトークンが無効な場合、エラーがcredsに含まれる（例外は発生しない）
            if (isset($creds['error'])) {
                throw new Exception($creds['error'] . ': ' . ($creds['error_description'] ?? ''));
            }
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

        // echo '<br>';
        // echo 'tokenを更新しました。(getGmailClient)';
        // echo '<br>';
    } else {
      // echo '<br>';
      // echo 'tokenの更新不要。(getGmailClient)';
      // echo '<br>';
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

/**
 * 'Y-m-d H:i:s'形式の日時文字列をUnixタイムスタンプに変換する
 */
function datetimeFormat2timestamp(string $datetime_format): int
{
  $datetime = new Datetime($datetime_format);
  return datetime2timestamp($datetime);
}

/**
 * DateTimeをUnixタイムスタンプに変換する
 */
function datetime2timestamp(DateTime $datetime): int
{
  return $datetime->getTimestamp();
}

/**
 * Unixタイムスタンプを'Y-m-d H:i:s'形式の日時文字列に変換する
 */
function timestamp2datetime(int $timestamp): string
{
  return date('Y-m-d H:i:s', $timestamp);
}
