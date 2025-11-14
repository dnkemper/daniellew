# Configuration Splits
There are a few prerequisites that you should read and understand before
working with config splits.

- https://www.drupal.org/project/config_split/issues/2885643#comment-12125863

## One rule to never break
**DO NOT** share theme-dependent configuration in the sync directory if your
site split has complete-split a custom theme. The shared configuration will be
deleted on export which will break when merging in new theme configuration. An
example of this would be a paragraph or media change.

## Site Split
### Weight: 70
The machine name of the split should be `site`and the directory should be `../config/sites/mysite.wustl.edu`
replacing`mysite.wustl.edu` with the multisite URL host.

To register a configuration split for a multisite, create the split locally in
the UI and export to the site split directory.
```
ddev drush config-split:export site
```

Deploy the code changes to each environment per the normal process and import
the configuration from the split manually.
```ddev drush @mysite.dev config:import --source ../config/sites/mysite.wustl.edu --partial```

## Config Ignore
### Weight: 100
This split should be used for configuration that site staff, editors, etc. can
change in production. Think of it as a config split with database storage. The
high weight means config entities in this split will take precedence on import.

Configuration that is ignored cannot be selectively enabled/disabled in
environment splits. Use Drupal's [configuration override system](https://www.drupal.org/docs/8/api/configuration-api/configuration-override-system) if you need to override configuration per environment.

## Best Practices
### Complete vs. Partial Split ?
You will need to decide whether to add your config items to the Complete list or Partial list sections. Following these practices makes it easier for another developer to see at a glance which configuration is new and which is overriding existing configuration.

#### Complete Split list
Any configuration that is completely unique and not duplicated in the sync configuration or another split. This would include custom content types, custom vocabularies, and custom fields added to existing content types.

You can complete-split individual modules that your site needs and Config
Split will enable them.

#### Partial Split List
Configuration that is overriding existing settings, content types, etc. This would include `user.role.*.yml`, re-ordering of fields in the entity display, or the entity form.

### How to split a custom content type
A custom content type consists of several types of interrelated configuration: `node.type.*.yml`, `field.storage.*.*.yml`, `field.field.*.*.*.yml`, `core.entity_form_display.*.*.yml`, and `core.entity_view_display.*.*.yml` at a minimum. The rules of configuration dependencies mean that if you add some of these items, the others will be inferred from that. After you set up your content type, it is a good idea to run `ddev drush cst` to see a list of the config items that are new or have changed.
* Add the `node.type.*.yml` to the config split first. After that, run `ddev drush config-split:export site`. You will notice that many config files get exported that were not added to the split.
* Run `ddev drush cst` again to see what additional config elements need to be added.
