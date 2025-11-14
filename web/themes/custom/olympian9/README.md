# Olympian9 Theme

## The stylesheets are compiled using yarn

Make sure you have `yarn` installed (go to the olympian9 theme directory and run `yarn install`).

## Debug - PHPCS

To debug potential template or stylesheet errors, run:

```bash
./vendor/bin/phpcs --standard=Drupal --extensions=php,module,theme,info,twig,yml,scss web/themes/custom/olympian9
```

## Working with the olympian9 theme

To start working on the `sass` and `js` for the theme, open up a new terminal:

```bash
ddev watch
```

Or (outside of `ddev`):
Make sure you have the necessary packages installed (the `ddev watch` also does this):

```bash
cd ../deps/web/themes/custom/olympian9
yarn
```

And then run the watch:

```bash
yarn run watch
```

The watch will run a build and then watch for changes in `*.scss` and `*.js` source files (and also the svg and cypress files) , so you may want to open a separate terminal for it.
A build involves processing for the `*.scss` files (running prettier, compiling with sass, running the autoprefixer and minifier), processing for the `*.js` files (running babel to do any necessary transpiling, and also to minify), and processing for the `*.svg` files (creating a sprite).

Other commands (outside of `ddev`):

- `yarn run cypresstests` - runs Cypress tests
- `yarn run build` - runs a build only without starting a watch for file changes

Please make sure your IDE (e.g., VSCode or PHPStorm, etc) has plugins installed for these (the links here are for VSCode):

- [StyleLint](https://marketplace.visualstudio.com/items?itemName=stylelint.vscode-stylelint) - For showing lint warnings for SASS code in the IDE.
- [ESLint](https://marketplace.visualstudio.com/items?itemName=dbaeumer.vscode-eslint) - For showing lint warnings for JavaScript code in the IDE.
- [EditorConfig](https://marketplace.visualstudio.com/items?itemName=EditorConfig.EditorConfig) - For applying editor conventions (like tabs, etc).
- [Prettier](https://marketplace.visualstudio.com/items?itemName=esbenp.prettier-vscode) - This is a little opinionated, but it's great for not worrying about formatting your code, since it will line things up whenever the file is saved. Have this set to run on save, but only for scss files. (You don't want it to run on the config yaml files, because the format is different from what Drupal spits out.)  In VSCode, set these in the settings.json file (`View->Command Palette->Preferences: Open User Settings (JSON)`):

    ```json
    "editor.formatOnSave": false,
    "[scss]": {
      "editor.formatOnSave": true,
      "editor.defaultFormatter": "esbenp.prettier-vscode"
    },
    ```

## Working with the olympian_core module

Similar to working with the olympian9 theme:

To start working on the `sass` and `js` for the module, open up a new terminal:

```bash
ddev watch-module
```

or (outside of `ddev`):

```bash
cd ../deps/web/modules/custom/olympian_core
yarn
yarn run watch
```

Other commands (outside of `ddev`):

- `yarn run lint` - runs eslint
- `yarn run build` - runs a build only without starting a watch for file changes
