#!/bin/bash -e
#
# Socket Server for generating PDF for findaidforms.fiu.edu
# 
# chkconfig: 345 90 10
# description: Python based socket server for pdflatex
#
# Daemon for starting and stopping the pdflatex_server for the
# finaidforms project
#
# @author Dayan Paez
# @version 2011-05-04

function usage {
    echo -e $1
    echo
    echo "usage: $0 start|stop|restart"
    exit 1
}

[ $# -ne 1 ] && usage "exactly one argument must be provided"

BASE=$(dirname "$(readlink -f $0)")
PID_PATH="$BASE/pdflatex_server.pid"

function start {
    echo -n "Starting... "
    python2 "$BASE/pdflatex_server.py" &
    PID=$!
    if [ $? ]; then
	      echo $PID > "$PID_PATH"
	      echo "OK"
    else
	      usage "FAIL\n\nProcess died after startup (PID=$PID)"
    fi
}

function stop {
    echo -n "Stopping... "
    [ -e "$PID_PATH" ] || usage "No PID file found in $PID_PATH"
    if kill -1 $(cat "$PID_PATH"); then
	      rm -f "$PID_PATH"
	      echo "OK"
    else
	      usage "FAIL\n\nUnable to kill process $(cat "$PID_PATH")"
    fi
}

case "$1" in
    start)
	      start
	      ;;
    stop)
	      stop
	      ;;
    restart)
	      stop
	      sleep 2
	      start
        ;;
    *)
	      usage "Unknown command $1"
esac
