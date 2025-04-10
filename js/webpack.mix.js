const mix = require('laravel-mix');

mix.options({
  manifest: false,
})
.webpackConfig({
  resolve: {
    fallback: {
      fs: false,
      child_process: false
    }
  }
})
.babelConfig({
  presets: [
    ['@babel/preset-env', {
      targets: {
        esmodules: true
      },
      exclude: ['@babel/plugin-transform-async-to-generator']
    }]
  ]
})
.js('source/preview.js', '.');
