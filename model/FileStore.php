<?php

class FileStore
{
    private string $filename;

    /**
     * @param string $filename 操作対象のファイルパス
     */
    function __construct(string $filename)
    {
        $this->filename = $filename;
    }


    /**
     * 操作対象のファイルパスを取得する
     */
    public function getFileName(): string
    {
        return $this->filename;
    }

    /**
     * ファイルの内容を読み込む
     *
     * @return string|null 読み込めた場合はファイルの内容、読み込めない場合はnull
     */
    public function getFile(): string|false|null
    {
      if (is_readable($this->filename))
      {
        return file_get_contents($this->filename);
      }else{
//        print "'$this->filename'"."は読み込めません。";
        return null;
      }
    }

    /**
     * ファイルの内容を1行ごとの配列として読み込む
     *
     * @return array|null 読み込めた場合は行ごとの配列、読み込めない場合はnull
     */
    public function getFileArray(): array|false|null
    {
      if (is_readable($this->filename))
      {
        return file($this->filename);
      }else{
//        print "'$this->filename'"."は読み込めません。";
        return null;
      }
    }

    /**
     * ファイルの内容を上書きする
     */
    public function writeFileOverWrite(string $message): void
    {
      file_put_contents($this->filename,$message."\n", LOCK_EX);
      // FILE_APPEND --- 追記モード
      // LOCK_EX --- 排他制御
    }

    /**
     * ファイルの末尾に追記する
     */
    public function writeFileAdd(string $message): void
    {
      file_put_contents($this->filename,$message."\n", FILE_APPEND | LOCK_EX);
      // FILE_APPEND --- 追記モード
      // LOCK_EX --- 排他制御
    }

}
?>
