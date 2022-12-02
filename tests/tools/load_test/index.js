const config = require('./config/default.json');
const {handler} = require('./lambda/index');
const util = require('util')


handler(config).then(response => {
    console.log(util.inspect(response, false, null, true));
    process.exit();
});