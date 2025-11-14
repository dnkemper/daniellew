# Description
[Jira issue](https://washuartsci.atlassian.net/browse/<REPLACE_WITH_ISSUE_NUMBER>)

<PLEASE_REPLACE_WITH_YOUR_DESCRIPTION>

## Dev Guide
this will be deployed to sites listed in [sites.php](https://github.com/artsci-webteam/deps/blob/main/web/sites/sites.php) for artscidev.wustl.edu, artscistage.wustl.edu and artsciprod.wustl.edu servers. [How to deploy changes to server](https://github.com/artsci-webteam/deps/wiki/Server-Setup)

- [ ] I have ran ddev sync with latest code and it ran successfully
- [ ] I have made corresponding changes to the documentation

## Testing Information information

```bash
# Sync Department Sites Environment
ddev sync stage olympian
```
<details>
<summary>Settings.php site splits</summary>

```bash
$config['config_split.config_split.local']['status'] = TRUE;
```
</details>

- [ ] Navigate to [https://default.ddev.site/](https://default.ddev.site/)
- [ ] Test steps (use specific /paths when available)
