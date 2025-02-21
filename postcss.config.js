module.exports = {
  plugins: {
    'postcss-import': {},
    'postcss-url': {
      filter: 'node_modules/**/*',
      url: 'copy',
      assetsPath: '../dist',
      useHash: true
    },
    'autoprefixer': {},
    'cssnano': {}
  }
}
