#!/bin/bash

# Get pending requests
echo 'select * from pub_update_request where id not in (select request from pub_update_log)' | mysql -u root ts2
