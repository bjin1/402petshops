# Take the Red Pill
Our whizbang server will plug you in to a whole new world!

## Protocol Usage
A peer-to-peer chat room server
Command based on utf-8 string:
# Client -> Server
     NAME--<name>  #user register name
     MESSAGE:<str> #user send message
     QUIT          #quit

# Server -> Client                          
     WELCOME--<name>  
     HAS_CONNECTED--<name>   #tell user who's chatroom he joined
     JOIN--<name>            #tell user who joined his chatroom
     VALID_NAME--<name>      #tell user he register success


     OTHER_SIDE_LEFT         #other side people left
     WAIT_OTHER_SIDE_CONNECTED  #wait another people join in chatroom
     PLEASE_REGISTER_NAME     #require people register
     REJECT                   #reject user connect
     ERROR_NAME               #user name has error

     MESSAGE:<text>           #send user message
     VALID_MESSAGE:<message>  #respond user send message
     SYSTEMERROR:<str>        #some unexpected system error
    ERROR_COMMMAND:<text>    

## Public Server
You can try us out at ec2-18-216-173-39.us-east-2.compute.amazonaws.com or IP:18.216.173.39 port 62222!
# connect cloud 
ssh -i "Demoserver.pem" ubuntu@ec2-18-216-173-39.us-east-2.compute.amazonaws.com
# start server
python3 ./server.py
