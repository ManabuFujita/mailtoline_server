<?php

require_once(__DIR__ . '/../config.php');
Config::setConfigDirectory(__DIR__ . '/../config');

abstract class Database
{
  private string $db_user;
  private string $db_pass;

  private string $db_host;
  private string $db_name;
  private string $db_char;
  private string $db_type;

  protected PDO $pdo;

  function __construct()
  {
    $this->db_user = Config::get('db_user');
    $this->db_pass = Config::get('db_pass');

    $this->db_host = Config::get('db_host');
    $this->db_name = Config::get('db_name');
    $this->db_char = Config::get('db_char');
    $this->db_type = Config::get('db_type'); // MySQL

    $this->connect();
  }

  public function connect(): PDO
  {
    $dsn = "$this->db_type:host=$this->db_host;dbname=$this->db_name;charset=$this->db_char";

    // connect
    try {
      $this->pdo = new PDO($dsn, $this->db_user, $this->db_pass);

      // default setting
      $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
      // print '接続しました...<br>';

    } catch(PDOException $Exception)
    {
      die('エラー:'.$Exception->getMessage());
    }
    return $this->pdo;
  }
}
