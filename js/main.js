var pages = [
];

var socket;
var currentPage;
var currentImgIndex;

function init() {
	var host = 'ws://127.0.0.1:9001/';

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

function quit() {
	if (socket != null) {
		log('Goodbye!');
		socket.close();
		socket = null;
	}
}

function reconnect() {
	quit();
	init();
}

// Utilities
function log(msg) {
	$('#socket-msg').append(msg + '<br>');
}

function render(msg) {
	var data = JSON.parse(msg);

	// Grab the inline template
	var tplOne = $('#template-crawling').html();
	var tplTwo = $('#template-complete').html();

	// Parse it (optional, only necessary if template is to be used again)
	Mustache.parse(tplOne);
	Mustache.parse(tplTwo);

	if (typeof data.type !== 'undefined') {
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

			var rendered = Mustache.render(tplOne, currentPage);

			// Overwrite the contents of #running with the rendered HTML
			$('#running').html(rendered);
		}

		if (data.type === 'image') {
			currentPage.progress = (parseInt(data.id) + 1) * 100 / currentPage.images.length;
			currentPage.images[data.id].status = data.status;
			currentImgIndex = data.id;

			var rendered = Mustache.render(tplOne, currentPage);
			$('#running').html(rendered);
		}

		if (data.type === 'image progress') {
			currentPage.images[currentImgIndex].progress = data.progress;
			var rendered = Mustache.render(tplOne, currentPage);
			$('#running').html(rendered);

			if ((currentImgIndex + 1 === currentPage.images.length) && data.progress === 100) {
				rendered = Mustache.render(tplTwo, currentPage);
				$('#completed').html(rendered);
				$('#running').html('');
			}
		}

		if (data.type === 'error') {
			log('Error: ' + data.message);
		}
	}
}

$(document).ready(function() {
	init();

	$('#form textarea').text(pages.toString().replace(/,/g, ',\n'));

	$('#form').on('submit', function(event) {
		event.preventDefault();
		send($('#form textarea')[0].value);
	});
});
