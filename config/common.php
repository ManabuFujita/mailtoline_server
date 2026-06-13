<?php
return [
    // バッチ設定
    'batch_log_run' => __DIR__ . '/../logs/batch_run.log',
    'batch_log_error' => __DIR__ . '/../logs/batch_error.log',
    'batch_state_file' => __DIR__ . '/../logs/batch_state.txt',

    // バッチ実行対象の時刻（この時刻を過ぎてから最初の1回だけ実行する）
    // ※日付が変わる直前（23:59など）を指定すると、日付をまたぐタイミングで
    //   実行されない場合があるため避けること
    'batch_run_times' => ['08:00', '12:00', '16:00', '20:00'],
];
