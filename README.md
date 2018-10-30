# README

A web app to crawl content especially photo assets using curl and websocket written in php, html5 and javascript.

## Websocket:

ws://host:port/path (ex. ws://localhost:8000/echo)

Server: PHP websockets https://github.com/ghedipunk/PHP-WebSockets
```
php -q bin/socket.php
```

Example usage:

http://localhost:80
Press connect socket button
Paste 'https://en.wikipedia.org/wiki/World' to textarea
Press Run

Format for input:

url|folder name|selection

https://en.wikipedia.org/wiki/World|wiki-world-images|range(1, 10)

- url is the only required portion
- folder name if you want to specify name of the folder where images will be saved to
- selection can be:
  - an array will specify pages to download & skip the rest e.g [11, 14, 15]
  - a number will set a starting page to download from & skip the prior ones e.g 11
  - range(1, 10) will tell apps to crawl image 1 to 10