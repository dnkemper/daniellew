// ----- 2024-Aug-18
// Gulp is no longer being used.  Run scripts from package.json (using yarn) instead.
// All local gulp-related npm packages have been uninstalled.
// This file is here only for historical purposes; could be deleted sometime.
// -----
const gulp = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const watch = require('gulp-watch');
const plumber = require('gulp-plumber');
const sourcemaps = require('gulp-sourcemaps');

// Postcss and its plugins
const postcss = require('gulp-postcss');
const autoprefixer = require('autoprefixer');
const cssnano = require('cssnano');

function css() {
  return gulp
      .src('./scss/**/*.scss')
      .pipe(plumber(function (error) {
        console.log(error.message);
        this.emit('end');
      }))
      .pipe(sourcemaps.init())
      .pipe(sass.sync({
        outputStyle: "expanded"
      }))
      .pipe(postcss([
        autoprefixer(),
        cssnano({ preset: 'default' })
      ]))
      .pipe(sourcemaps.write())
      .pipe(gulp.dest('./css'));
}


// CSS task
// Watch files
function watchFiles() {
  watch('./scss/**/*.scss', { verbose:true }, css);
}


exports.build = gulp.series(css);
exports.watch = function() {
  gulp.watch('./scss/**/*.scss', css);
};

// By setting the default, we can simply run 'gulp' to start watching for changes.
exports.default = watchFiles;
