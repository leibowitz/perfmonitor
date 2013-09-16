from celery import Celery
from subprocess import CalledProcessError
import json
from pymongo import MongoClient
from dbinsert import insertToMongo
import run_tests

celery = Celery('tasks')
celery.config_from_object('celeryconfig')


@celery.task
def processtest(content):
    try:
        #harcontent = run_tests.run_webkit_remote(content['url'], content['agent'])
        harcontent = run_tests.run_phantomjs(content['url'], content['agent'])
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

    try:
        insertToMongo(jscontent)
        print ' [x] HAR response saved'

        content['nb'] -= 1
        if content['nb'] > 0:
            print ' [x] More tests to do, sending back msg to queue'
            processtest.delay(content)
        return True
    except:
        print ' [x] Unable to save HAR response, sending back'
        return False

@celery.task
def processcron(minutes):
    print 'Running cron of tasks for every %d minutes' % (minutes)
    dbcon = MongoClient()
    rows = dbcon.perfmonitor.sites.aggregate([
        {
         '$match': {'interval': minutes}
        }, 
        {'$unwind': "$urls"} 
    ])

    if not rows['result']:
        print 'No tasks found to run every %d minutes' % (minutes)
        return False

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
    
    print 'Done running tasks for every %d minutes' % (minutes)
    return True
