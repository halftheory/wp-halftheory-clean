module.exports = {
  plugins: {
    'postcss-import': {},
    'postcss-url': [
      {
        filter: 'node_modules/**/*',
        url: 'copy',
        assetsPath: '../dist',
        useHash: true
      },
      {
        filter: 'assets/scss/**/*',
        url: 'copy',
        assetsPath: '../dist',
        useHash: true
      }
    ],
    'autoprefixer': {},
    'postcss-discard-comments': {
      removeAll: true,
    },
    'cssnano': {}
  }
}
