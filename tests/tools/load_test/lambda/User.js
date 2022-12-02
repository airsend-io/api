const axios = require('axios');
const qs = require('query-string');
const moment = require('moment');
const WebSocket = require('websocket').w3cwebsocket;
const { LoremIpsum } = require('lorem-ipsum');
const _ = require('lodash');
const { v4: uuidv4 } = require('uuid');

class User {

  constructor(data, core) {

    const [ email, password ] = data.split(":");

    this.core = core;

    this.email = email;
    this.password = password;

    // base axios config
    this.axiosConfig = {
      timeout: 60000,
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      }
    };

    this.lorem = new LoremIpsum({
      sentencesPerParagraph: {
        max: 8,
        min: 4
      },
      wordsPerSentence: {
        max: 16,
        min: 4
      }
    });

    this.connection = null;

    this.sentEvents = 0;
    this.lastMessageTimeout = 0;

  }

  async init() {

    //console.log("Logging in as", this.email, this.password);

    try {

      await this.login();

    } catch (e) {
      console.log(e);
    }

  }

  async startLoad() {

    const i1 = setInterval(() => {
      this.sendTypingEvent();
    }, this.core.config.typingInterval);

    let messagesCounter = 0;
    const i2 = setInterval(() => {
      this.postMessage();
      messagesCounter++;
      if (messagesCounter >= this.core.config.numberOfMessages) {
        clearInterval(i1);
        clearInterval(i2);
        this.core.exit(this.email);
      }
    }, this.core.config.messageInterval);

  }

  async sendTypingEvent() {
    this.ws.send(JSON.stringify({
      command: 'ws_ephemeral_typing',
      user_email: this.email,
      channel_id: this.core.config.channel
    }));
    this.sentEvents++;
    //this.core.displayStats();
  }

  async postMessage() {

    const startTime = moment();
    const messageId = uuidv4();
    const message = `[${messageId}] ${this.lorem.generateWords(Math.floor(Math.random() * (50 - 5) + 5))}`;
    this.core.messages[messageId] = { startTime };

    let response = null;
    let errorStatus = null;

    try {
      response = await axios.post(`${this.core.config.url}/v1/chat.postmessage`, qs.stringify({
        text: message,
        channel_id: this.core.config.channel,
        send_email: 0
      }), this.axiosConfig);
    } catch (error) {
      response = error.response;
    }

    if (this.core.bar) {
      this.core.bar.update(Object.keys(this.core.messages).length);
    }
    this.core.messages[messageId].apiReturnTime = moment();
    this.core.messages[messageId].status = 200;

  }

  async react(message) {

    const { data } = await axios.post(`${this.core.config.url}/v1/chat.reactmessage`, qs.stringify({
      message_id: message,
      emoji_value: "👍",
      remove: 0
    }), this.axiosConfig);

    console.log("REACT", data);

  }

  async connect() {

    const { data } = await axios.get(`${this.core.config.url}/v1/rtm.connect`, this.axiosConfig)

    if(data.meta.ok) {

      const { rtm } = data;

      this.ws = new WebSocket(rtm.ws_endpoint, 'echo-protocol');

      this.ws.onmessage = (e) => {

        const event = JSON.parse(e.data);

        if(event.event === 'chat.postMessage') {

          const { message } = event.payload;

          const match = _.get(message, 'content', '').match(/^\[([0-9a-f-]+)]/);
          if (match) {
            if (this.core.messages[match[1]]) {
              this.core.messages[match[1]].websocketReturnTime = moment();
            }
          }

        }

      };

      this.ws.addEventListener('open',  (e) => {

        //console.log("Socket connection is now open");

        // authenticate using JWT
        this.ws.send(JSON.stringify({
          command: 'ws_auth',
          auth_token: rtm.ws_token
        }));

        this.startLoad();

      });

    } else {
      throw new Error(`Failed to connect ${this.email}:${this.password}`);
    }

  }

  async login() {

    const { data } = await axios.post(`${this.core.config.url}/v1/user.login`, qs.stringify({
      email: this.email,
      password: this.password
    }), this.axiosConfig)

    if(data.meta.ok) {
      this.user = data.user;
      this.token = data.token;

      this.axiosConfig.headers['Authorization'] = `Bearer ${this.token}`;
    } else {
      throw new Error(`Failed to authenticate ${this.email}:${this.password}`);
    }

  }

}

module.exports = User;
