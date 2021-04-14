const app = require('express')();
const server = require('http').Server(app);
const io = require('socket.io')(server);
const db = require("./database");

io.on('connection', function (socket) {
    socket.on('message', function (data) {
        io.sockets.to(data.receiver).emit('message', data);

		db.Message.create({"id":null,"sender":data.sender,"header":data.header,"receiver":data.receiver,"message":data.message,"time":data.time}).then(msg => {
		  console.log("save");
		});
    });

    socket.on('join',function (data) {
        //bind user info with socket obj
        if(!socket.room && !socket.user){
            socket.user = data.user;
            socket.room = data.room;
        }else{
            io.sockets.to(socket.room).emit('leave',{
                'id': socket.id,
                'type': 'leave',
                'user': socket.user,
                'room': socket.room,
                'time': Date.parse(new Date()) / 1000
            });
            
            socket.leave(socket.room);
        }

        socket.room = data.room;

        socket.join(data.room);

        io.sockets.to(data.room).emit('join',{
            'id': socket.id,
            'type': 'join',
            'user': socket.user,
            'room': data.room,
            'time': Date.parse(new Date()) / 1000
        });
    });

    socket.on('disconnect',function (data) {
		//send msg
        io.sockets.to(socket.room).emit('disconnect',{
            'id': socket.id,
            'type': 'disconnect',
            'user': socket.user,
            'room': socket.room,
            'time': Date.parse(new Date()) / 1000
        });
    });
});

server.listen(3000,{origins:'*'},function () {
    console.log('socket.io connect successful');
});