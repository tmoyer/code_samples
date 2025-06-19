/**
 * @file
 Gulpfile for UMD Prepare Theme.
 */

let gulp = require('gulp'),
  sass = require('gulp-sass')(require('node-sass')),
  sassGlob = require('gulp-sass-glob'),
  del = require('del'),
  sourcemaps = require('gulp-sourcemaps'),
  postcss = require('gulp-postcss'),
  autoprefixer = require('autoprefixer');

const paths = {
  scss: {
    src: [
      './src/scss/*.scss'
    ],
    dest: './dist/css',
    watch: './src/scss/**/*.scss'
  },
  js: {
    src: "./src/js/**/*.js",
    dest: "./dist/js",
  },
  vendors: {
    bootstrap: "./node_modules/bootstrap/",
    fontAwesome: "./node_modules/@fortawesome/fontawesome-free/",
    popperjs: "./node_modules/@popperjs/core/",
    featherIcons: "./node_modules/feather-icons/",
    destJs: "./dist/js/third-parties",
    destCss: "./dist/css/third-parties",
    destFonts: "./dist/assets/fonts/third-parties",
    destBootstrapGrid: "./dist/vendor/bootstrap/layout-admin",
    slickCarousel: "./node_modules/slick-carousel/",
    chosen: "./node_modules/chosen-js/"
  },
};

// Compile sass into CSS.
async function styles(exitOnError) {
  await gulp.src(paths.scss.src)
    .pipe(sourcemaps.init())
    .pipe(sassGlob())
    .pipe(sass({
      includePaths: ['node_modules/'],
      includePaths: ['node_modules/'],
    }).on('error', function (error) {
      if (typeof exitOnError === 'undefined' || exitOnError === true) {
        sass.logError(error);
      }
      else {
        sass.logError.bind(this)(error);
      }
    }))
    .pipe(postcss([autoprefixer({
      browsers: [
        'Chrome >= 35',
        'Firefox >= 38',
        'Edge >= 12',
        'iOS >= 8',
        'Safari >= 8',
        'Android 2.3',
        'Android >= 4',
        'Opera >= 12']
    })]))
    .pipe(sourcemaps.write())
    .pipe(gulp.dest(paths.scss.dest))
}

/*
 * lintThis()
 * Lint all the script.
 */
const lintScript = () =>
  src(paths.js.src)
    .pipe(gulpESLintNew())
    .pipe(gulpESLintNew.format());


const compileVendors = (done) => {
  const vendorPaths = [
    {
      src: `${paths.vendors.bootstrap}dist/js/bootstrap.min.js`,
      dest: `${paths.vendors.destJs}/bootstrap`,
    },
    {
      src: `${paths.vendors.bootstrap}dist/js/bootstrap.min.js.map`,
      dest: `${paths.vendors.destJs}/bootstrap`,
    },
    {
      src: `${paths.vendors.bootstrap}dist/css/bootstrap-grid.min.css`,
      dest: paths.vendors.destBootstrapGrid,
    },
    {
      src: `${paths.vendors.bootstrap}dist/css/bootstrap-grid.css.map`,
      dest: paths.vendors.destBootstrapGrid,
    },

    // Add more libraries below.
    {
      src: `${paths.vendors.popperjs}dist/umd/popper.min.js`,
      dest: `${paths.vendors.destJs}/popper`,
    },
    {
      src: `${paths.vendors.popperjs}dist/umd/popper.min.js.map`,
      dest: `${paths.vendors.destJs}/popper`,
    },
    {
      src: `${paths.vendors.fontAwesome}webfonts/**/*`,
      dest: `${paths.vendors.destFonts}/webfonts`,
    },
    {
      src: `${paths.vendors.featherIcons}dist/feather.min.js`,
      dest: `${paths.vendors.destFonts}/featherIcons`,
    },
    {
      src: `${paths.vendors.featherIcons}dist/feather.min.js.map`,
      dest: `${paths.vendors.destFonts}/featherIcons`,
    },
    {
      src: `${paths.vendors.featherIcons}dist/icons/**/*`,
      dest: `${paths.vendors.destFonts}/featherIcons/icons`,
    },
    {
      src: `${paths.vendors.slickCarousel}slick/slick.min.js`,
      dest: `${paths.vendors.destJs}/slick/`,
    },
    {
      src: `${paths.vendors.slickCarousel}slick/fonts/**/*`,
      dest: `${paths.vendors.destJs}/slick/fonts/`,
    },
    {
      src: `${paths.vendors.slickCarousel}slick/slick.css`,
      dest: `${paths.vendors.destJs}/slick/`,
    },
    {
      src: `${paths.vendors.slickCarousel}slick/slick-theme.css`,
      dest: `${paths.vendors.destJs}/slick/`,
    },
    {
      src: `${paths.vendors.slickCarousel}slick/ajax-loader.gif`,
      dest: `${paths.vendors.destJs}/slick/`,
    },
    {
      src: `${paths.vendors.chosen}chosen.jquery.min.js`,
      dest: `${paths.vendors.destJs}/chosen/`,
    },
    {
      src: `${paths.vendors.chosen}chosen.min.css`,
      dest: `${paths.vendors.destCss}/chosen/`,
    },
    {
      src: `${paths.vendors.chosen}chosen-sprite.png`,
      dest: `${paths.vendors.destCss}/chosen/`,
    },
    {
      src: `${paths.vendors.chosen}chosen-sprite@2x.png`,
      dest: `${paths.vendors.destCss}/chosen/`,
    },
  ];
  const tasks = vendorPaths.map((path) => src(path.src).pipe(dest(path.dest)));
  merge(tasks);
  done();
};

/*
 * compileScript()
 * Compiles all the scripts into a minified file.
 */
const compileScript = () =>
  src(paths.js.src) // source directory
    .pipe(terser())
    .pipe(dest(paths.js.dest)); // final/public directory

// Static Server + watching scss/html files.
async function serve() {
    // Watch for scss changes.
    gulp.watch([paths.scss.watch], styles);
}

async function clean() {
  await
    del(['./dist']);
}

const build = gulp.series(clean, function() { return styles(false) }, gulp.parallel(serve), script, compileVendors);

exports.styles = styles;
exports.serve = serve;
exports.script = compileScript;
exports.vendors = compileVendors;

exports.default = build;

// For the CI build, don't need the browser sync stuff.
const deploy = gulp.series(clean, function() { return styles(true) });
exports.deploy = deploy;
