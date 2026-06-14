<?php

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

function updateTokens(GmailRepository $db, array $emailList): void
{
  foreach ($emailList as $l)
  {
    try {
      $lineId = $l['line_id'];
      $email = $l['email'];
      $token = getToken($l);

      // トークンは更新しづづける
      updateToken($db, $lineId, $email, $token);

    } catch (Exception $e) {
      // writeLog(LOG_ERROR, '[トークン更新エラー] ' . $l['email'] . ' : ' . $e->getMessage());
      continue;
    }
  }
}

function updateToken(GmailRepository $db, string $lineId, string $email, array $token): void
{
  $client = newGmailClient();

  $client->setAccessToken($token);

  // トークンが期限切れの場合は更新する
  if ($client->isAccessTokenExpired())
  {
    $client->fetchAccessTokenWithRefreshToken($token['refresh_token']);

    $token = $client->getAccessToken();
    $accessToken = $token['access_token'];
    $refreshToken = $token['refresh_token'];
    $idToken = $token['id_token'];
    $expiresIn = $token['expires_in'];
    // $created = date('Y-m-d H:i:s', $token['created']);
    $created = timestamp2datetime($token['created']);

    // DB更新
    $db->updateToken($lineId, $email, $accessToken, $refreshToken, $idToken, $expiresIn, $created);

    debugEcho('Gmailトークンを更新しました。: ' . $email);
  } else {
    debugEcho('Gmailトークンの更新不要。: ' . $email);
  }
}

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

function getGmailClient(array $token): Google_Client
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

function datetimeFormat2timestamp(string $datetime_format): int
{
  $datetime = new Datetime($datetime_format);
  return datetime2timestamp($datetime);
}

function datetime2timestamp(DateTime $datetime): int
{
  return $datetime->getTimestamp();
}

function timestamp2datetime(int $timestamp): string
{
  return date('Y-m-d H:i:s', $timestamp);
}
