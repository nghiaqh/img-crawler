var pages = [
];

var socket;
var currentPage;
var parsedPages = {
	'pages': []
};
var currentImgIndex;

/**
 * Init Web Socket connection
 */
function init(host) {
	try {
		socket = new WebSocket(host);

		socket.onopen = function(msg) {
			log('Welcome - status ' + this.readyState);
		};

		socket.onmessage = function(msg) {
			render(msg.data);
		};

		socket.onclose = function(msg) {
			log('Disconnected - status ' + this.readyState);
		};
	} catch(ex){
		log(ex);
	}
}

/**
 * Send message to socket server
 * @param  {String} msg [description]
 * @return
 */
function send(msg) {
	if(!msg) {
		alert('Message can not be empty');
		return;
	}

	try {
		socket.send(msg);
		log('Sent: ' + msg);
	} catch(ex) {
		log(ex);
	}
}

/**
 * Disconnect to socket server
 * @return {[type]} [description]
 */
function quit() {
	if (socket != null) {
		log('Goodbye!');
		socket.close();
		socket = null;
	}
}

/**
 * Reconnect to socket server
 */
function reconnect(host) {
	quit();
	init(host);
}

/**
 * Receive data from socket server and print to client
 * @param  {String} msg [description]
 * @return
 */
function log(msg) {
	$('#socket-msg').append(msg + '<br>');
}

/**
 * Render progress and status
 * @param  {String} msg [description]
 * @return {[type]}     [description]
 */
function render(msg) {
	var data = JSON.parse(msg);

	// Grab the inline template
	var tplOne = $('#template-crawling').html();
	var tplTwo = $('#template-complete').html();
	var rendered;

	// Parse it (optional, only necessary if template is to be used again)
	Mustache.parse(tplOne);
	Mustache.parse(tplTwo);

	if (typeof data.type !== 'undefined') {
		// Render a page object parsing process in progress
		if (data.type === 'page') {
			currentPage = data;

			// preprocess images array
			data.images.forEach(function(value, index) {
				currentPage.images[index] = {
					url: value,
					id: index,
					progress: 0
				};
			});

			rendered = Mustache.render(tplOne, currentPage);

			// Overwrite the contents of #running with the rendered HTML
			$('#running').html(rendered);
		}

		// Render an image progress
		if (data.type === 'image') {
			currentPage.progress = (parseInt(data.id) + 1) * 100 / currentPage.images.length;
			currentPage.images[data.id].status = data.status;
			currentImgIndex = data.id;

			rendered = Mustache.render(tplOne, currentPage);
			$('#running').html(rendered);
		}

		// Update image progress bar and progress, completed sections once a page completes
		if (data.type === 'image progress') {
			currentPage.images[currentImgIndex].progress = data.progress;
			var selector = '#img-' + currentImgIndex + ' .progress-bar';
			$(selector).attr('aria-valuenow', data.progress);
			$(selector).css('width', data.progress);

			if ((currentImgIndex + 1 === currentPage.images.length) && data.progress === 100) {
				currentPage['time'] = Date();
				if (parsedPages.pages.indexOf(currentPage) === -1) {
					parsedPages.pages.push(currentPage);
				}

				rendered = Mustache.render(tplTwo, parsedPages);
				$('#completed').html(rendered);
				$('#running').html('');
			}
		}

		if (data.type === 'error') {
			log('Error: ' + data.message);
		}

    if (data.type === 'curl error') {
      parsedPages.pages.push({
        url: data.url,
        title: currentPage.title + ' / ' + data.url,
        time: data.error,
      });
      rendered = Mustache.render(tplTwo, parsedPages);
      $('#completed').html(rendered);
    }
	}
}

/**
 * Start binding events and init socket connection
 */
$(document).ready(function() {
	if (pages.length > 0) {
		$('#form textarea').text(pages.toString().replace(/,/g, ',\n'));
	}

	var port = $('#form #port-number').val() ? $('#form #port-number').val() : '9001';
	var host = 'ws://127.0.0.1:' + port + '/';
	// init(host);

	$('#form').on('submit', function(event) {
		event.preventDefault();
		send($('#form textarea')[0].value);
	});

	$('#reconnect-socket').click(function(event) {
		event.preventDefault();
		port = $('#form #port-number').val() ? $('#form #port-number').val() : '9001';
		host = 'ws://127.0.0.1:' + port + '/';
		init(host);
		reconnect(host);
	});
});
