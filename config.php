<?php
class Config {
    protected static $directory;
    public static function setConfigDirectory($directory) {
        self::$directory = $directory;
    }

    public static function getConfigDirectory() {
        return rtrim(self::$directory, '/\\');
    }

    // config/dev.phpが存在する場合は開発環境、存在しない場合は本番環境とする
    public static function isProd() {
        return !file_exists(self::getConfigDirectory() . DIRECTORY_SEPARATOR . 'dev.php');
    }

    public static function get($s) {

        $values = preg_split('/\./', $s, -1, PREG_SPLIT_NO_EMPTY);
        $key = array_pop($values);

        $path = (!empty($values)) ? implode(DIRECTORY_SEPARATOR, $values) .
            DIRECTORY_SEPARATOR : '';
        $base_dir = self::getConfigDirectory() . DIRECTORY_SEPARATOR;

        // 環境によってファイルを切り替える
        $file = (self::isProd() ? 'prod' : 'dev') . '.php';

        $common = include($base_dir . $path . 'common.php');
        $config = include($base_dir . $path . $file);
        $config = array_merge($common, $config);
        return $config[$key];
    }
}