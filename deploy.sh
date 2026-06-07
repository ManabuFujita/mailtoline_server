#!/bin/sh

# 本番環境の設定
export APP_ENV=prod

LOG_FILE="./logs/deploy.log"

echo "[デプロイ開始] $(date '+%Y-%m-%d %H:%M:%S')" >> $LOG_FILE

cd /home/users/0/sub.jp-xprkd134/web/xprkd134.site/mailtoline_server/
git pull git@github.com:ManabuFujita/mailtoline_server.git >> $LOG_FILE 2>&1

echo "[デプロイ終了] $(date '+%Y-%m-%d %H:%M:%S')" >> $LOG_FILE

exit
