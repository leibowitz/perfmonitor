var OPT_DEBUG = false;
// Maximum time to wait for a resource
var MAX_TIMEOUT = 5000; // 5s
// Maximum total time to wait
var MAX_WAIT = 60000; // 60s

function debug(msg)
{
    if(OPT_DEBUG)
    {
        console.log(msg);
    }
}

if (!Date.prototype.toISOString) {
    Date.prototype.toISOString = function () {
        function pad(n) { return n < 10 ? '0' + n : n; }
        function ms(n) { return n < 10 ? '00'+ n : n < 100 ? '0' + n : n }
        return this.getFullYear() + '-' +
            pad(this.getMonth() + 1) + '-' +
            pad(this.getDate()) + 'T' +
            pad(this.getHours()) + ':' +
            pad(this.getMinutes()) + ':' +
            pad(this.getSeconds()) + '.' +
            ms(this.getMilliseconds()) + 'Z';
    }
}

function randomString(length)
{
	chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890";
	var pass = "";
	for(var x=0;x<length;x++)
	{
		var i = Math.floor(Math.random() * 62);
		pass += chars.charAt(i);
	}
	return pass;
}

function addToHar(har, page)
{
    var address = page.address, title = page.title, startTime = page.startTime, resources = page.resources;
    var entries = [];
    
    var id = 'page_'+randomString(6);

    resources.forEach(function (resource) {
        var request = resource.request,
            startReply = resource.startReply,
            endReply = resource.endReply;

        if (!request || !startReply || !endReply) {
            return;
        }
        
        // Exclude data:uri included images
        if (request.url.match(/(^data:image\/.*)/i)) {
            return;
        }

        har.log.entries.push({
            startedDateTime: request.time.toISOString(),
            time: endReply.time - request.time,
            request: {
                method: request.method,
                url: request.url,
                httpVersion: "HTTP/1.1",
                cookies: [],
                headers: request.headers,
                queryString: [],
                headersSize: -1,
                bodySize: -1
            },
            response: {
                status: endReply.status,
                statusText: endReply.statusText,
                httpVersion: "HTTP/1.1",
                cookies: [],
                headers: endReply.headers,
                redirectURL: "",
                headersSize: -1,
                bodySize: startReply.bodySize,
                content: {
                    size: startReply.bodySize,
                    mimeType: endReply.contentType
                }
            },
            cache: {},
            timings: {
                blocked: 0,
                dns: -1,
                connect: -1,
                send: 0,
                wait: startReply.time - request.time,
                receive: endReply.time - startReply.time,
                ssl: -1
            },
			pageref: id 
        });
    });

    har.log.pages.push({
        startedDateTime: startTime.toISOString(),
        id: id,
        title: title,
        pageTimings: {"onLoad": page.windowOnLoadTime, "onContentLoad": page.onDOMReadyTime}
    });

}

function createHAR()
{

    return {
        log: {
            version: '1.2',
            creator: {
                name: "PhantomJS",
                version: phantom.version.major + '.' + phantom.version.minor +
                    '.' + phantom.version.patch
            },
            pages: [],
            entries: []
        }
    };
}

function getTimeInSeconds(page)
{
    return page.evaluate(function(){
        return Date.now();
    });
}

function updatePage(page)
{
    page.endTime = new Date();
    page.title = page.evaluate(function () {
        return document.title;
    });

    page.onDOMReadyTime = page.phantomjs_timingDOMContentLoaded - page.phantomjs_timingLoadStarted;

    page.windowOnLoadTime = page.phantomjs_timingOnLoad - page.phantomjs_timingLoadStarted;

}

function waitTillFinished(har, page, cb)
{
    var now = getTimeInSeconds(page);
    var elapsed = now - page.startTime.getTime();
    debug('waitTillFinished [' + elapsed + ']');
    if(elapsed > MAX_WAIT)
    {
        debug('waited too long, exiting');
        phantom.exit();
    }
    if(page.phantomjs_timingOnLoad)
    {
        updatePage(page);
        addToHar(har, page);
        cb();
    }
    else {
        setTimeout(function(){
            waitTillFinished(har, page, cb);
        }, 500);
    }
}

function testUrl(har, page, url, cb)
{
    page.address = url;
    page.resources = [];
    debug('opening url ['+url+']');
    page.onLoadFinished = function (status) {
        debug('onLoadFinished status ['+status+']');
        if (status !== 'success') {
            console.log('FAIL to load the address');
            phantom.exit();
        } else {
            debug('setting timeout for callback function');
            waitTillFinished(har, page, cb);
        }
    };
    page.open(page.address);
}
const PHANTOM_FUNCTION_PREFIX = '/* PHANTOM_FUNCTION */';
function runMain()
{
    var system = require('system');

    if (system.args.length === 1) {
        console.log('Usage: netsniff.js <some URL>');
        phantom.exit();
    }
    
    var url = system.args[1];

    var page = require('webpage').create();
page.onInitialized = function() {
    debug('onInitialized at ['+getTimeInSeconds(page)+']');
    page.evaluate(function(onLoadMsg) {
        window.addEventListener("load", function() {
            console.log(onLoadMsg);
        }, false);
    }, PHANTOM_FUNCTION_PREFIX + page.onLoad);
    page.evaluate(function(domContentLoadedMsg) {
        document.addEventListener('DOMContentLoaded', function() {
            console.log(domContentLoadedMsg);
        }, false);
    }, PHANTOM_FUNCTION_PREFIX + page.onDOMContentLoaded);
};
page.onDOMContentLoaded = function() {
    page.phantomjs_timingDOMContentLoaded = getTimeInSeconds(page);

    debug('onDOMContentLoaded at ['+getTimeInSeconds(page)+'] time ['+page.phantomjs_timingDOMContentLoaded+']');
};
page.onLoad = function() {
    page.phantomjs_timingOnLoad = getTimeInSeconds(page);
    
    debug('onLoad at ['+getTimeInSeconds(page)+'] time ['+page.phantomjs_timingOnLoad+']');
};

    page.onNavigationRequested = function(url, type, willNavigate, main){
        debug('onNavigationRequested ['+url+']');
    };
    page.onLoadStarted = function () {
        debug('onLoadStarted at ['+getTimeInSeconds(page)+']');
        if(!page.startTime)
        {
            debug('setting StartTime');
            page.startTime = new Date();
            page.phantomjs_timingLoadStarted = getTimeInSeconds(page);
        }

    };

    page.onResourceRequested = function (req) {
        page.resources[req.id] = {
            request: req,
            startReply: null,
            endReply: null
        };
    };

    page.onResourceReceived = function (res) {
        if (res.stage === 'start') {
            page.resources[res.id].startReply = res;
        }
        if (res.stage === 'end') {
            page.resources[res.id].endReply = res;
        }
    };
    
    page.onResourceTimeout = function (req) {
        debug('timeout: ' + req.url);
    };

    page.onError = function (msg, trace) {
        // catch uncaught error from the page
        debug('Error ' + msg);
    };

    page.onConsoleMessage = function (msg) {
if (msg.indexOf(PHANTOM_FUNCTION_PREFIX) === 0) {
        eval('(' + msg + ')()');
            } else {
        //console.log('Message ' + msg);
        }
    };

    if (system.args.length > 2 && system.args[2].indexOf('mobile') != -1){
        page.settings.userAgent = 'Mozilla/5.0 (iPhone; U; CPU iPhone OS 4_0 like Mac OS X; en-us) AppleWebKit/532.9 (KHTML, like Gecko) Version/4.0.5 Mobile/8A293 Safari/6531.22.7';
    }
   
    page.settings.resourceTimeout = MAX_TIMEOUT;

    var har = createHAR();
    testUrl(har, page, url, function(){
        //testUrl(har, page, url, function(){
            console.log(JSON.stringify(har, undefined, 4));
            phantom.exit();
        //});
    });

}

runMain();
