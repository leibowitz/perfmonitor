from browsermobproxy import Server
from browsermob import BrowserMobProxy, BrowserMobProxyHub
from selenium import webdriver
import json
import sys
import os
import time 
from urlparse import urlparse
from distutils.spawn import find_executable

server = Server(find_executable('browsermob-proxy'))
server.start()
url = urlparse(server.url)
hub = BrowserMobProxyHub(hostname = url.hostname, port = url.port)
proxy = hub.get_proxy(capture_headers=True, capture_content=False)

proxy.new_har("google")

proxy_url = "http://%s:%s" %(proxy.hub.hostname, proxy.port)
se_proxy = webdriver.Proxy({"httpProxy": proxy_url})

driver = None

browser = 'Firefox'
if browser == 'Firefox':
    profile = webdriver.FirefoxProfile()
    profile.set_proxy(se_proxy)
    driver = webdriver.Firefox(firefox_profile=profile)

if driver:
    driver.get("http://www.google.co.uk")
    har = proxy.get_har()

    # get onload time from web timing api, if available
    timing = driver.execute_script("""
    var t = window.performance.timing;
    return t.loadEventStart-t.navigationStart;
    """)
    if type(timing) == type(1) and timing: 
        har['log']['pages'][0]['pageTimings']['onLoad'] = timing

    print json.dumps(har, indent=4)

    driver.quit()

proxy.close_proxy()
server.stop()
sys.exit(0)
