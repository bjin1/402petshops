# A peer-to-peer char room client

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


import sys
import socket
import threading
import time
import re

sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
thread_run = 1
link_flag = 0
name_flag = 0
other_name = ''
self_name = ''

def read_message_thread():
    global thread_run
    global link_flag
    global name_flag 
    while thread_run:
        message = ''
        command = ''
        while link_flag:
            try:
                re_data = sock.recv(256)
                if not re_data:
                   break
            except Exception as e:
                thread_run = 0
                link_flag = 0
                sock.close()
                print(e)
                print("system:server link break")
                print("system:input any key to exit")
                break
            command += re_data.decode("utf-8")  
            command_list = command.split('\n')
            for i in range(len(command_list)):
                message = command_list[i]   
                if i ==len(command_list)-1 :
                    command = message
                    if message != '':
                        break
                if message.startswith('HAS_CONNECTED'):  
                    data = message.split('--',1)
                    other_name = data[1]
                    print("system:" + other_name + " has join in chatroom")
                elif message.startswith('JOIN'):  
                    data = message.split('--',1)
                    other_name = data[1]
                    print("system:welcome join in " + other_name +" chatroom")
                elif message.startswith('VALID_MESSAGE:'): 
                    data = message.split(':',2)
                    print('me:'+ data[2])
                elif message.startswith('MESSAGE:'):  
                    data = message.split(':',1)
                    print(other_name + ':' + data[1])
                elif message.startswith('WELCOME'):   
                    data = message.split('--',1)
                    print("system:welcome " + data[1])
                elif message.startswith('WAIT_OTHER_SIDE_CONNECTED'): 
                    print("system:waiting for otherside connect")
                elif message.startswith('PLEASE_REGISTER_NAME'):     
                    print("system:please input your name ex:Bob Smith or Bob")
                elif message.startswith('ERROR_NAME'):     
                    print("system:error name,please input your name")
                elif message.startswith('VALID_NAME'):   
                    data = message.split('--',1)
                    self_name = data[1]
                    name_flag = 1
                    print("system:register success")
                elif message.startswith('SYSTEMERROR'):  
                    data = message.split(':',1)
                    print("system error:" + data[1])
                elif message.startswith('REJECT'):    
                    print("system:connection is max, server reject your require")
                    print("system:input any key to exit")
                    thread_run=0
                    link_flag = 0
                    return
                elif message.startswith('OTHER_SIDE_LEFT'):   
                    print('system:' + other_name + ' has left chatroom.')
                    print("system:link will break")
                    sock.sendall('QUIT\n'.encode('utf-8'))
                    thread_run=0
                    link_flag = 0
                    #print("system:server link break")
                    print("system:input any key to exit")
                    return

def net_link_thread(ip_address):
    global link_flag
    global thread_run
    while thread_run:    
        while not link_flag:
            try:
                sock.connect((ip_address, 62222))
                link_flag =1
                break
            except Exception as e:
                    print(e)
                    break
        time.sleep(2)


if __name__ == '__main__':
    if len(sys.argv) != 2:
        ip_address =  '18.216.173.39' #'localhost' '18.216.173.39' 
    else:
        ip_address = sys.argv[1]
    print('Welcome to use my peer-to-peer char room. Enter lines of message to send. Eeter "QUIT" to quit.')
    
    net_link_t = threading.Thread(target=net_link_thread,args= (ip_address,))  
    net_link_t.start()  

    read_message_t = threading.Thread(target=read_message_thread)     
    read_message_t.start()
    
    while True:
        message = input()
        if not message:
            break
        if thread_run == 0:
            break
        if message == 'QUIT':
            if link_flag:
                sock.sendall('QUIT\n'.encode('utf-8'))
                print("system:server link break")
            else:
                print("system:application will close") 
            thread_run = 0
            break
        if link_flag:
            try:
                if name_flag:
                    sock.sendall(f'MESSAGE:{message}\n'.encode('utf-8'))
                else:
                    if re.match("[A-Z][a-z]* ?([A-Z][a-z]*)?",message):   
                        sock.sendall(f'NAME--{message}\n'.encode('utf-8'))
                    else:
                        print("please input name at correct style")
                        continue
            except Exception as e:
                thread_run = 0
                link_flag = 0
                sock.close()
                print(e)
                print("system:server link break")
                print("system:input any key to exit")
                break
