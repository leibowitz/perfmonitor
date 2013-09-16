from subprocess import check_output, CalledProcessError
import os

def run_webkit_remote(url, agent):
    FNULL = open(os.devnull, 'w')

    return check_output(['chrome-har-capturer', url], stderr=FNULL)

from subprocess import check_output
import os

NETSNIFF_UTIL = os.path.join(os.path.dirname(os.path.realpath(__file__)), 'tools', 'netsniff.js')

def run_phantomjs(url, agent):
    return check_output(['phantomjs', NETSNIFF_UTIL, url, agent])


