const mix = require('laravel-mix');

mix.js('resources/js/app.js', 'public/js')
    .vue({ version: 2 })
    .sass('resources/sass/app.scss', 'public/css', { sassOptions: { quietDeps: true } })
    .sass('resources/sass/customer.scss', 'public/css', { sassOptions: { quietDeps: true } })
    .options({ processCssUrls: false })
    .version();
