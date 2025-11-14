// ----- 2024-Aug-18
// Gulp is no longer being used.  Run scripts from package.json (using yarn) instead.
// All local gulp-related npm packages have been uninstalled.
// This file is here only for historical purposes; could be deleted sometime.
// -----
const gulp = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const typescript = require('gulp-typescript');
const prettier = require('gulp-prettier');
const cypress = require('cypress');
const { src, dest, series, watch } = require('gulp');
const del = require('del');
const glob = require('gulp-sass-glob');
const sourcemaps = require('gulp-sourcemaps');
const terser = require('gulp-terser');
const babel = require('gulp-babel');
const rename = require('gulp-rename');
const svgSprite = require('gulp-svg-sprite');

// Postcss and its plugins
const postcss = require('gulp-postcss');
const autoprefixer = require('autoprefixer');
const cssnano = require('cssnano');

const paths = {
  scss: {
    source: 'scss/**/*.scss',
    destination: 'assets/css',
  },
  ts: {
    source: 'ts/**/*.ts',
    destination: 'assets/ts',
  },
  svg: {
    source: 'svg/*.svg',
    destination: 'assets/svg',
  },
  images: {
    source: ['images/*.{png,jpg,jpeg}'],
    destination: 'assets/images',
  },
  javascript: {
    source: 'js/**/*.js',
    destination: 'assets/js',
  },
};

// Prettify task
function prettify() {
  return src([paths.scss.source])
    .pipe(prettier())
    .pipe(dest(file => file.base));
}

// Clean CSS files
function cleanCSSFiles() {
  return del([`${paths.scss.destination}/*.css`]);
}

// Compile SCSS files with sourcemaps and minify CSS
// NOTE: If Chrome devtools is showing weird sourcemap line numbers:  make sure cache is disabled(!!!) (see Network settings), then refresh.
function compileSCSS() {
  return src(paths.scss.source)
    .pipe(sourcemaps.init())
    .pipe(sass.sync()).on('error', sass.logError)
    .pipe(postcss([autoprefixer(), cssnano({ preset: 'default' })]))
    .pipe(sourcemaps.write('./'))
    .pipe(dest(paths.scss.destination));
}

// Compile TypeScript
function compileTypeScript() {
  return src(paths.ts.source)
    .pipe(typescript())
    .pipe(dest(paths.ts.destination));
}

// Minify JavaScript
function minifyJavaScript() {
  return src(paths.javascript.source)
    .pipe(sourcemaps.init())
    .pipe(babel({ presets: ['@babel/env'] }))
    .pipe(terser())
    .pipe(sourcemaps.write('.'))
    .pipe(dest(paths.javascript.destination));
}

// SVG Sprite task
function createSvgSprite() {
  return src('svg/*.svg')
    .pipe(svgSprite({
      mode: {
        symbol: {
          sprite: 'sprite.svg',
        },
      },
    }))
    .pipe(dest(paths.svg.destination));
}
// Testing with Cypress
function runCypress() {
  return cypress.run();
}
// Watch task for automatic processing
function watchFiles() {
  watch(paths.scss.source, series(cleanCSSFiles, compileSCSS));
  watch(paths.ts.source, compileTypeScript);
  watch(paths.javascript.source, minifyJavaScript);
  watch('cypress/**/*.js', runCypress);
}

// Define a clean task to remove CSS files once
gulp.task('clean', function () {
  return del([
    paths.scss.destination,
    paths.ts.destination,
    paths.javascript.destination,
  ]);
});

exports.build = series('clean', prettify, compileSCSS, compileTypeScript, minifyJavaScript, createSvgSprite);

// Define the default task with 'clean' as a dependency
exports.default = series('clean', prettify, compileSCSS, compileTypeScript, minifyJavaScript, createSvgSprite, watchFiles);
