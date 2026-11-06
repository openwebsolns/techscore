#!/usr/bin/env sh
set -e

S3_KEY="$(date +"%+4Y/%m/%d-%H-%M-%S").sql.bz2"

mariadb-dump -u"${SQL_USER}" -p"${SQL_PASS}" -P"${SQL_PORT}" -h"${SQL_HOST}" --single-transaction "${SQL_DB}" \
    | bzip2 \
    | aws s3 cp - s3://"${SQL_BACKUP_BUCKET}/${SQL_DB}/${S3_KEY}"
