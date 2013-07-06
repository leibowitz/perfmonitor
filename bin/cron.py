#!/usr/bin/env python
from pymongo import MongoClient
from celery import Celery
from tasks import processtest

celery = Celery('cron')
celery.config_from_object('celeryconfig')

@celery.task
def process(minutes):
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
