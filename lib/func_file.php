<?php

class FuncFile
{
    private $filename;

    function __construct($filename)
    {
        $this->filename = $filename;
    }


    public function getFileName()
    {
        return $this->filename;
    }

    /*
    * ファイル読み込み
    * args : $filename
    * return : filedata(success)
    *          false(failed)
    */
    public function getFile()
    {
      if (is_readable($this->filename))
      {
        return file_get_contents($this->filename);
      }else{
//        print "'$this->filename'"."は読み込めません。";
        return null;
      }
    }

    /*
    * ファイル読み込み
    * args : $filename
    * return : fileArray(success)
    *          false(failed)
    */
    public function getFileArray()
    {
      if (is_readable($this->filename))
      {
        return file($this->filename);
      }else{
//        print "'$this->filename'"."は読み込めません。";
        return null;
      }
    }

    /*
    * ファイル書き込み
    * args : filename, messages
    * return : none
    */
    public function writeFileOverWrite($message)
    {
      file_put_contents($this->filename,$message."\n", LOCK_EX);
      // FILE_APPEND --- 追記モード
      // LOCK_EX --- 排他制御
    }

    public function writeFileAdd($message)
    {
      file_put_contents($this->filename,$message."\n", FILE_APPEND | LOCK_EX);
      // FILE_APPEND --- 追記モード
      // LOCK_EX --- 排他制御
    }

}
?>
