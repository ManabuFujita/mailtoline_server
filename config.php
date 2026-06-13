<?php
class Config {
    protected static $directory;
    public static function setConfigDirectory($directory) {
        self::$directory = $directory;
    }

    public static function getConfigDirectory() {
        return rtrim(self::$directory, '/\\');
    }

    public static function get($s) {

        // 環境によってファイルを切り替える
        // 本番環境はAPP_ENVをrunCron.shに設定している（prod）
        // 開発環境は環境変数を設定してないため、devになる
        $env = getenv('APP_ENV') ?: 'dev';
        $file = $env . '.php';

        $values = preg_split('/\./', $s, -1, PREG_SPLIT_NO_EMPTY);
        $key = array_pop($values);

        $path = (!empty($values)) ? implode(DIRECTORY_SEPARATOR, $values) .
            DIRECTORY_SEPARATOR : '';
        $base_dir = self::getConfigDirectory() . DIRECTORY_SEPARATOR;
        $common = include($base_dir . $path . 'common.php');
        $config = include($base_dir . $path . $file);
        $config = array_merge($common, $config);
        return $config[$key];
    }
}