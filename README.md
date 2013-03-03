Performance Monitoring Tool
===========================

About
-----

Web based tool built to monitor the front-end performances of websites.

Requirements
------------

MongoDB >= 2.2
PHP >= 5.3.2 (and extensions: mongo) 
Python (and libs: pika, pymongo)
PhantomJS >= 1.5
RabbitMQ
Apache

Installation
------------

Extract the code inside any directory that can be accessed by apache (configure your vhost accordingly to point to the web/ folder)

After installing all the required libraries, make sure all the services are running (MongoDB, Apache, PHP, RabbitMQ)

Start the queue processing by running:

    python bin/receive.py

If RabbitMQ and python pika library are installed correctly, you should see this:

    [*] Waiting for messages. To exit press CTRL+C

Now to test the queue and mongoDb, you can open a new shell and run this:

    python bin/send.py

This will print and exit:

    [x] Sent 'Hello World!'

Go back to the other shell and make sure the output is:

    [x] Received '{"url": "http://www.google.co.uk", "account": "me", "type": "har", "site": "test"}'
    [x] Executing command phantomjs http://www.google.co.uk
    [x] Done

If that's the case, it means your python/RabbitMQ/MongoDB set-up is correct.

Now open your web browser and go to this url

    /app.php/perf/time

You should see one site called test. After selecting it, you should see one request to www.google.co.uk

Click on it and start learning the interface.
