const LoadTester = require('./LoadTester');
const sleep = require('./sleep');

exports.handler = async (config) => {

  const loadTester = new LoadTester(config);

  await loadTester.init();

  while (loadTester.report.successful === undefined) {
    await sleep(500);
  }

  return {
    statusCode: 200,
    body: loadTester.report,
  };

};
