Performance Monitoring Tool
===========================

About
-----

Web based tool built to monitor the front-end performances of websites.

Requirements
------------

- MongoDB >= 2.2
- PHP >= 5.3.2 (composer, less and mongo PHP module)
- Python (and libs: pika, pymongo, celery)
- PhantomJS >= 1.5
- RabbitMQ
- Webserver (apache, nginx)

Installation
------------

After installing all the dependencies above, make sure all the services are running (MongoDB, Apache/Nginx, PHP, RabbitMQ)


To install python dependencies, use 

    pip install pika pymongo celery

Clone the repo or get the zip file

    git clone https://github.com/leibowitz/perfmonitor.git
    cd perfmonitor

Install composer if you don't have it

    curl -sS https://getcomposer.org/installer | php

Install all PHP dependencies using composer

    php composer.phar install

Install less for generating CSS

    npm install less

Generate assets

    php app/console assetic:dump --env=prod

If you have errors about less module not found in npm, try to install the module globally using the -g option (sudo npm install less -g)

Configure your apache or nginx to point to the perfmonitor/web folder. If you need more help please refer to the symfony2 documentation.

Here is an example for apache

    <VirtualHost *:80>
        ServerAdmin webmaster@localhost

        ServerName perfmonitor

        ErrorLog ${APACHE_LOG_DIR}/perfmonitor_error.log

        # Possible values include: debug, info, notice, warn, error, crit,
        # alert, emerg.
        LogLevel warn

        CustomLog ${APACHE_LOG_DIR}/perfmonitor_access.log combined

        # Change this path to where you've downloaded the code
        DocumentRoot /home/your-directory/perfmonitor/web
        <Directory "/">
            Options Indexes MultiViews FollowSymLinks
            AllowOverride All
            Order allow,deny
            Allow from all
        </Directory>
    </VirtualHost>

Start the queue processing by running the task.py celery worker from the bin directory:

    cd ./bin; celery -A tasks worker --loglevel=info

If RabbitMQ and python pika library are installed correctly, you should see this:

     -------------- celery@user-MACHINE v3.0.20 (Chiastic Slide)
    ---- **** ----- 
    --- * ***  * -- Linux-3.5.0-34-generic-x86_64-with-Ubuntu-12.10-quantal
    -- * - **** --- 
    - ** ---------- [config]
    - ** ---------- .> broker:      amqp://guest@localhost:5672//
    - ** ---------- .> app:         tasks:0x25697d0
    - ** ---------- .> concurrency: 2 (processes)
    - *** --- * --- .> events:      OFF (enable -E to monitor this worker)
    -- ******* ---- 
    --- ***** ----- [queues]
     -------------- .> celery:      exchange:celery(direct) binding:celery
                    
    
    [Tasks]
      . tasks.processcron
      . tasks.processtest

    [2013-09-10 21:47:50,608: WARNING/MainProcess] celery@user-MACHINE ready.

Now to test everything, open your webbrowser and go to your local site and generate a new request (remember you need to configure it first)

    http://localhost/app_dev.php/perf/send

Go back to the other shell and make sure the output looks like this:

    [2013-09-10 21:54:07,387: INFO/MainProcess] Got task from broker: tasks.processtest[php_52376fef4f4177.69911429]
    [2013-09-10 21:54:12,416: WARNING/PoolWorker-1] [x] HAR response saved
    [2013-09-10 21:54:12,467: INFO/MainProcess] Task tasks.processtest[php_52376fef4f4177.69911429] succeeded in 5.04600286484s: True

The first line appears only if the message has been received by celery from the RabbitMQ queue.
The second message means the request has been processed using phantomJS and has been saved to the MongoDB backend.

If you see both, it means everything is set-up correctly on your machine. If you struggle getting everything configured seek help on the mailing list or use the Vagrantfile provided to get everything setup for you.

Now open your web browser and go check the results

    http://localhost/app_dev.php/perf/index

You should see one result. Click on the url and you will see the waterfall.

Use also the Histogram and the Monitoring tabs to see the histogram and box pot respectively.

Use the manage sites tab at the top right to add scheduler to monitor websites.

If you see any bugs please create issues in github. 

