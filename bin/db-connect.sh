#!/usr/bin/env sh

mariadb -u"${SQL_USER}" -p"${SQL_PASS}" -P"${SQL_PORT}" -h"${SQL_HOST}" "${SQL_DB}"
