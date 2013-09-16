#!/usr/bin/env python
import json
import os
import sys
from pymongo import MongoClient
from dbinsert import insertToMongo


for path in sys.argv[1:]:
    if os.path.isfile(path):
        print path
        f = open(path, 'r')
        harcontent = f.read()
        jscontent = json.loads(harcontent)
            
        insertToMongo(jscontent)
        f.close()

sys.exit(0)

