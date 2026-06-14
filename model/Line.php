=<?php
class Line
{
  private $access_token;
  private $id;
  private $url_push;
  private $url_reply;
 
  function __construct()
  {
    // LineName : test_gomi
//    $this->access_token = '/W2Q4hSqlkL62yxc1gXAMI+gY/oGAYcaupkg0skA/KWZirH13NbvFWMGtx29V3Yg//vQnFqbxSvRfsBUaAkelCo3LWBEwS3xiUJANfoj9VrCn6BwdhoCculLHDps/o5aNCrunBrVbhLkjRHH9h3w1QdB04t89/1O/w1cDnyilFU=';
    $this->url_push = 'https://api.line.me/v2/bot/message/push';
    $this->url_reply = 'https://api.line.me/v2/bot/message/reply';
  }
  
  public function setToken($token) {
    $this->access_token = $token;
  }
  
  public function setId($id)
  {
    $this->id = $id;
  }
  
  public function hasId()
  {
    return isset($this->id);
  }

  public function hasToken()
  {
    return isset($this->access_token);
  }
  
  public function push($message)
  {
    if (!$this->hasId())
    {
      print "IDをセットしていません。<br>";
      return false;
    }
    if (!$this->hasToken())
    {
      print "Access Tokenをセットしていません。<br>";
      return false;
    }
    
    // 送信するメッセージ作成
    $messages = array('type' => 'text',
                      'text' => $message);

    $body = json_encode(array('to' => $this->id, // or groupId
                              'messages'   => array($messages)));  // 複数送る場合は、array($mesg1,$mesg2) とする。

    $this->send($this->url_push, $body);
  }
  
  public function reply($message, $reply_token)
  {
    // 送信するメッセージ作成
    $messages = array('type' => 'text',
                      'text' => $message);
    
    $body = json_encode(array('replyToken' => $reply_token,
                              'messages'   => array($messages)));
                              // 複数送る場合は、array($mesg1,$mesg2) とする。

    $this->send($this->url_reply, $body);
    
  }
  
  private function send($url, $body)
  {
    $header = array('Content-Type: application/json',
                    'Authorization: Bearer ' . $this->access_token);
    
    $options = array(CURLOPT_URL            => $url,
                     CURLOPT_CUSTOMREQUEST  => 'POST',
                     CURLOPT_RETURNTRANSFER => true,
                     CURLOPT_HTTPHEADER     => $header,
                     CURLOPT_POSTFIELDS     => $body);

    
    print "<pre>";
    print_r($header);
    print "</pre>";

    print "<pre>";
    print_r($options);
    print "</pre>";

    
    
    $curl = curl_init();

    print "<pre>";
    print_r($curl);
    print "</pre>";

    
    
    curl_setopt_array($curl, $options);
    curl_exec($curl);
    curl_close($curl);
  }

}
?>
