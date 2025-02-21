const path = require('path');
const TerserPlugin = require('terser-webpack-plugin');

module.exports = {
  mode: process.env.NODE_ENV,
  entry: {
    main: path.resolve(__dirname, 'assets/js/main.js')
  },
  output: {
    filename: '[name].min.js',
    path: path.resolve(__dirname, 'assets/js')
  },
  module: {
    rules: [
      {
        test: /\.js$/i,
        exclude: /node_modules/,
        use: []
      }
    ]
  },
  externals: {
    jquery: 'jQuery',
  },
  optimization: {
    minimize: process.env.NODE_ENV === 'production',
    minimizer: process.env.NODE_ENV === 'production' ? [new TerserPlugin()] : []
  }
};
