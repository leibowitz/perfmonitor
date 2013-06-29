#!/usr/bin/env python
import json
import sys
from pymongo import MongoClient
from celery import Celery
from tasks import processtest

if len(sys.argv) == 1:
    sys.exit(1)

if not sys.argv[1].isdigit():
    sys.exit(1)

minutes = int(sys.argv[1])

dbcon = MongoClient()

# 5, 10, 15, 30, 60, 180, 360, 720, 1440

# 5 = 5
# 10 = 5, 10
# 15 = 5, 15
# 20 = 5, 10
# 25 = 5
# 30 = 5, 10, 15, 30
# 35 = 5
# 40 = 5, 10
# 45 = 5, 15
# 50 = 5, 10
# 55 = 5
# 60 = 5, 10, 15, 30, 60

rows = dbcon.perfmonitor.sites.aggregate([
    {
     '$match': {'interval': minutes}
    }, 
    {'$unwind': "$urls"} 
])

if not rows['result']:
    sys.exit(0)

for row in rows['result']:
    msg = {
        'url': str(row['urls']),
        'site': str(row['site']),
        'account': 'me',
        'type': 'har',
        'nb': int(row['nb']),
        'agent': str(row['agent'])
    }

    processtest.delay(msg)


sys.exit(0)
