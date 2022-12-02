## AirSend Realtime Messages

This section documents various messages supported between Websocket endpoint and Client

### SERVER to CLIENT Push EVENT Structure
The data sent from websocket to client is shown below. The top level will contain *event* and *payload* .
The *event* is a string key value of an event data being sent to the client. Each of the supported event is discussed below
The *payload* section can contain relevant data of each of the event.

      { 
         "meta":{ 
               "api":"v1"
            },
         "event":"subsystem.op",
         "payload":{ 
            "<someobject1>":{
            },
            "<someobject2>":{
            }
            .
            .
         }
      }


#### Establishing RTM Connection

This section discusses the realtime login flow

![RTM Login Flow](./rtmlogin.png) 

<ol>
    <li>
    Perform user.login  api call with HTTP endpoint. Upon successful connection, a bearer auth token will be returned by the user.login API   
    </li>
    <li>
    Perform rtm.connect api call with HTTP endpoint with the bearer token from login response. This will return two information in rtm object. 
    The ws_endpoint indicates the Websocket endpoint to use for connection.
    The ws_token is the unique connection token to establish the websocket connection
    
        { 
           "meta":{ 
              "ok":true
           },
           "rtm":{ 
              "ws_endpoint":"ws://127.0.0.1:9001",
              "ws_token":"eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyX2lkIjo5MTAxMDAwMCwiY2xpZW50X2lwIjoiMTcyLjIwLjAuMSIsInVzZXJfYWdlbnQiOiJNb3ppbGxhXC81LjAgKE1hY2ludG9zaDsgSW50ZWwgTWFjIE9TIFggMTBfMTRfNSkgQXBwbGVXZWJLaXRcLzUzNy4zNiAoS0hUTUwsIGxpa2UgR2Vja28pIENocm9tZVwvNzcuMC4zODY1LjkwIFNhZmFyaVwvNTM3LjM2IiwiZXhwaXJ5IjoiMjAxOS0xMC0wNSAxNjoyMDoxMyIsImZpbmdlcl9wcmludCI6ImJqY3BvZ2FrdWJ5YSJ9.c3amovxprwo7hDvf0vkPRSN09WnnZzTGls4DoBjUxSI"
           }
        } 
   </li>
   <li>
   Create a websocket connection with the ws_endpoint url. 
   The first message from the client must the ws_auth command with the auth_token containing the ws_token received from rtm.command API
   
   __If this is not sent, no data will flow and the connection will be terminated__
   
        {
            "command": "ws_auth",
            "auth_token" : <ws_token received from earlier rmt.connect call>
        }
    
   </li>
    
</ol>

__Server will send *ping* frames every 25 seconds and client must respond with a *pong* frame for each *ping* frame. If a *pong*
is not received, the connection will be terminated__

***
### SERVER to CLIENT events

#### EVENT: 'chat.postMessage' 
This event is sent to all users of a channel for each new chat message for that channel. The structure of this message is shown below.

The payload contains a *message* object. The *message* object structure is defined in HTTP endpoint API documentation.

    { 
       "meta":{ 
          "api":"v1"
       },
       "event":"chat.postMessage",
       "payload":{ 
          "message":{ 
             "user_id":91010006,
             "channel_id":11010004,
             "display_name":"test1@test.com",
             "attachments":[ 
    
             ],
             "message_type":1,
             "is_edited":false,
             "parent_message":[ 
    
             ],
             "emoticons":[ 
    
             ],
             "created_on":"2019-10-17 16:44:32",
             "id":10004,
             "created_on_ts":1571330672,
             "content":"fs",
             "read_user_count":"0",
             "read_users":[ 
    
             ]
          }
       }
    }
 
 #### EVENT: 'chat.updateMessage'
 This event is sent to all users of a channel whenever a message is updated. The update could happen because the owner of
 a message updated the message or if events such as unfurling, read notification, reactions are done to a message.
 Typically the message should be re-rendered if it is loaded and should be ignored if it is not loaded (May be the user is
 in a different channel or the user is too old and is not loaded). 
 
 The payload contains a *message* object. 
 
    { 
       "meta":{ 
          "api":"v1"
       },
       "event":"chat.updateMessage",
       "payload":{ 
          "message":{ 
             "id":10000,
             "user_id":91010000,
             "channel_id":11010000,
             "display_name":"Chat Unit Test User",
             "content_text":"This is a unit test message",
             "attachments":[ 
    
             ],
             "message_type":1,
             "is_edited":false,
             "parent_message":[ 
    
             ],
             "emoticons":[ 
                { 
                   "uid":91010000,
                   "dn":"Chat Unit Test User",
                   "ev":"U 1F600"
                }
             ],
             "created_on":"2019-10-10 18:36:57",
             "read_user_count":"0",
             "read_users":[ 
    
             ]
          }
       }
    }
 
 #### EVENT: 'chat.deleteMessage'
 This event is sent to all users of a channel whenever a message is deleted. The payload contains the message id and 
 channel id of the deleted message. If the message is not loaded. 
 
     { 
        "meta":{ 
           "api":"v1"
        },
        "event":"chat.deleteMessage",
        "payload":{ 
           "message_id":10000,
           "channel_id":11010000
        }
     }

 
 
 
 #### EVENT: 'channel.create'
 This event is sent when a channel object is created that this logged in user is associated with. 
 
 The payload will contain *channel* object. The *channel* object is defined in HTTP API spec. 
 Here is an example message.
 
     { 
        "meta":{ 
           "api":"v1"
        },
        "event":"channel.create",
        "payload":{ 
           "channel":{ 
              "channel_name":"Channel 22",
              "channel_email":"chan_hderu5@airsendmail.com",
              "last_active_on":"2019-10-17 16:43:31",
              "created_on":"2019-10-17 16:43:31",
              "created_by":91010006,
              "id":11010005,
              "created_on_ts":1571330611,
              "last_active_on_ts":1571330611,
              "latest_message":null,
              "members":[ 
                 { 
                    "id":91010006,
                    "email":"test1@test.com",
                    "display_name":"test1@test.com",
                    "last_active_on":"2019-10-17 16:42:29",
                    "online_status":true,
                    "last_active_on_ts":1571330549,
                    "has_avatar": false
                 }
              ],
              "channel_roots":[ 
                 { 
                    "type":"files",
                    "location":"/cf/10015"
                 },
                 { 
                    "type":"wiki",
                    "location":"/wf/10016"
                 }
              ],
              "total_fs_count":1,
              "total_file_count":0,
              "total_folder_count":1,
              "total_fs_size":0
           }
        }
     }
 
 
 #### EVENT: 'user_added.channel'
 This event is sent to all users of a channel when a user is added to a channel.
 The payload will contain *channel_id*  which had an user added and *user* object that
 was added to the channel.
 
    { 
       "meta":{ 
          "api":"v1"
       },
       "event":"user_added.channel",
       "payload":{ 
          "channel_id":11010007,
          "user":{ 
             "id":91010006,
             "email":"test1@test.com",
             "display_name":"test1@test.com",
             "last_active_on":"2019-10-17 16:44:32",
             "online_status":true,
             "last_active_on_ts":1571330672,
             "has_avatar": false,
          }
       }
    }
    
 #### EVENT: 'user_removed.channel'
 This event is sent to all users of a channel when a user leaves a channel due to a leave
 or kick operation. A separate bot message will also be sent to display. This event is to update the
 channel members. Note: If *removed_by_user_id* is same as *removed_user_id* , then it means the user left the channel.
 If they are different, then the user was kicked from the channel.
     
     { 
        "meta":{ 
           "api":"v1"
        },
        "event":"user_removed.channel",
        "payload":{ 
           "removed_user_id":91010003,
           "removed_by_user_id":91010001,
           "channel_id":11010001
        }
     }
     
     
 #### EVENT: 'channel.update'
 This event will be sent to all members of a channel when a channel is updated.
 The update operation can be name update, user added or removed or image/logo updated etc
 When this event is received, the clients must refresh the channel information stored locally.
 The payload will contain *channel*  object that was updated.
 
    { 
       "meta":{ 
          "api":"v1"
       },
       "event":"channel.update",
       "payload":{ 
          "channel":{ 
             "id":11010010,
             "channel_name":"test",
             "channel_email":"chan+nz7lws@airsendmail.com",
             "channel_status":100,
             "last_active_on":"2019-11-07 03:42:34",
             "created_on":"2019-11-07 03:42:34",
             "created_by":"91010001",
             "created_on_ts":1573098154,
             "last_active_on_ts":1573098154,
             "latest_message":null,
             "members":[ 
                { 
                   "id":91010001,
                   "email":"anis@codelathe.com",
                   "display_name":"Anis Abdul",
                   "has_avatar":false,
                   "last_active_on":"2019-11-07 03:48:00",
                   "online_status":true,
                   "last_active_on_ts":1573098480
                }
             ],
             "channel_roots":[ 
                { 
                   "type":"files",
                   "location":"/cf/10030"
                },
                { 
                   "type":"wiki",
                   "location":"/wf/10031"
                }
             ],
             "total_fs_count":1,
             "total_file_count":0,
             "total_folder_count":1,
             "total_fs_size":0
          }
       }
    } 
    
  #### EVENT: 'channel.remove'
  This event is sent to all users of a channel when a channel is removed. The payload
  contains the id of the channel that was removed.
  
    { 
       "meta":{ 
          "api":"v1"
       },
       "event":"channel.remove",
       "payload":{ 
          "channel_id":11010001
       }
    }
 
  #### EVENT: 'chat.userTyping'
  This event can be all other users of a channel that a message might be coming soon. This is originated from another client
  The payload contains a *user* object that is typing a message
      
      { 
         "meta":{ 
            "api":"v1"
         },
         "event":"chat.userTyping",
         "payload":{ 
            "user":{ 
               "id":91010006,
               "email":"test1@test.com",
               "display_name":"test1@test.com",
               "last_active_on":"2019-10-17 16:40:30",
               "online_status":true,
               "last_active_on_ts":1571330430,
               "has_avatar": false,
            },
            "channel_id":11010004
         }
      }
 
 #### EVENT: 'user.online'
 
 This event will be sent to all channels when a member of the channel comes online
 
     { 
        "meta":{ 
           "api":"v1"
        },
        "event":"user.online",
        "payload":{ 
           "user":{ 
              "id":91010007,
              "email":"test2@test.com",
              "display_name":"Test user",
              "last_active_on":"2019-10-17 16:59:36",
              "online_status":true,
              "last_active_on_ts":1571331576,
              "has_avatar": false,
           },
           "channel_id":11010007
        }
     }
     
 #### EVENT: 'user.offline'
 This event will be sent when user disconnects from ALL realtime connections
 
    { 
       "meta":{ 
          "api":"v1"
       },
       "event":"user.offline",
       "payload":{ 
          "user":{ 
             "id":91010007,
             "email":"test2@test.com",
             "display_name":"Test user",
             "last_active_on":"2019-10-17 17:03:07",
             "online_status":false,
             "last_active_on_ts":1571331787,
             "has_avatar": false,
          },
          "channel_id":11010007
       }
    }
    
 #### EVENT: 'user.profileUpdate'
 This event will be sent when a user updates the avatar, display name 
 
    { 
       "meta":{ 
          "api":"v1"
       },
       "event":"user.profileUpdate",
       "payload":{ 
          "user":{ 
             "id":91010001,
             "email":"test1@test.com",
             "display_name":"test test test",
             "has_avatar":true,
             "last_active_on":"2019-10-18 16:47:19",
             "online_status":true,
             "last_active_on_ts":1571417239
          },
          "channel_id":11010000
       }
    }
 
 
 #### EVENT: 'alert.notification'
 This event will be sent to a user when an alert has to be notified. If a message is quoted, or if a 
 message is reacted by another user or if a @mention is done then an alert record will be sent. There are
 other conditions like action due etc can also generate alerts. 
 
     { 
        "meta":{ 
           "api":"v1"
        },
        "event":"alert.notification",
        "payload":{ 
           "alert_id":7,
           "from":[ 
              { 
                 "id":91010001,
                 "email":"test2@test.com",
                 "display_name":"Test 2",
                 "has_avatar":false,
                 "last_active_on":"2019-10-25 15:23:29",
                 "online_status":true,
                 "last_active_on_ts":1572017009
              }
           ],
           "message_id":10006,
           "alert_text":"... 2 : @91010000 has added @910 ...",
           "channel_id":11010000,
           "is_read":false
        }
     } 
     
#### EVENT: 'action.create'
This event will be sent when a new event is created. 

    { 
       "meta":{ 
          "api":"v1"
       },
       "event":"action.create",
       "payload":{ 
          "action":{ 
             "channel_id":11000001,
             "action_name":"test action2",
             "action_desc":"action desc",
             "action_type":1,
             "action_status":0,
             "due_on":"2019-11-29 01:31:18",
             "created_on":"2019-11-28 01:46:37",
             "created_by":91000002,
             "updated_on":null,
             "updated_by":null,
             "id":3
          },
          "channel_id":11000001
       }
    }
    
#### EVENT: 'action.create'
This event will be sent when a new event is created. 

    { 
       "meta":{ 
          "api":"v1"
       },
       "event":"action.create",
       "payload":{ 
          "action":{ 
             "channel_id":11000001,
             "action_name":"test action2",
             "action_desc":"action desc",
             "action_type":1,
             "action_status":0,
             "due_on":"2019-11-29 01:31:18",
             "created_on":"2019-11-28 01:46:37",
             "created_by":91000002,
             "updated_on":null,
             "updated_by":null,
             "id":3
          },
          "channel_id":11000001
       }
    }
    
#### EVENT: 'channel.unreadCountUpdate'
This event will be sent back to the user when a read notification for a message is sent. This allows 
the clients to update their unread counts.

    {
       "meta":{
          "api":"v1"
       },
       "event":"chat.unreadCountUpdate",
       "payload":{
          "total_unread_count":0,
          "channel_id":11000002,
          "channel_unread_count":0,
          "oldest_unread_message_id":0
       }
    }   
    
#### EVENT: 'lock.acquire'
This event will be sent to all users of a channel when a file in the channel is locked by a user

    {
       "meta":{
          "api":"v1"
       },
       "event":"lock.acquire",
       "payload":{
          "lock_id":9,
          "channel_id":11000002,
          "user_id":91010000,
          "path":"/cf/4/attachments/Screen Shot 2020-04-10 at 1.25.09 PM.png"
       }
    }
   
#### EVENT: 'lock.release'
This event will be sent to all users of a channel when a locked file in the channel is released by a user

    {
       "meta":{
          "api":"v1"
       },
       "event":"lock.release",
       "payload":{
          "lock_id":9,
          "channel_id":11000002,
          "user_id":91010000,
          "path":"/cf/4/attachments/Screen Shot 2020-04-10 at 1.25.09 PM.png"
       }
    }

   
***

### CLIENT to SERVER commands
 
 This section contains the event commands that can be sent to server after authentication.
 
 __If any message is sent before authentication or if any unknown message is received from a client, the server terminate the connection.__
 
 
#### COMMAND: 'ws_auth'
This is the authentication command to authenticate the connection with Websocket server. This has to be done before sending
any other command to the backend to prevent disconnection.

        {
            "command": "ws_auth",
            "auth_token" : <ws_token received from earlier rmt.connect call>
        }

#### COMMAND: 'ws_ephemeral_typing'
This event can be sent when a client wants to send *chat.userTyping* event needs to be sent to other users of the channel.
This event needs to be throttled to prevent overloading (else it may be dropped by the server). 
The *user_email* is the email id of sender of this command. The *channel_id* is the id of the channel whose members need to be notified. 

    { 
       "command":"ws_ephemeral_typing",
       "channel_id":"11010000"
    }
    
#### COMMAND: 'ws_read_notification'
This event is sent when user wants read notification for a message. The client must send this command
for each of the message that is considered as "read". This will generate chat.updateMessage notification for all members of the channel
*command* is command string, *channel_id* is the id of channel of this message. *message_id* is the message identifier to add notification.

    {
        'command':'ws_read_notification',
        'channel_id':'1101000',
        'message_id':'10000'
    }

#### COMMAND: 'ws_all_read_notification'
This event is sent when user wants mark all messages of a channel as read for that user. This will NOT generate chat.updateMessage notification
*command* is command string, *channel_id* is the id of channel of this message.

    {
        'command':'ws_all_read_notification',
        'channel_id':'1101000',
    }
    
#### COMMAND: 'ws_add_reaction'
This event is sent when user wants to add a reaction to a message

    {
        'command':'ws_add_reaction',
        'message_id': '10000',
        'emoji_value' : Unicode id of the emoji
    }

#### COMMAND: 'ws_remove_reaction'
This event is sent when user wants to remove a reaction to a message

    {
        'command':'ws_remove_reaction',
        'message_id': '10000',
        'emoji_value' : Unicode id of the emoji
    }


## BOT Messages
Bot messages are system generated messages generated automatically and will be sent when channel relevant actions occur 
in the system.

The BOT message will be a *message* object with the following properties
 
message.type will be set to 5
message.content will be a JSON object. 
The message.content will have *type* field and *bot_message* field.



***
#### Bot message types
    1 : File was added or updated in a channel
    2 : User was added to a channel


Here is an example BOT Message showing a user invite action being notified to all users of a channel

    { 
       "meta":{ 
          "api":"v1"
       },
       "event":"chat.postMessage",
       "payload":{ 
          "message":{ 
             "user_id":91000001,
             "channel_id":11010000,
             "display_name":"AirBot",
             "attachments":[ 
    
             ],
             "message_type":5,
             "is_edited":false,
             "parent_message":[ 
    
             ],
             "emoticons":[ 
    
             ],
             "created_on":"2019-10-18 16:56:55",
             "id":10001,
             "created_on_ts":1571417815,
             "content":{ 
                "type":2,
                "bot_message":"@91010001 has added @91010002 to this channel"
             },
             "read_user_count":"0",
             "read_users":[ 
    
             ]
          }
       }
    }
    
    
     