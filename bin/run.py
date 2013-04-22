from browsermobproxy import Server
from selenium import webdriver
import json
import sys
import os
import time 
from distutils.spawn import find_executable

server = Server(find_executable('browsermob-proxy'))
server.start()
# server._is_listening()
proxy = server.create_proxy()
driver = None

browser = 'Firefox'
if browser == 'Firefox':
    profile = webdriver.FirefoxProfile()
    profile.set_proxy(proxy.selenium_proxy())
    driver = webdriver.Firefox(firefox_profile=profile)

if driver is not None:
    proxy.new_har("google")
    driver.get("http://www.google.co.uk")
    har = proxy.har() 

    # get onload time from web timing api, if available
    timing = driver.execute_script("""
    var t = window.performance.timing;
    return t.loadEventStart-t.navigationStart;
    """)
    if type(timing) == type(1) and timing: 
        har['log']['pages'][0]['pageTimings']['onLoad'] = timing

    print json.dumps(har, indent=4)
    driver.quit()

proxy.close()
server.stop()
sys.exit(0)
