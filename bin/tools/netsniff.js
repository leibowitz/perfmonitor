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
        pageTimings: {"onLoad": page.endTime - page.startTime}
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

function updatePage(page)
{
    page.endTime = new Date();
    page.title = page.evaluate(function () {
        return document.title;
    });
}

function testUrl(har, page, url, cb)
{
    page.address = url;
    page.resources = [];
    page.open(page.address, function (status) {
        if (status !== 'success') {
            console.log('FAIL to load the address');
            phantom.exit();
        } else {
            updatePage(page);
            addToHar(har, page);
            cb();
        }
    });
}

function runMain()
{
    var system = require('system');

    if (system.args.length === 1) {
        console.log('Usage: netsniff.js <some URL>');
        phantom.exit();
    }
    
    var url = system.args[1];

    var page = require('webpage').create();

    page.onLoadStarted = function () {
        page.startTime = new Date();
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

    page.onError = function () {
        // catch uncaught error from the page
    };

    if (system.args.length > 2 && system.args[2].indexOf('mobile') != -1){
        page.settings.userAgent = 'Mozilla/5.0 (iPhone; U; CPU iPhone OS 4_0 like Mac OS X; en-us) AppleWebKit/532.9 (KHTML, like Gecko) Version/4.0.5 Mobile/8A293 Safari/6531.22.7';
    }
    
    var har = createHAR();
    testUrl(har, page, url, function(){
        testUrl(har, page, url, function(){
            console.log(JSON.stringify(har, undefined, 4));
            phantom.exit();
        });
    });

}

runMain();
