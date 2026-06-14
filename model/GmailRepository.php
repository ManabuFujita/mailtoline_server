<?php

require_once(__DIR__ . '/Database.php');

class GmailRepository extends Database
{
  private string $table_gmail;
  private string $table_filter;

  /**
   * 利用するテーブル名を設定する
   */
  function __construct()
  {
    parent::__construct();
    $this->table_gmail = 'mail_gmails';
    $this->table_filter = 'mailfilters';
  }

  /**
   * 指定したline_id/emailのGmailトークンを更新する
   */
  public function updateToken(string $lineId, string $email, string $accessToken, string $refreshToken, string $idToken, int $expiresIn, string $created): void
  {
    try {
      $this->pdo->beginTransaction(); // トランザクション開始

      $sql = "UPDATE " . $this->table_gmail . " set "
              . "access_token = '" . $accessToken 
              . "', refresh_token = '" . $refreshToken
              . "', id_token = '" . $idToken 
              . "', expires_in = '" . $expiresIn 
              . "', created = '" . $created 
              . "' WHERE line_id = '" . $lineId
              . "' AND email = '" . $email
              . "'";

      $stmh = $this->pdo->query($sql);
      
      $this->pdo->commit(); // トランザクション終了

      print "Tokenを更新しました。<br>";
    } catch (PDOException $Exception) {

      $this->pdo->rollBack(); // トランザクション失敗の場合、処理を戻す

      print "エラー:".$Exception->getMessage();
    }
  }

  /**
   * 登録済みの全Gmailアカウントを取得する
   */
  public function getAllGmail(): array|null
  {
    try {
      // $sql = "SELECT * FROM " . $this->table_gmail;
      $sql = "SELECT * FROM " . $this->table_gmail;

      $stmh = $this->pdo->query($sql);

      $list = $stmh->fetchAll(PDO::FETCH_ASSOC);
      
      return $list;

      // return $stmh;
      
    } catch (PDOException $Exception) {

      print "エラー:".$Exception->getMessage();
      return null;
    }
    
  }

  /**
   * 指定したline_idに紐づくGmailアカウントを取得する
   */
  public function getMyGmail(string $lineId): array|null
  {
    try {
      $sql = "SELECT * FROM " . $this->table_gmail . " WHERE line_id = '" . $lineId . "'";

      $stmh = $this->pdo->query($sql);

      $list = $stmh->fetchAll(PDO::FETCH_ASSOC);
      
      return $list;

      // return $stmh;
      
    } catch (PDOException $Exception) {

      print "エラー:".$Exception->getMessage();
      return null;
    }
    
  }

  /**
   * 全フィルター設定を、対応するGmailトークン情報と結合して取得する
   */
  public function getAllFilterWithToken(): array|null
  {
    try {
      // $sql = "SELECT * FROM " . $this->table_gmail;

      // emailアドレスで結合する
      $sql = "SELECT"
        . " "  . $this->table_gmail . ".line_id"
        . " ," . $this->table_gmail . ".email"
        . " ," . $this->table_filter . ".mail_from"
        . " ," . $this->table_filter . ".subject"
        . " ," . $this->table_gmail . ".access_token" 
        . " ," . $this->table_gmail . ".refresh_token"
        . " ," . $this->table_gmail . ".id_token"
        . " ," . $this->table_gmail . ".expires_in"
        . " ," . $this->table_gmail . ".created"
        . " FROM " . $this->table_filter
        . " INNER JOIN " . $this->table_gmail
        . " ON " . $this->table_filter . ".email = " . $this->table_gmail . ".email"
        . " ORDER BY " . $this->table_gmail . ".line_id";

      // echo '<br>';
      // echo 'filter';
      // echo '<pre>';
      // print_r($sql);
      // echo '</pre>';
      // echo '<br>';

      $stmh = $this->pdo->query($sql);

      $list = $stmh->fetchAll(PDO::FETCH_ASSOC);

      return $list;

      // return $stmh;
      
    } catch (PDOException $Exception) {

      print "エラー:".$Exception->getMessage();
      return null;
    }
    
  }

  /**
   * 指定したline_id/emailのフィルター設定を取得する
   */
  public function getMyFilter(string $lineId, string $email): array|null
  {
    try {
      // $sql = "SELECT * FROM " . $this->table_gmail;

      // emailアドレスで結合する
      $sql = "SELECT"
        . " "  . $this->table_filter . ".line_id"
        . " ," . $this->table_filter . ".email"
        . " ," . $this->table_filter . ".mail_from"
        . " ," . $this->table_filter . ".subject"
        . " FROM " . $this->table_filter
        . " WHERE line_id = '" . $lineId . "' AND email = '" . $email . "'";

      $stmh = $this->pdo->query($sql);

      $list = $stmh->fetchAll(PDO::FETCH_ASSOC);

      return $list;

      // return $stmh;
      
    } catch (PDOException $Exception) {

      print "エラー:".$Exception->getMessage();
      return null;
    }
    
  }

  /**
   * 送信履歴をsendlogsテーブルに登録する
   */
  public function insertSendlog(string $lineId, string $email, string $mailId, string $title, string $from, DateTimeImmutable $senddate): void
  {
    try {
      $this->pdo->beginTransaction(); // トランザクション開始

      // categoryにrow追加
      $sql = "INSERT INTO sendlogs"
        . " (line_id, email, mail_id, senddate, title, mail_from)"
        . " VALUES (:line_id, :email, :mail_id, '".$senddate->format('Y-m-d H:i:s')."', :title, :mail_from)";

      $stmh = $this->pdo->prepare($sql);

      $stmh->bindValue(':line_id', $lineId, PDO::PARAM_STR);
      $stmh->bindValue(':email', $email, PDO::PARAM_STR);
      $stmh->bindValue(':mail_id', $mailId, PDO::PARAM_STR);
      // $stmh->bindValue(':senddate', $senddate, PDO::PARAM_STR); // datetime型はできない
      $stmh->bindValue(':title', $title, PDO::PARAM_STR);
      $stmh->bindValue(':mail_from', $from, PDO::PARAM_STR);

      $stmh->execute();

      $this->pdo->commit(); // トランザクション終了

    } catch (PDOException $Exception) {

      $this->pdo->rollBack(); // トランザクション失敗の場合、処理を戻す

      print "エラー:".$Exception->getMessage();
    }
  }

  
  /**
   * 指定したline_id/email/mail_idの送信履歴を取得する
   */
  public function getSendlog(string $lineId, string $email, string $mailId): array|null
  {
    try {
      // $sql = "SELECT * FROM " . $this->table_gmail;
      $sql = "SELECT * FROM sendlogs WHERE line_id = '" . $lineId . "' AND email = '" . $email . "' AND mail_id = '" . $mailId . "'";

      $stmh = $this->pdo->query($sql);

      $list = $stmh->fetchAll(PDO::FETCH_ASSOC);
      
      return $list;

      // return $stmh;
      
    } catch (PDOException $Exception) {

      print "エラー:".$Exception->getMessage();
      return null;
    }
  }

  /**
   * 指定したline_idについて、指定した日時が含まれる月のLINE送信回数を取得する
   */
  public function getSendCountThisMonth(string $lineId, DateTimeImmutable $now): int
  {
    try {
      $sql = "SELECT send_count FROM sendcounts WHERE line_id = :line_id AND target_month = :target_month";

      $stmh = $this->pdo->prepare($sql);

      $stmh->bindValue(':line_id', $lineId, PDO::PARAM_STR);
      $stmh->bindValue(':target_month', $now->format('Y-m'), PDO::PARAM_STR);

      $stmh->execute();

      $count = $stmh->fetchColumn();

      return $count === false ? 0 : (int)$count;

    } catch (PDOException $Exception) {

      print "エラー:".$Exception->getMessage();
      return 0;
    }
  }

  /**
   * 指定したline_idについて、指定した日時が含まれる月のLINE送信回数を1増やす
   */
  public function incrementSendCount(string $lineId, DateTimeImmutable $now): void
  {
    try {
      $sql = "INSERT INTO sendcounts (line_id, target_month, send_count)"
        . " VALUES (:line_id, :target_month, 1)"
        . " ON DUPLICATE KEY UPDATE send_count = send_count + 1";

      $stmh = $this->pdo->prepare($sql);

      $stmh->bindValue(':line_id', $lineId, PDO::PARAM_STR);
      $stmh->bindValue(':target_month', $now->format('Y-m'), PDO::PARAM_STR);

      $stmh->execute();

    } catch (PDOException $Exception) {

      print "エラー:".$Exception->getMessage();
    }
  }

  /**
   * 指定したmail_idが既に送信済みかどうかを判定する
   */
  public function isSended(string $lineId, string $email, string $mailId): bool|null
  {
    try {
      $data = $this->getSendlog($lineId, $email, $mailId);
      
      return !empty($data);

      // return $stmh;
      
    } catch (PDOException $Exception) {

      print "エラー:".$Exception->getMessage();
      return null;
    }
  }


// 



//   public function createScheduleTable()
//   {
//     try {
//       $sql = "CREATE TABLE schedule date FROM schedule WHERE date = ".$day." AND ".$category." = 1";

//       $stmh = $this->pdo->query($sql);

//       $row = $stmh->fetch();

//       return $row[0];
      
//     } catch (PDOException $Exception) {

//       print "エラー:".$Exception->getMessage();
//       return null;
//     } 
//   }
  
//   public function createTable($sql)
//   {
//     try {
//       $stmh = $this->pdo->query($sql);
//     } catch (PDOException $Exception) {
//       print "エラー:".$Exception->getMessage();
//     } 
//   }
  
//   public function getDbname()
//   {
//     return $this->db_name;
//   }
  
//   public function getTables()
//   {    
//     $stmh = $this->showTables();
//     $row = $stmh->fetchAll(PDO::FETCH_COLUMN);  // key:0 val:date1, key:1, 
  
//     return $row;
//   }

//   public function showTables()
//   {    
//     try {
//       $sql = "SHOW TABLES";

//       $stmh = $this->pdo->query($sql);

//       print "データを".$stmh->rowCount()."件、取得しました。";
//       return $stmh;
      
//     } catch (PDOException $Exception) {

//       print "エラー:".$Exception->getMessage();
//     }
//     return null;
//   }
  
//   public function getFirstDay()
//   {
//     $stmh = $this->getSchedule();
//     $row = $stmh->fetchAll(PDO::FETCH_COLUMN);  // key:0 val:date1, key:1, date2 の形式
    
//     sort($row);
    
//     return $row[0];
//   }

//   public function getLastDay()
//   {
//     $stmh = $this->getSchedule();
//     $row = $stmh->fetchAll(PDO::FETCH_COLUMN);  // key:0 val:date1, key:1, date2 の形式
    
//     rsort($row);
    
//     return $row[0];
//   }

//   public function getAllDate()
//   {
//     $stmh = $this->getSchedule();
//     $row = $stmh->fetchAll(PDO::FETCH_COLUMN);  // key:0 val:date1, key:1, date2 の形式
    
//     sort($row);
    
//     return $row;
//   }

//   public function getAllFromSchedule()
//   {
//     $stmh = $this->getSchedule();
//     $row = $stmh->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);
//     // [date1 => [kanen => 0, funen =>0....], date2 => [kanen...]]
        
//     return $row;
//   }

//   public function getSchedule()
//   {
//     try {
//       $sql = "SELECT * FROM schedule";

//       $stmh = $this->pdo->query($sql);

//       return $stmh;
      
//     } catch (PDOException $Exception) {

//       print "エラー:".$Exception->getMessage();
//       return null;
//     }
    
//   }

//   public function getNextSchedule($day, $category)
//   {
//     $sql = "SELECT date FROM schedule WHERE date >= ".$day." AND ".$category." = 1";
//     print $sql."<br>";
    
//     try {

//       $stmh = $this->pdo->query($sql);

//       $row = $stmh->fetch(PDO::FETCH_ASSOC);
      
//       print_r($row);
      
//       if (empty($row))
//       {
//         return null;
//       } else {
//         return $row['date'];
//       }
      
//     } catch (PDOException $Exception) {

//       print "エラー:".$Exception->getMessage();
//       return null;
//     } 
//   }
 
//   public function getScheduleFromDayCat($day, $category)
//   {
//     try {
//       $sql = "SELECT date FROM schedule WHERE date = ".$day." AND ".$category." = 1";

//       $stmh = $this->pdo->query($sql);

//       $row = $stmh->fetch();

//       return $row[0];
      
//     } catch (PDOException $Exception) {

//       print "エラー:".$Exception->getMessage();
//       return null;
//     } 
//   }
 
//   public function getNumColumns()
//   {
//     $stmh = $this->getSchedule();
//     return $stmh->columnCount();
//   }

// //   public function getCategory()
// //   {
// //     $stmh = $this->getAllCategory();
// // //    $row = $stmh->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);
// //     $row = $stmh->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
// // //    $row = $stmh->fetchAll();
    
// //     $ret = $row[$this->id];

// // //    print "<pre>";
// // //    print_r($ret);
// // //    print "</pre><br>";
    
// //     return $ret;
// //   }

//   public function getCategoryOne($name)
//   {
//     $stmh = $this->getAllCategory();
//     $row = $stmh->fetchAll(PDO::FETCH_ASSOC);  // key:0 val:date1, key:1, date2 の形式

//     foreach ($row as $key => $cat)
//     {
//       if ($name === $cat['name']) return $cat;
//     }
//     return null;
//   }
  
//   // private function getAllCategory()
//   // {
//   //   try {
//   //     $sql = "SELECT * FROM category where id = '".$this->id."'";

//   //     $stmh = $this->pdo->query($sql);

//   //     return $stmh;
      
//   //   } catch (PDOException $Exception) {

//   //     print "エラー:".$Exception->getMessage();
//   //     return null;
//   //   }
    
//   // }

//   public function getDateSince($day)
//   {
//     try {
//       $sql = "SELECT date FROM schedule where date <= '".$day."'";

//       $stmh = $this->pdo->query($sql);
// //      $row = $stmh->fetchAll();
//     $row = $stmh->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);
    
//       return $row;
      
//     } catch (PDOException $Exception) {

//       print "エラー:".$Exception->getMessage();
//       return null;
//     }
    
//   }
  
//   private function hasDate($date)
//   {
//     $stmh = $this->getSchedule();
//     $row = $stmh->fetchAll(PDO::FETCH_COLUMN);  // key:0 val:date1, key:1, date2 の形式
    
//     return in_array($date, $row);    
//   }

//   public function deleteSchedule($date)
//   {
//     try {
//       $this->pdo->beginTransaction(); // トランザクション開始

//       // schedule から　指定日付まで削除
//       $sql = "DELETE FROM schedule WHERE date = :date";

//       $stmh = $this->pdo->prepare($sql);

//       $stmh->bindValue(':date', $date, PDO::PARAM_STR);

//       $stmh->execute();
      
//       $this->pdo->commit(); // トランザクション終了

// //      print "データを".$stmh->rowCount()."件、削除しました。";
//     } catch (PDOException $Exception) {

//       $this->pdo->rollBack(); // トランザクション失敗の場合、処理を戻す

//       print "エラー:".$Exception->getMessage();
//     }
//   }

//   public function deleteSinceToday($today)
//   {
//     try {
//       $this->pdo->beginTransaction(); // トランザクション開始

//       // schedule から　指定日付まで削除
//       $sql = "DELETE FROM schedule WHERE date < :date";

//       $stmh = $this->pdo->prepare($sql);

//       $stmh->bindValue(':date', $today, PDO::PARAM_STR);

//       $stmh->execute();
      
//       $this->pdo->commit(); // トランザクション終了

// //      print "データを".$stmh->rowCount()."件、削除しました。";
//     } catch (PDOException $Exception) {

//       $this->pdo->rollBack(); // トランザクション失敗の場合、処理を戻す

//       print "エラー:".$Exception->getMessage();
//     }
//   }

//   public function readDate()
//   {
//     try {
//       $sql = "SELECT * FROM schedule (last_name, first_name, age) values (:last_name, :first_name, :age)";

//       $stmh = $pdo->prepare($sql);

//       $stmh->bindValue(':last_name',
//                        $_POST['last_name'], PDO::PARAM_STR); // データ型：文字列
//       $stmh->bindValue(':first_name',
//                        $_POST['first_name'], PDO::PARAM_STR); // データ型：文字列
//       $stmh->bindValue(':age',
//                        $_POST['age'], PDO::PARAM_INT); // データ型：数値

//       $stmh->execute();

//       print "データを".$stmh->rowCount()."件、挿入しました。";
//     } catch (PDOException $Exception) {

//       print "エラー:".$Exception->getMessage();
//     }
//   }

// //   public function insert($name, $remind_day, $remind_time)
// //   {
// //     try {
// //       $this->pdo->beginTransaction(); // トランザクション開始

// //       // categoryにrow追加
// //       $sql = "INSERT INTO category VALUES (:id, :name, :remind_day, :remind_time)";

// //       $stmh = $this->pdo->prepare($sql);

// //       $stmh->bindValue(':id', $this->id, PDO::PARAM_STR);
// //       $stmh->bindValue(':name', $name, PDO::PARAM_STR);
// //       $stmh->bindValue(':remind_day', $remind_day, PDO::PARAM_INT);
// //       $stmh->bindValue(':remind_time', $remind_time, PDO::PARAM_STR);

// //       $stmh->execute();

// //       $this->pdo->commit(); // トランザクション終了

// //     } catch (PDOException $Exception) {

// //       $this->pdo->rollBack(); // トランザクション失敗の場合、処理を戻す

// //       print "エラー:".$Exception->getMessage();
// //     }

// //     try {
// //       $this->pdo->beginTransaction(); // トランザクション開始
// //       // scheduleのcol追加
// //       // 他の人が項目名を使用していれば、追加しない（できない）。
// //       // 追加できない場合、エラーは出さない
// //       $sql = "ALTER TABLE schedule ADD ".$name." BOOLEAN NOT NULL DEFAULT false";

// //       $stmh = $this->pdo->query($sql);
      
// //       $this->pdo->commit(); // トランザクション終了

// //     } catch (PDOException $Exception) {

// //       $this->pdo->rollBack(); // トランザクション失敗の場合、処理を戻す

// // //      print "エラー:".$Exception->getMessage();
// //     }
// //   }

//   public function insertFromLastTo1year()
//   {
//     // 当日の１年後を取得
//     $today = date("Y-m-d");
//     $day_1year_later = date("Y-m-d",strtotime("+1 year"));   /*********************** for test ****************************/

//     //　1年後があればなにもしない
//     if ($this->hasDate($day_1year_later))
//     {
//       // 1年後データあり
//     } else {
//       // 1年後データなし
//       //　最新日取得
//       $day_last = $this->getLastDay();
//       //  最新日から1年後までを追加（値はfalse）
//       $day_last_next = date("Y-m-d", strtotime("+1 day", strtotime($day_last)));
//       // INSERT
//       $this->insertFromTo($day_last_next, $day_1year_later);
//     }

//   }
  
//   // private function insertFromTo($from, $to)
//   // {
//   //   $col = $this->getNumColumns() - 1;

//   //   try {
//   //     $this->pdo->beginTransaction(); // トランザクション開始

//   //     // sql作成
//   //     $sql = "insert into schedule values (:day, :id";
//   //     for ($i = 1; $i < $col; $i++) {
//   //       $sql .= ", 0";
//   //     }
//   //     $sql .= ")";
      
//   //     $stmh = $this->pdo->prepare($sql);
  

//   //     //  日付を入れ、SQL実行→翌日を求める
//   //     $day = $from;
//   //     $ctr = 0;
//   //     do {
//   //       $stmh->bindValue("day", $day, PDO::PARAM_STR); // データ型：文字列
//   //       $stmh->bindValue("id", $this->id, PDO::PARAM_STR); // データ型：文字列

//   //       $day = date("Y-m-d",strtotime("+1 day",strtotime($day)));
//   //       $stmh->execute();

//   //       $ctr++;
//   //     } while($day <= $to);

//   //     $this->pdo->commit(); // トランザクション終了

//   //     print "データを".$ctr."件、挿入しました。<br>";
//   //   } catch (PDOException $Exception) {

//   //     $this->pdo->rollBack(); // トランザクション失敗の場合、処理を戻す

//   //     print "エラー:".$Exception->getMessage();
//   //   }
//   // }

//   public function update($name_before, $after)
//   {
//     $before = $this->getCategoryOne($name_before);

//     foreach ($after as $key => $val)
//     {
//       if (empty($val)) $after[$key] = $before[$key];
//     }
    
//     if ($before === $after) {
// //      print "same";
//     } else {
// //      print "different<br>";
//     }
    
// //    print "<pre>";
// //    print_r($before);
// //    print "</pre><br>";
// //    print "<pre>";
// //    print_r($after);
// //    print "</pre><br>";
    
//     try {
//       $this->pdo->beginTransaction(); // トランザクション開始

//       // 変更箇所が無ければ処理しない
//       if ($before != $after)
//       {
//         // categoryの値を変更
//         $sql = "UPDATE category set name = '".$after['name']."', remind_day = ".$after['remind_day'].", remind_time = '".$after['remind_time']."' where id = '".$after['id']."' AND name = '".$before['name']."'";

//         // 時間の桁数が違うのを直す
        
//         $stmh = $this->pdo->query($sql);

//         // scheduleのcol名の変更
//         if ($before['name'] != $after['name'])
//         {
//         $sql = "alter table schedule change ".$before['name']." ".$after['name']." BOOLEAN NOT NULL DEFAULT FALSE";

//         $stmh = $this->pdo->query($sql);

// //          $stmh = $this->pdo->prepare($sql);
// //
// //        $stmh->bindValue(':name_before', $before['name'], PDO::PARAM_STR);
// //        $stmh->bindValue(':name', $after['name'], PDO::PARAM_STR);
// //
// //        $stmh->execute();
//         }
//       }
      
//       $this->pdo->commit(); // トランザクション終了

// //      print "更新しました。<br>";
//     } catch (PDOException $Exception) {

//       $this->pdo->rollBack(); // トランザクション失敗の場合、処理を戻す

//       print "エラー:".$Exception->getMessage();
//     }
//   }

//   public function updateScheduleArray($arr)
//   {
//     foreach ($arr as $date => $cat_arr)
//     {
//       foreach ($cat_arr as $cat => $bool)
//       {
//         $this->updateSchedule($date, $cat, $bool);   
//       }
//     }
//   }

//   public function updateSchedule($date, $category, $bool)
//   {

// //    $sql = "UPDATE schedule set ".$category." = ".$bool." where date = ".$date;
// //    print $sql."<br>";
    
//     try {
//       $this->pdo->beginTransaction(); // トランザクション開始

//       $sql = "UPDATE schedule set ".$category." = ".$bool." where date = '".$date."'";

//       $stmh = $this->pdo->query($sql);
      
//       $this->pdo->commit(); // トランザクション終了

//       print "更新しました。<br>";
//     } catch (PDOException $Exception) {

//       $this->pdo->rollBack(); // トランザクション失敗の場合、処理を戻す

//       print "エラー:".$Exception->getMessage();
//     }
//   }

//   public function updateRemind($date, $category)
//   {

// //    $sql = "UPDATE schedule set ".$category." = ".$bool." where date = ".$date;
// //    print $sql."<br>";
    
//     try {
//       $this->pdo->beginTransaction(); // トランザクション開始

//       // 9:リマインド済
//       $sql = "UPDATE schedule set ".$category." = 9 where date = '".$date."'";

//       $stmh = $this->pdo->query($sql);
      
//       $this->pdo->commit(); // トランザクション終了

//       print "リマインド済です。<br>";
//     } catch (PDOException $Exception) {

//       $this->pdo->rollBack(); // トランザクション失敗の場合、処理を戻す

//       print "エラー:".$Exception->getMessage();
//     }
//   }

  
// //   public function delete($name)
// //   {
// //     try {
// //       $this->pdo->beginTransaction(); // トランザクション開始

// //       // categoryからrow削除
// //       $sql = "DELETE FROM category WHERE id = :id AND name = :name";

// //       $stmh = $this->pdo->prepare($sql);

// //       $stmh->bindValue(':id', $this->id, PDO::PARAM_STR);
// //       $stmh->bindValue(':name', $name, PDO::PARAM_STR);

// //       $stmh->execute();

// //       // scheduleのcol削除
// //       // 他のユーザーが同じ項目名を使って無ければ削除
// //       /*
// //       うまくできないから消さない
// //       $sql = "SELECT id FROM category WHERE name = :name";
// //       $stmh = $this->pdo->prepare($sql);
// //       $stmh->bindValue(':name', $name, PDO::PARAM_STR);
// //       $stmh->execute();

// //       print "count:".$stmh->rowCount()."<br>";
// //       print_r($stmh->fetch());
// //       print "end<br>";
      
// //       if ($stmh->rowCount() == 1)
// //       {
// //         // 削除可
// //         $sql = "ALTER TABLE schedule DROP COLUMN ".$name;
// //         $stmh = $this->pdo->query($sql);        
// //       }
// //       */
      
// //       $this->pdo->commit(); // トランザクション終了

// // //      print "データを".$stmh->rowCount()."件、削除しました。";
// //     } catch (PDOException $Exception) {

// //       $this->pdo->rollBack(); // トランザクション失敗の場合、処理を戻す

// //       print "エラー:".$Exception->getMessage();
// //     }
// //   }
  
  
//   public function close()
//   {
//     // close
//     $this->pdo = null;
//   }
  
}
?>
