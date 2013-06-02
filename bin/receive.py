#!/usr/bin/env python
import pika
from subprocess import check_output, CalledProcessError
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
    if msg['nb'] > 0:
        channel.basic_publish(exchange='perfmonitor',
              routing_key='perftest',
              body=json.dumps(msg))
        print ' [x] Message sent to queue with count ', msg['nb']

def send_ack(ch, method):
    ch.basic_ack(delivery_tag = method.delivery_tag)
    print " [x] Acknoledgment sent"

def callback(ch, method, properties, body):
    print " [x] Received %r" % (body,)
    content = json.loads(body)
     
    # if the current count reached 0
    # there are no more requests left
    if content['nb'] <= 0:
        # acknowledge the msg and quit 
        send_ack(ch, method)
        print ' [x] No more requests left'
        return

    print ' [x] Executing browser', content['url']

    try:
        harcontent = check_output(['phantomjs', NETSNIFF_UTIL, content['url'], content['agent']])
    except CalledProcessError:
        print ' [x] Sub-process failed'
        harcontent = None

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

                send_ack(ch, method)
                content['nb'] -= 1
                if content['nb'] > 0:
                    sendback(content)
            except:
                print ' [x] Unable to save HAR response, sending back'
        else:
            print ' [x] Unable to parse HAR file from sub-process, sending back'

    else:
        print ' [x] Unable to generate HAR file, sending back'

channel.basic_consume(callback,
                      queue=queue_name)

channel.start_consuming()
