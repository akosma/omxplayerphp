Driving omxplayer through PHP on a Raspberry Pi
===============================================

This script allows to start and stop the playback of a list of movies in
a Raspberry Pi computer, using lighttpd as a web server and the PHP
package. It displays a mobile-friendly user interface, so that you can
use your smartphone to control the playback.

Installation
------------

It requires the "www-data" user to be part of the "video" and "audio"
groups, as defined in "/etc/group".

The pipe "fifo" is located in the same folder as this script; it was
created using the commands:

     $ mkfifo fifo 
     $ chmod 777 fifo

This pipe is used to send commands to the running instance of omxplayer,
just as if the user was typing those commands on the command line.

