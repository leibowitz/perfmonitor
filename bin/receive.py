#!/usr/bin/env python
import pika
import subprocess
import json
import os
from pymongo import MongoClient


dbcon = MongoClient()

NETSNIFF_UTIL = os.path.join(os.path.dirname(os.path.realpath(__file__)), 'tools', 'netsniff.js')

queuecon = pika.BlockingConnection(pika.ConnectionParameters(
               'localhost'))
channel = queuecon.channel()
channel.exchange_declare(exchange='perfmonitor', type='direct')

result = channel.queue_declare('perf')
queue_name = result.method.queue

channel.queue_bind(exchange='perfmonitor', queue=queue_name, routing_key='perftest')

print ' [*] Waiting for messages. To exit press CTRL+C'

def sendback(msg):
    channel.basic_publish(exchange='perfmonitor',
          routing_key='perftest',
          body=json.dumps(msg))

def callback(ch, method, properties, body):
    print " [x] Received %r" % (body,)
    content = json.loads(body)
     
    content['nb'] -= 1

    if content['nb'] > 0:
        sendback(content)
        print ' [x] Message sent back to queue'

    print ' [x] Executing command phantomjs', content['url']

    harcontent = subprocess.check_output(['phantomjs', NETSNIFF_UTIL, content['url'], content['agent']])

    if harcontent:
        try:
            jscontent = json.loads(harcontent)
        except:
            print ' [x] Unable to parse JSON output'
            jscontent = None

        if jscontent:
            jscontent['site'] = content['site']
            jscontent['agent'] = content['agent']

            try:
                dbcon.perfmonitor.har.insert(jscontent)
                print ' [x] HAR response saved'
            except:
                print ' [x] Unable to save HAR response, sending one request to queue'
                content['nb'] = 1
                sendback(content)

    ch.basic_ack(delivery_tag = method.delivery_tag)
    print " [x] Acknoledgment sent"

channel.basic_consume(callback,
                      queue=queue_name)

channel.start_consuming()
