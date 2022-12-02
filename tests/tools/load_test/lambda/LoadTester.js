const Parallel = require('async-parallel');
const User = require('./User');
const sleep = require('./sleep');
const cliProgress = require('cli-progress');

class LoadTester {

    constructor(config) {

        this.config = config;
        this.messages = {};
        this.finished = [];
        this.report = [];
        this.messagesFailed = [];
        this.bar = !config.progressbar ? null : new cliProgress.SingleBar({}, cliProgress.Presets.shades_classic);

    }

    async init() {

        const { users } = this.config;

        if (this.bar) {
            this.bar.start(this.config.numberOfMessages*this.config.users.length);
        }

        await Parallel.each(users, async item => {
            let user = new User(item, this);
            await user.init();
            await user.connect();
        });

    }

    async exit(email) {

        this.finished.push(email);
        const users = this.config.users.map(item => item.split(':')[0]);

        if (users.filter(item => !this.finished.includes(item)).length === 0) {

            // check the messages that still don't have a return time for api or websocket
            let waitTime = 0;
            let failedApiResponse;
            let failedWebSocketResponse;
            while (true) {

                failedApiResponse = Object.values(this.messages)
                    .filter(item => typeof item.apiReturnTime === "undefined").length;
                failedWebSocketResponse = Object.values(this.messages)
                    .filter(item => typeof item.websocketReturnTime === "undefined").length;

                if (failedApiResponse === 0 && this.bar) {
                    this.bar.stop();
                }

                // if there is no failure, go forward to the report
                if (failedApiResponse === 0 && failedWebSocketResponse === 0) {
                    break;
                }

                // if we still have requests pending, give it time (max 10 seconds)
                waitTime += 500;
                await sleep(500);
                if (waitTime > 10000) {
                    break;
                }

            }

            if (this.bar) {
                this.bar.stop();
            }

            console.log('Processing the report...');

            // total messages successfully sent
            this.report.totalMessagesSent = Object.values(this.messages).length;

            // find the sucessful sent message (api responded 200)
            this.report.successfulSends = Object.values(this.messages).reduce((carry, current) => current.status === 200 ? ++carry : carry, 0);

            // find the messages that failed to be sent because of the rate limiter
            this.report.blockedByRateLimiter = Object.values(this.messages).reduce((carry, current) => current.status === 429 ? ++carry : carry, 0);

            // find the messages that failed because of unknown causes (basically anything that is not rate limit, should never happen on healthy envs)
            this.report.failedByUnknownReasons = Object.values(this.messages).reduce((carry, current) => current.status !== 200 && current.status !== 429 ? ++carry : carry, 0);

            // then find the messages that don't have return times (service not responding correctly)
            this.report.failedApiResponse = failedApiResponse;
            this.report.failedWebSocketResponse = failedWebSocketResponse;

            // calculate the response time
            const timesMap = Object.values(this.messages)
                // ignore messages that for some reason don't have the times set (should never happen, but avoids script breaking)
                .filter(item => typeof item.apiReturnTime !== "undefined" && typeof item.websocketReturnTime !== "undefined")
                // map the differences
                .map(item => ({
                    apiReturnTime: item.apiReturnTime.diff(item.startTime),
                    websocketReturnTime: item.websocketReturnTime.diff(item.startTime),
                    status: item.status,
                }));

            const apiReturnTimes = timesMap.map((item) => item.apiReturnTime);
            const websocketReturnTimes = timesMap.map((item) => item.websocketReturnTime);

            this.report.minApiReturnTime = apiReturnTimes.reduce((carry, current) => carry === null || carry > current ? current : carry, null);
            this.report.maxApiReturnTime = apiReturnTimes.reduce((carry, current) => carry < current ? current : carry, 0);
            this.report.avgApiReturnTime = apiReturnTimes.reduce((carry, current) => carry + current, 0) / apiReturnTimes.length;

            this.report.minWebsocketReturnTime = websocketReturnTimes.reduce((carry, current) => carry === null || carry > current ? current : carry, null);
            this.report.maxWebsocketReturnTime = websocketReturnTimes.reduce((carry, current) => carry < current ? current : carry, 0);
            this.report.avgWebsocketReturnTime = websocketReturnTimes.reduce((carry, current) => carry + current, 0) / apiReturnTimes.length;

            this.report.successful = timesMap;
            console.log('Exiting');
        }
    }



}

module.exports = LoadTester;
