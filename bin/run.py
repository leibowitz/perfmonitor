from browsermobproxy import Server
from browsermob import BrowserMobProxy, BrowserMobProxyHub
from selenium import webdriver
import json
import sys
import os
import time 
from urlparse import urlparse

if len(sys.argv) < 2:
    sys.exit(1)

url = sys.argv[1]

server = Server('/home/gianni/browsermob-proxy/browsermob-proxy-2.0-beta-7/bin/browsermob-proxy')

server.start()
server_url = urlparse(server.url)
hub = BrowserMobProxyHub(hostname = server_url.hostname, port = server_url.port)
proxy = hub.get_proxy(capture_headers=True, capture_content=False)

proxy.new_har()

proxy_url = "%s:%s" %(proxy.hub.hostname, proxy.port)
proxy_settings = {
    'httpProxy': proxy_url,
    'ftpProxy': proxy_url,
    'sslProxy': proxy_url,
}

se_proxy = webdriver.Proxy(proxy_settings)

driver = None

browser = 'Firefox'
timingapi = True

if browser == 'Firefox':
    driver = webdriver.Firefox(proxy=se_proxy)
elif browser == 'PhantomJS':
    driver = webdriver.PhantomJS(proxy=proxy_url) 

if driver:
    driver.get(url)
    har = proxy.get_har()

    # get onload time from web timing api, if available
    if timingapi:
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
