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


def callback(ch, method, properties, body):
    print " [x] Received %r" % (body,)
    content = json.loads(body)
    print ' [x] Executing command phantomjs', content['url']
    harcontent = subprocess.check_output(['phantomjs', NETSNIFF_UTIL, content['url']])
    jscontent = json.loads(harcontent)
    print jscontent
    dbcon.perfmonitor.har.insert(jscontent)

    #path = os.path.join('harfiles', content['account'], content['site'])
    #if not os.path.exists(path):
    #    os.makedirs(path)
    #f = open(os.path.join(path, 'file.har'), 'w')
    #f.write(harcontent)
    #f.close()
    ch.basic_ack(delivery_tag = method.delivery_tag)
    print " [x] Done"

channel.basic_consume(callback,
                      queue=queue_name)

channel.start_consuming()
