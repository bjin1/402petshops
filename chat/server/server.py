# A peer-to-peer chat room server
#
# Command based on utf-8 string:
# Client -> Server
#     NAME--<name>
#     MESSAGE:<str>
#     QUIT

# Server -> Client                          
#     WELCOME--<name>
#     HAS_CONNECTED--<name> 
#     JOIN--<name>
#     VALID_NAME--<name>


#     OTHER_SIDE_LEFT
#     WAIT_OTHER_SIDE_CONNECTED
#     PLEASE_REGISTER_NAME
#     REJECT
#     ERROR_NAME

#     MESSAGE:<text> 
#     VALID_MESSAGE:<message>
#     SYSTEMERROR:<str>
#     ERROR_COMMMAND:<text>

import socketserver
import threading
import time
import re
import socket

clear = 1  #room clear
thread_max_number = 50   #max connect
thread_connect_number = 0  

class ThreadedTCPServer(socketserver.ThreadingMixIn, socketserver.TCPServer):
    daemon_threads = True
    allow_reuse_address = True

class UserHandler(socketserver.StreamRequestHandler):
    def handle(self):
        self.other_side = None
        self.state = ''
        global clear
        global thread_max_number
        global thread_connect_number
        self.run = 1                
        thread_connect_number +=1   
        self.client = f'{self.client_address} on {threading.currentThread().getName()}'
        print(f'Connected: {self.client} --{thread_connect_number}')
        try:
            if(thread_connect_number > thread_max_number):  
                self.send('REJECT')
                raise ValueError('connection is max, server reject  require')
            self.init()
            if self.name != '/quit':
                self.process_command()
        except Exception as e:
            print(e)
        finally:
            try:
                if self.other_side is not None:       
                    self.other_side.run = 0
                    self.other_side.send('OTHER_SIDE_LEFT')  
                    #self.other_side.other_side = None
            except:
                pass
            
        if self.state == "hold":
            if self.other_side is None and self.run == 1:   
                clear = 1
        thread_connect_number -=1
        print(f'Closed: {self.client} --{thread_connect_number}')

    def send(self, message):
        #self.wfile.write(f'{message}'.encode('utf-8'))
        self.wfile.write(f'{message}\n'.encode('utf-8'))
        #time.sleep(0.05)   
        #print(message)

    def init(self):
        self.name = self.process_register()      #wait user register
        if self.name == '/quit':                 
            return
        ChatRoom.join(self)
        self.send(f'WELCOME--{self.name }')
        if self.state == 'hold':
            self.chatroom.bob_handle = self
            self.send(f'WAIT_OTHER_SIDE_CONNECTED') 
        else:
            self.other_side = self.chatroom.bob_handle
            self.other_side.other_side = self
            self.send(f'JOIN--{self.other_side.name}')  
            try:
                if self.other_side is None:
                     raise ValueError('the chatroom is empty,program will close')  
                self.other_side.send(f'HAS_CONNECTED--{self.name}')  
            except Exception as e:
                print(f'{e} on {threading.currentThread().getName()}')
                self.send(f'SYSTEMERROR:{str(e)}')

    def process_register(self):    
        self.send(f'PLEASE_REGISTER_NAME')
        while True:
            command = self.rfile.readline()
            if not command:
                break
            command = command.decode('utf-8')
            if command.startswith('QUIT'): 
                self.send("end")
                # print(f'{self.client} QUIT')
                return "/quit"
            elif command.startswith('NAME'):
                name = command.split('--',1)[1].replace('\n','')
                if re.match("[A-Z][a-z]* ?([A-Z][a-z]*)?",name):  
                    self.send(f'VALID_NAME--{name}')         
                # print(f'{self.client} MESSAGE: {command}"')
                    return name
                else:
                    self.send(f'ERROR_NAME')
            else:
                command.replace('\n','')  #去掉换行
                self.send(f'ERROR_COMMMAND:{command}')

    def process_command(self):   #deal with command
        while True:
            command = self.rfile.readline()
            if not command:
                break
            if self.run == 0:
                break
            command = command.decode('utf-8')
            command.replace('\n','') 
            if command.startswith('QUIT'):
                if self.other_side is not None:
                   self.run = 0
                   self.other_side.other_side = None
                   self.send("end")
                # print(f'{self.client} QUIT')
                return
            elif command.startswith('MESSAGE:'):
                # print(f'{self.client} MESSAGE: {command}"')
                self.handle_message(command)
            else:
                self.send(f'ERROR_COMMMAND:{command}')

    def handle_message(self, message):     #deal with message
        try:
            if self.other_side is None:   
                raise ValueError('Don\'t have another people in chatroom')
            self.send('VALID_MESSAGE:' + message)   
            self.other_side.send(f'{message}') 
        except Exception as e:
            print(f'{e} on {threading.currentThread().getName()}')
            self.send('SYSTEMERROR:' + str(e))

class ChatRoom:
    now_room = None
    room_selection_lock = threading.Lock()
    hold_people = "hold"
    join_people = "join"

    def __init__(self):
        self.bob_handle = None

    @classmethod
    def join(cls, user):
        global clear
        with cls.room_selection_lock:
            if clear == 1:
                cls.now_room = None
                clear = 0
            if cls.now_room is None:
                cls.now_room = ChatRoom()
                user.chatroom = cls.now_room
                user.state =  cls.hold_people   
                #user.name =  name      
            else:
                #user.name =  name
                user.state =  cls.join_people
                user.chatroom = cls.now_room
                cls.now_room = None
if __name__ == '__main__':
    host = socket.gethostname()
    with ThreadedTCPServer((host, 62222), UserHandler) as server:
        print(f'The peer-to-peer chat room server is running...')
        server.serve_forever()
