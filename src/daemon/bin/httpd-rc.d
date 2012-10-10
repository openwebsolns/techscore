#!/bin/bash
#
# chkconfig: - 85 15
# description: Apache Server for local Techscore updates
#
# rc.d initscript for Apache. Install by symlink or direct copy into
# your system's rc.d/init.d directories.

function usage {
    echo ERROR: $1
    exit 1
}

APACHECTL=$(which apachectl 2> /dev/null || usage "apachectl not found")

NAME="local Apache REST Service"
PWD=$(readlink -f $(dirname $(dirname $0)))
[ ! -f $PWD/etc/httpd.conf ] && usage "conf file $PWD/etc/httpd.conf not found"


case "$1" in
    start)
        echo -n "Starting $NAME..."
        if $APACHECTL -f $PWD/etc/httpd.conf -k start > /dev/null; then
            echo "DONE"
        else
            echo "FAIL"
            exit 1
        fi
        ;;

    stop)
        echo -n "Stopping $NAME..."
        if $APACHECTL -f $PWD/etc/httpd.conf -k stop > /dev/null; then
            echo "DONE"
        else
            echo "FAIL"
            exit 1
        fi
        ;;

    reload)
        echo -n "Reloading $NAME..."
        if $APACHECTL -f $PWD/etc/httpd.conf -k graceful >/dev/null; then
            echo "DONE"
        else
            echo "FAIL"
            exit 1
        fi
        ;;

    restart)
        echo -n "Restarting $NAME..."
        if $APACHECTL -f $PWD/etc/httpd.conf -k restart > /dev/null; then
            echo "DONE"
        else
            echo "FAIL"
            exit 1
        fi
        ;;

    *)
        echo "usage: $0 {start|stop|reload|restart}"
esac
