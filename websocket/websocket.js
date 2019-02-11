var express 	= require('express');
var app 		= express();
var server 		= require('http').createServer(app);
var io 			= require('socket.io').listen(server);
var redis 		= require('socket.io-redis');
var port 		= process.env.PORT || 3000;

io.adapter(redis({ host: 'laratrack_redis', port: 6379 }));

server.listen(port, function() {
  console.log('Server listening at port %d', port);
});

app.all('/', function(req, res) {
	res.header('Access-Control-Allow-Origin', '*');
	res.json({'success': true});
});

io.on('connection', function(socket) {

	console.log('a connection has been created: ' + io.engine.clientsCount);

	socket.on('subscribe', function(channel) { 
		console.log('joining channel', channel);
		socket.join(channel);
		socket.emit(channel + '|subscribe', 1);
	});

	socket.on('unsubscribe', function(channel) {  
		console.log('leaving channel', channel);
		socket.emit(channel + '|unsubscribe', 1);
	});

	socket.on('channel', function(data) { 
		io.sockets.in(data.channel).emit(data.channel + '|' + data.event, data.data);
	});

	socket.on('disconnect', function() {
		console.log('a connection has been terminated: ' + io.engine.clientsCount);
	});

});