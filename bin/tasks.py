from celery import Celery
from subprocess import check_output, CalledProcessError
import os
import json
from pymongo import MongoClient

NETSNIFF_UTIL = os.path.join(os.path.dirname(os.path.realpath(__file__)), 'tools', 'netsniff.js')

celery = Celery('tasks', backend='amqp', broker='amqp://guest:guest@localhost:5672//')

celery.conf.update(
    CELERY_TASK_SERIALIZER='json',
    CELERY_RESULT_SERIALIZER='json',
    CELERY_TASK_RESULT_EXPIRES = None
)


@celery.task
def processtest(content):
    try:
        harcontent = check_output(['phantomjs', NETSNIFF_UTIL, content['url'], content['agent']])
    except CalledProcessError:
        print ' [x] Sub-process failed'
        return False

    try:
        jscontent = json.loads(harcontent)
    except:
        print ' [x] Unable to parse JSON output'
        return False

    jscontent['site'] = content['site']
    jscontent['agent'] = content['agent']

    dbcon = MongoClient()
    try:
        dbcon.perfmonitor.har.insert(jscontent)
        print ' [x] HAR response saved'

        content['nb'] -= 1
        if content['nb'] > 0:
            print ' [x] More tests to do, sending back msg to queue'
            processtest.delay(content)
        return True
    except:
        print ' [x] Unable to save HAR response, sending back'
        return False