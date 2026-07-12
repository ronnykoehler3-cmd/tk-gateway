#!/bin/bash

sqlite3 /opt/tkgateway/database/gateway.db \
"INSERT INTO events(level,message)
 VALUES('INFO','Gateway gestartet');"
