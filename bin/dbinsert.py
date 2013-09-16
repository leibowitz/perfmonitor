from pymongo import MongoClient

def insertToMongo(jscontent):
    dbcon = MongoClient()

    if 'site' not in jscontent:
        # create a default site if not specified
        jscontent['site'] = 'default'
    if 'agent' not in jscontent:
        # assume this is a desktop generated har file
        jscontent['agent'] = 'desktop'
    
    content_id = dbcon.perfmonitor.har.insert(jscontent)

    tinydoc = {
        'startedDateTime': jscontent['log']['pages'][0]['startedDateTime'],
        'pageTimings': jscontent['log']['pages'][0]['pageTimings'],
        'url': jscontent['log']['entries'][0]['request']['url'],
        'site': jscontent['site'],
        'agent': jscontent['agent'],
        '_id': content_id
    }

    dbcon.perfmonitor.timings.insert(tinydoc)

