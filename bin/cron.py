#!/usr/bin/env python
import pika
import json
import sys
from pymongo import MongoClient

def send_msg(msg):
    print msg
    channel.basic_publish(exchange='perfmonitor',
                      routing_key='perftest',
                      body=json.dumps(msg))
                      
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

connection = pika.BlockingConnection(pika.ConnectionParameters(
               'localhost'))
channel = connection.channel()
channel.exchange_declare(exchange='perfmonitor', type='direct')
channel.queue_declare(queue='perf')

for row in rows['result']:

    msg = {
        'url': str(row['urls']),
        'site': str(row['site']),
        'account': 'me',
        'type': 'har',
        'nb': 1,
        'user-agent': 'default'
    }

    send_msg(msg)


connection.close()
sys.exit(0)
