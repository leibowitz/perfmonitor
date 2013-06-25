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


exchange = 'perfmonitor'

# Queue to read from
key_read = 'perfreinject'
queue_read = 'reinject'

# Queue to send message back to (original queue)
key_write = 'perftest'
queue_write = 'perf'

queuecon = pika.BlockingConnection(pika.ConnectionParameters(
               'localhost'))

channelread = create_channel(queuecon, exchange, queue_read, key_read)
channelwrite = create_channel(queuecon, exchange, queue_write, key_write)

print ' [*] Waiting for messages. To exit press CTRL+C'

def callback(ch, method, properties, body):
    # send msg back to original queue
    channelwrite.basic_publish(exchange=exchange,
          routing_key=key_write,
          body=body)
    # acknowledge the msg  
    ch.basic_ack(delivery_tag = method.delivery_tag)
    print " [x] Message acknoledgment and sent back"

try:
    channelread.basic_consume(callback,
                      queue=queue_read)

    channelread.start_consuming()
except pika.exceptions.ConnectionClosed, e:
    print 'Connection closed', e

except KeyboardInterrupt:
    channelread.stop_consuming()

