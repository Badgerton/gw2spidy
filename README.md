GW2Spidy - Trade Market Graphs
==============================
This project aims to provide you with graphs of the sale and buy listings of items on the Guild Wars 2 Trade Market.

How does it work?
=================
ArenaNet has build the Trade Market so that it's loaded into the game from a website.
You can also access this website with a browser and use your game account to login and view all the items and listings.

Now what I've build is some tools which will run constantly to automatically login to that website and record all data we can find,
as a result I can record the sale listings for all the items about every hour and with that data I can create graphs with the price changing over time! 

Contributing
============
Everyone is very much welcome to contribute, 99% chance you're reading this on github so it shouldn't be to hard to fork and do pull requests right :) ?

If you need any help with setup of the project or using git(hub) then just contact me and I'll be glad to help you!
If you want a dump of the database, since that's a lot easier to work with, then just contact me ;)

Project setup
=============
I'll provide you with some short setup instructions to make your life easier if you want to run the code for yourself or contribute.

Environment
-----------
### Linux
I run the project on a linux server and many of the requirements might not be available on windows and I have only (a tiny bit) of (negative) experience with windows.
If you want to run this on a windows machine, for development purposes, then I strongly sugest you just run a virtual machine with linux (virtualbox is free and works pretty nice).

### PHP 5.3
You'll need PHP5.3 or higher for the namespace support etc.

### MySQL / Propel
I think 4.x will suffice, though I run 5.x.
On the PHP side of things I'm using PropelORM, thanks to that you could probally switch to PostgreSQL or MSSQL easily if you have to ;) 

### Apache / Nginx / CLI
The project will work fine with both Apache or Nginx (I actually run apache on my dev machine and nginx in production), you can find example configs in the `docs` folder of this project.
If you want to run the code that spiders through the trade market then you'll need command line access, if you just want to run the frontend code (and get a database dump from me) then you can live without ;)

### Memcache
Using memcached daemon and PHP Memcache lib to easily cache some stuff in memory (item and type data).
However, everything will work fine without memcached, if you have memcached installed but don't want the project to use it then define MEMCACHED_DISABLED in your config.inc.php and set it to true.

### Redis
The spidering code uses a custom brew queue and some custom brew system to make sure we don't do more then x amount of requests.
Both the queue and the slots are build using Redis (Predis library is already included in the `vendor` folder).
Previously I was using MySQL for this, but using MySQL was a lot heavier on load and using Redis it's also slightly faster!

### Silex / Twig / Predis
Just some PHP libs, already included in the `vendor` folder.

### jQuery / Flot / Twitter Bootstrap
Just some HTML / JS / CSS libs, already included in `webroot/assets/vendor` folder.

RequestSlots
------------
ArenaNet is okay with me doing this, but nonetheless I want to limit the amount of requests I'm shooting at their website or at least spread them out a bit.
I came up with this concept of 'request slots', I setup an x amount of slots, claim one when I do a request and then give it a cooldown before I can use it again.
That way I can control the flood a bit better.

This is done using Redis sorted sets.

WorkerQueue
-----------
All spidering work is done through the worker queue, the queue process also handles the previously mentioned request slots.

This is also done using Redis sorted sets.

Database Setup
--------------
In the `config` folder there's a `config/schema.sql` (generated by propel based of `config/schema.xml`, so database changes should be made to the XML and then generating the SQL file!).
You should create a database called 'gw2spidy' and load the `config/schema.sql` in.

The `config/runtime-conf.xml` contains the database credentials, be careful that it's not on .gitignore, so don't commit your info!!
If you do by excident, backup your code and delete your whole repo xD - I'll come up with a better way soon.

Spider Config Setup
-------------------
Copy the `config/config.inc.example.php` to `config/config.inc.php` and change the account info, this file is on .gitignore so you can't commit it by excident ;)

RequestSlots Setup
------------------
Run `tools/setup-request-slots.php` to create the initial request slots, you can also run this during development to reinitiate the slots so you can instantly use them again if they are all on cooldown.

TinyInt Setup
-------------
To get the top10 listings grouped by ItemID and listing time/date you need to do some tricky stuff in the query,
for this we need a table with a range from 1 to 255, the `tools/setup-tinyint.php` sets this up.
For more info read: http://code.openark.org/blog/mysql/sql-selecting-top-n-records-per-group

First Spider Run
----------------
The first time you'll have to run `daemons/fill-queue-daily.php` to enqueue a job which will fetch all the item (sub)types.
Then run `daemons/worker-queue.php` to execute that job.
After that is done, run  `daemons/fill-queue-daily.php` again, this will enqueue a job for each (sub)type to start fetch item information.
Then run `daemons/worker-queue.php` again until it's done (needs to fetch about 600~650 pages of items).

The Worker Queue
----------------  
When you run `daemons/worker-queue.php` the script will do 50 loops to either fetch an item from the queue to execute or if none it will sleep.
It will also sleep if there are no slots available.

I personally run 4 of these in parallel using `while [ true ]; do php daemons/worker-queue.php >> /var/log/gw2spidy/worker.1.log; echo "restart"; done;` 
Where I replace the .1 with which number of the 4 it is so I got 4 logs to tail.
4 processes is enough to handle all item listings every hour and with a VPS with 2 cores and it barely uses any load (thanks to redis!).

Fill Queue Hourly
-----------------
The `daemon/fill-queue-hourly.php` script enqueues a job for every item in the database to fetch the listings.

Fill Queue Daily
-----------------
The `daemon/fill-queue-daily.php` script enqueues a job for every (sub)type in the database to fetch the first page of items,
that job then requeues itself until all the pages are fetched.