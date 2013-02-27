#!/usr/bin/env python
import json
import os
import sys
from pymongo import MongoClient


dbcon = MongoClient()


for path in sys.argv[1:]:
    if os.path.isfile(path):
        print path
        f = open(path, 'r')
        harcontent = f.read()
        jscontent = json.loads(harcontent)
        dbcon.perfmonitor.har.insert(jscontent)
        f.close()

sys.exit(0)

