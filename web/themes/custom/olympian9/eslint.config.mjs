/*
To update the packages and settings for the latest `eslint`,
this command was run: `yarn create @eslint/config`
(see https://eslint.org/docs/latest/use/getting-started), and it created this file.
Log follows:

  >  % yarn create @eslint/config
  >  yarn create v1.22.22
  >  [1/4] 🔍  Resolving packages...
  >  [2/4] 🚚  Fetching packages...
  >  [3/4] 🔗  Linking dependencies...
  >  [4/4] 🔨  Building fresh packages...
  >  success Installed "@eslint/create-config@1.3.1" with binaries:
  >        - create-config
  >  [############] 12/12@eslint/create-config: v1.3.1
  >
  >  ✔ How would you like to use ESLint? · problems
  >  ✔ What type of modules does your project use? · script
  >  ✔ Which framework does your project use? · none
  >  ✔ Does your project use TypeScript? · typescript
  >  ✔ Where does your code run? · browser
  >  The config that you've selected requires the following dependencies:
  >
  >  eslint, globals, @eslint/js, typescript-eslint
  >  ✔ Would you like to install them now? · No / Yes
  >  ✔ Which package manager do you want to use? · yarn
  >  ☕️Installing...
  >  yarn add v1.22.22
  >  [1/4] 🔍  Resolving packages...
  >  [2/4] 🚚  Fetching packages...
  >  [3/4] 🔗  Linking dependencies...
  >  warning "typescript-eslint > @typescript-eslint/eslint-plugin > ts-api-utils@1.3.0" has unmet peer dependency "typescript@>=4.2.0".
  >  [4/4] 🔨  Building fresh packages...
  >  success Saved lockfile.
  >  success Saved 35 new dependencies.

Then added an `ignores` section for skipping 3rd-party js files.
Added what was in .eslintignore to the ignores list.
*/
import globals from "globals";
import pluginJs from "@eslint/js";
import tseslint from "typescript-eslint";


export default [
  {files: ["**/*.{js,mjs,cjs,ts}"]},
  {files: ["**/*.js"], languageOptions: {sourceType: "script"}},
  {languageOptions: { globals: globals.browser }},
  {ignores: [
    "js/libs/*.js",  // 3rd-party
    "node_modules/*",
    "**/node_modules/*",
    "dist/*",
  ]},
  pluginJs.configs.recommended,
  ...tseslint.configs.recommended,
];
