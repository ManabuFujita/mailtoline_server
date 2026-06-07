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
        $env = getenv('APP_ENV') ?: 'dev';
        $file = 'common_' . $env . '.php';

        $values = preg_split('/\./', $s, -1, PREG_SPLIT_NO_EMPTY);
        $key = array_pop($values);

        $path = (!empty($values)) ? implode(DIRECTORY_SEPARATOR, $values) .
            DIRECTORY_SEPARATOR : '';
        $base_dir = self::getConfigDirectory() . DIRECTORY_SEPARATOR;
        $config = include($base_dir . $path . $file);
        return $config[$key];
    }
}