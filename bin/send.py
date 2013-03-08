#!/usr/bin/env python
import pika
import json

msg = {
    'url':'http://www.google.co.uk',
    'site': 'gtk',
    'account': 'me',
    'type': 'har',
    'nb': 1,
    'user-agent': 'default',
}
connection = pika.BlockingConnection(pika.ConnectionParameters(
               'localhost'))
channel = connection.channel()
channel.exchange_declare(exchange='perfmonitor', type='direct')
channel.queue_declare(queue='perf')
channel.basic_publish(exchange='perfmonitor',
                      routing_key='perftest',
                      body=json.dumps(msg))
print " [x] Sent 'Hello World!'"
connection.close()
