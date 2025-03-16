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
}).js('source/preview.js', '.');
