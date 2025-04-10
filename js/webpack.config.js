const path = require('path');

module.exports = {
  entry: './source/preview.js',
  output: {
    path: path.resolve(__dirname, './'),
    filename: 'preview.js'
  },
  mode: 'production',
  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        use: [] // No Babel, no transforms
      }
    ]
  },
  resolve: {
    fallback: {
      fs: false,
      child_process: false
    }
  }
};
