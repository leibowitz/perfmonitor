#!/usr/bin/env python
import pika
from subprocess import check_output, CalledProcessError
import json
import os
from pymongo import MongoClient
import logging
logging.basicConfig()

def create_channel(connection, exchange, queue, key, type = 'direct'):
    channel = connection.channel()
    channel.exchange_declare(exchange=exchange, type=type)

    channel.queue_declare(queue)

    channel.queue_bind(exchange=exchange, queue=queue, routing_key=key)

    return channel

dbcon = MongoClient()

NETSNIFF_UTIL = os.path.join(os.path.dirname(os.path.realpath(__file__)), 'tools', 'netsniff.js')

exchange = 'perfmonitor'

queue_read = 'perf'
key_read = 'perftest'

queue_write = 'reinject'
key_write = 'perfreinject'

queuecon = pika.BlockingConnection(pika.ConnectionParameters(
               'localhost'))
channelread = create_channel(queuecon, exchange, queue_read, key_read)
channelwrite = create_channel(queuecon, exchange, queue_write, key_write)

print ' [*] Waiting for messages. To exit press CTRL+C'

def sendback(msg):
    if msg['nb'] > 0:
        channelwrite.basic_publish(exchange=exchange,
              routing_key=key_write,
              body=json.dumps(msg))
        print ' [x] Message sent to queue with count ', msg['nb']

def send_ack(ch, method):
    ch.basic_ack(delivery_tag = method.delivery_tag)
    print " [x] Acknoledgment sent"

def send_nack(ch, method):
    ch.basic_nack(delivery_tag = method.delivery_tag)
    print " [x] Nacknoledgment sent"

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
                return
            except:
                print ' [x] Unable to save HAR response, sending back'
        else:
            print ' [x] Unable to parse HAR file from sub-process, sending back'

    else:
        print ' [x] Unable to generate HAR file, sending back'
    send_nack(ch, method)

try:
    channelread.basic_consume(callback,
                      queue=queue_read)

    channelread.start_consuming()
except pika.exceptions.ConnectionClosed, e:
    print 'Connection closed', e

except KeyboardInterrupt:
    channelread.stop_consuming()

