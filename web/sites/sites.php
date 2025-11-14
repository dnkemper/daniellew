<?php

// @codingStandardsIgnoreFile

/**
 * @file
 * Configuration file for multi-site support and directory aliasing feature.
 *
 * This file is required for multi-site support and also allows you to define a
 * set of aliases that map hostnames, ports, and pathnames to configuration
 * directories in the sites directory. These aliases are loaded prior to
 * scanning for directories, and they are exempt from the normal discovery
 * rules. See default.settings.php to view how Drupal discovers the
 * configuration directory when no alias is found.
 *
 * Aliases are useful on development servers, where the domain name may not be
 * the same as the domain of the live server. Since Drupal stores file paths in
 * the database (files, system table, etc.) this will ensure the paths are
 * correct when the site is deployed to a live server.
 *
 * To activate this feature, copy and rename it such that its path plus
 * filename is 'sites/sites.php'.
 *
 * Aliases are defined in an associative array named $sites. The array is
 * written in the format: '<port>.<domain>.<path>' => 'directory'. As an
 * example, to map https://www.drupal.org:8080/mysite/test to the configuration
 * directory sites/example.com, the array should be defined as:
 * @code
 * $sites = [
 *   '8080.www.drupal.org.mysite.test' => 'example.com',
 * ];
 * @endcode
 * The URL, https://www.drupal.org:8080/mysite/test/, could be a symbolic link
 * or an Apache Alias directive that points to the Drupal root containing
 * index.php. An alias could also be created for a subdomain. See the
 * @link https://www.drupal.org/documentation/install online Drupal installation guide @endlink
 * for more information on setting up domains, subdomains, and subdirectories.
 *
 * The following examples look for a site configuration in sites/example.com:
 * @code
 * URL: http://dev.drupal.org
 * $sites['dev.drupal.org'] = 'example.com';
 *
 * URL: http://localhost/example
 * $sites['localhost.example'] = 'example.com';
 *
 * URL: http://localhost:8080/example
 * $sites['8080.localhost.example'] = 'example.com';
 *
 * URL: https://www.drupal.org:8080/mysite/test/
 * $sites['8080.www.drupal.org.mysite.test'] = 'example.com';
 * @endcode
 *
 * @see default.settings.php
 * @see \Drupal\Core\DrupalKernel::getSitePath()
 * @see https://www.drupal.org/documentation/install/multi-site
 */

// Directory aliases for amcs.wustl.edu.
$sites['amcs.ddev.site'] = 'amcs.wustl.edu';
$sites['amcs.artscidev.wustl.edu'] = 'amcs.wustl.edu';
$sites['amcs.artscistage.wustl.edu'] = 'amcs.wustl.edu';
$sites['amcs.wustl.edu'] = 'amcs.wustl.edu';

// Directory aliases for afas.wustl.edu.
$sites['afas.ddev.site'] = 'afas.wustl.edu';
$sites['afas.artscidev.wustl.edu'] = 'afas.wustl.edu';
$sites['afas.artscistage.wustl.edu'] = 'afas.wustl.edu';
$sites['afas.wustl.edu'] = 'afas.wustl.edu';

// Directory aliases for anthropology.wustl.edu.
$sites['anthropology.ddev.site'] = 'anthropology.wustl.edu';
$sites['anthropology.artscidev.wustl.edu'] = 'anthropology.wustl.edu';
$sites['anthropology.artscistage.wustl.edu'] = 'anthropology.wustl.edu';
$sites['anthropology.wustl.edu'] = 'anthropology.wustl.edu';

// Directory aliases for arthistory.wustl.edu.
$sites['arthistory.ddev.site'] = 'arthistory.wustl.edu';
$sites['arthistory.artscidev.wustl.edu'] = 'arthistory.wustl.edu';
$sites['arthistory.artscistage.wustl.edu'] = 'arthistory.wustl.edu';
$sites['arthistory.wustl.edu'] = 'arthistory.wustl.edu';

// Directory aliases for artsciadvising.wustl.edu.
$sites['artsciadvising.ddev.site'] = 'artsciadvising.wustl.edu';
$sites['artsciadvising.artscidev.wustl.edu'] = 'artsciadvising.wustl.edu';
$sites['artsciadvising.artscistage.wustl.edu'] = 'artsciadvising.wustl.edu';
$sites['artsciadvising.wustl.edu'] = 'artsciadvising.wustl.edu';

// Directory aliases for artsciportal.wustl.edu.
$sites['artsciportal.ddev.site'] = 'artsciportal.wustl.edu';
$sites['artsciportal.artscidev.wustl.edu'] = 'artsciportal.wustl.edu';
$sites['artsciportal.artscistage.wustl.edu'] = 'artsciportal.wustl.edu';
$sites['artsciportal.wustl.edu'] = 'artsciportal.wustl.edu';

// Directory aliases for biology.wustl.edu.
$sites['biology.ddev.site'] = 'biology.wustl.edu';
$sites['biology.artscidev.wustl.edu'] = 'biology.wustl.edu';
$sites['biology.artscistage.wustl.edu'] = 'biology.wustl.edu';
$sites['biology.wustl.edu'] = 'biology.wustl.edu';

// Directory aliases for buildingartsci.wustl.edu.
$sites['buildingartsci.ddev.site'] = 'buildingartsci.wustl.edu';
$sites['buildingartsci.artscidev.wustl.edu'] = 'buildingartsci.wustl.edu';
$sites['buildingartsci.artscistage.wustl.edu'] = 'buildingartsci.wustl.edu';
$sites['buildingartsci.wustl.edu'] = 'buildingartsci.wustl.edu';

// Directory aliases for chemistry.wustl.edu.
$sites['chemistry.ddev.site'] = 'chemistry.wustl.edu';
$sites['chemistry.artscidev.wustl.edu'] = 'chemistry.wustl.edu';
$sites['chemistry.artscistage.wustl.edu'] = 'chemistry.wustl.edu';
$sites['chemistry.wustl.edu'] = 'chemistry.wustl.edu';

// Directory aliases for classics.wustl.edu.
$sites['classics.ddev.site'] = 'classics.wustl.edu';
$sites['classics.artscidev.wustl.edu'] = 'classics.wustl.edu';
$sites['classics.artscistage.wustl.edu'] = 'classics.wustl.edu';
$sites['classics.wustl.edu'] = 'classics.wustl.edu';

// Directory aliases for collegewriting.wustl.edu.
$sites['collegewriting.ddev.site'] = 'collegewriting.wustl.edu';
$sites['collegewriting.artscidev.wustl.edu'] = 'collegewriting.wustl.edu';
$sites['collegewriting.artscistage.wustl.edu'] = 'collegewriting.wustl.edu';
$sites['collegewriting.wustl.edu'] = 'collegewriting.wustl.edu';

// Directory aliases for complitandthought.wustl.edu.
$sites['complitandthought.ddev.site'] = 'complitandthought.wustl.edu';
$sites['complitandthought.artscidev.wustl.edu'] = 'complitandthought.wustl.edu';
$sites['complitandthought.artscistage.wustl.edu'] = 'complitandthought.wustl.edu';
$sites['complitandthought.wustl.edu'] = 'complitandthought.wustl.edu';

// Directory aliases for ealc.wustl.edu.
$sites['ealc.ddev.site'] = 'ealc.wustl.edu';
$sites['ealc.artscidev.wustl.edu'] = 'ealc.wustl.edu';
$sites['ealc.artscistage.wustl.edu'] = 'ealc.wustl.edu';
$sites['ealc.wustl.edu'] = 'ealc.wustl.edu';

// Directory aliases for economics.wustl.edu.
$sites['economics.ddev.site'] = 'economics.wustl.edu';
$sites['economics.artscidev.wustl.edu'] = 'economics.wustl.edu';
$sites['economics.artscistage.wustl.edu'] = 'economics.wustl.edu';
$sites['economics.wustl.edu'] = 'economics.wustl.edu';

// Directory aliases for education.wustl.edu.
$sites['education.ddev.site'] = 'education.wustl.edu';
$sites['education.artscidev.wustl.edu'] = 'education.wustl.edu';
$sites['education.artscistage.wustl.edu'] = 'education.wustl.edu';
$sites['education.wustl.edu'] = 'education.wustl.edu';


// Directory aliases for english.wustl.edu.
$sites['english.ddev.site'] = 'english.wustl.edu';
$sites['english.artscidev.wustl.edu'] = 'english.wustl.edu';
$sites['english.artscistage.wustl.edu'] = 'english.wustl.edu';
$sites['english.wustl.edu'] = 'english.wustl.edu';


// Directory aliases for enst.wustl.edu.
$sites['enst.ddev.site'] = 'enst.wustl.edu';
$sites['enst.artscidev.wustl.edu'] = 'enst.wustl.edu';
$sites['enst.artscistage.wustl.edu'] = 'enst.wustl.edu';
$sites['enst.wustl.edu'] = 'enst.wustl.edu';


// Directory aliases for eeps.wustl.edu.
$sites['eeps.ddev.site'] = 'eeps.wustl.edu';
$sites['eeps.artscidev.wustl.edu'] = 'eeps.wustl.edu';
$sites['eeps.artscistage.wustl.edu'] = 'eeps.wustl.edu';
$sites['eeps.wustl.edu'] = 'eeps.wustl.edu';


// Directory aliases for fms.wustl.edu.
$sites['fms.ddev.site'] = 'fms.wustl.edu';
$sites['fms.artscidev.wustl.edu'] = 'fms.wustl.edu';
$sites['fms.artscistage.wustl.edu'] = 'fms.wustl.edu';
$sites['fms.wustl.edu'] = 'fms.wustl.edu';

// Directory aliases for globalstudies.wustl.edu.
$sites['globalstudies.ddev.site'] = 'globalstudies.wustl.edu';
$sites['globalstudies.artscidev.wustl.edu'] = 'globalstudies.wustl.edu';
$sites['globalstudies.artscistage.wustl.edu'] = 'globalstudies.wustl.edu';
$sites['globalstudies.wustl.edu'] = 'globalstudies.wustl.edu';


// Directory aliases for gradstudies.artsci.wustl.edu.
$sites['gradstudies.ddev.site'] = 'gradstudies.artsci.wustl.edu';
$sites['gradstudies.artscidev.wustl.edu'] = 'gradstudies.artsci.wustl.edu';
$sites['gradstudies.artscistage.wustl.edu'] = 'gradstudies.artsci.wustl.edu';
$sites['gradstudies.artsci.wustl.edu'] = 'gradstudies.artsci.wustl.edu';


// Directory aliases for hdw.wustl.edu.
$sites['hdw.ddev.site'] = 'hdw.wustl.edu';
$sites['hdw.artscidev.wustl.edu'] = 'hdw.wustl.edu';
$sites['hdw.artscistage.wustl.edu'] = 'hdw.wustl.edu';
$sites['hdw.wustl.edu'] = 'hdw.wustl.edu';


// Directory aliases for history.wustl.edu.
$sites['history.ddev.site'] = 'history.wustl.edu';
$sites['history.artscidev.wustl.edu'] = 'history.wustl.edu';
$sites['history.artscistage.wustl.edu'] = 'history.wustl.edu';
$sites['history.wustl.edu'] = 'history.wustl.edu';


// Directory aliases for holdthatthought.wustl.edu.
$sites['holdthatthought.ddev.site'] = 'holdthatthought.wustl.edu';
$sites['holdthatthought.artscidev.wustl.edu'] = 'holdthatthought.wustl.edu';
$sites['holdthatthought.artscistage.wustl.edu'] = 'holdthatthought.wustl.edu';
$sites['holdthatthought.wustl.edu'] = 'holdthatthought.wustl.edu';


// Directory aliases for humanities.wustl.edu.
$sites['humanities.ddev.site'] = 'humanities.wustl.edu';
$sites['humanities.artscidev.wustl.edu'] = 'humanities.wustl.edu';
$sites['humanities.artscistage.wustl.edu'] = 'humanities.wustl.edu';
$sites['humanities.wustl.edu'] = 'humanities.wustl.edu';


// Directory aliases for insideartsci.wustl.edu.
$sites['insideartsci.ddev.site'] = 'insideartsci.wustl.edu';
$sites['insideartsci.artscidev.wustl.edu'] = 'insideartsci.wustl.edu';
$sites['insideartsci.artscistage.wustl.edu'] = 'insideartsci.wustl.edu';
$sites['insideartsci.wustl.edu'] = 'insideartsci.wustl.edu';


// Directory aliases for jimes.wustl.edu.
$sites['jimes.ddev.site'] = 'jimes.wustl.edu';
$sites['jimes.artscidev.wustl.edu'] = 'jimes.wustl.edu';
$sites['jimes.artscistage.wustl.edu'] = 'jimes.wustl.edu';
$sites['jimes.wustl.edu'] = 'jimes.wustl.edu';


// Directory aliases for johnmaxwulfing.wustl.edu.
$sites['johnmaxwulfing.ddev.site'] = 'johnmaxwulfing.wustl.edu';
$sites['johnmaxwulfing.artscidev.wustl.edu'] = 'johnmaxwulfing.wustl.edu';
$sites['johnmaxwulfing.artscistage.wustl.edu'] = 'johnmaxwulfing.wustl.edu';
$sites['johnmaxwulfing.wustl.edu'] = 'johnmaxwulfing.wustl.edu';

// Directory aliases for lasprogram.wustl.edu.
$sites['lasprogram.ddev.site'] = 'lasprogram.wustl.edu';
$sites['lasprogram.artscidev.wustl.edu'] = 'lasprogram.wustl.edu';
$sites['lasprogram.artscistage.wustl.edu'] = 'lasprogram.wustl.edu';
$sites['lasprogram.wustl.edu'] = 'lasprogram.wustl.edu';


// Directory aliases for linguistics.wustl.edu.
$sites['linguistics.ddev.site'] = 'linguistics.wustl.edu';
$sites['linguistics.artscidev.wustl.edu'] = 'linguistics.wustl.edu';
$sites['linguistics.artscistage.wustl.edu'] = 'linguistics.wustl.edu';
$sites['linguistics.wustl.edu'] = 'linguistics.wustl.edu';


// Directory aliases for literaryarts.wustl.edu.
$sites['literaryarts.ddev.site'] = 'literaryarts.wustl.edu';
$sites['literaryarts.artscidev.wustl.edu'] = 'literaryarts.wustl.edu';
$sites['literaryarts.artscistage.wustl.edu'] = 'literaryarts.wustl.edu';
$sites['literaryarts.wustl.edu'] = 'literaryarts.wustl.edu';


// Directory aliases for math.wustl.edu.
$sites['math.ddev.site'] = 'math.wustl.edu';
$sites['math.artscidev.wustl.edu'] = 'math.wustl.edu';
$sites['math.artscistage.wustl.edu'] = 'math.wustl.edu';
$sites['math.wustl.edu'] = 'math.wustl.edu';


// Directory aliases for mcss.wustl.edu.
$sites['mcss.ddev.site'] = 'mcss.wustl.edu';
$sites['mcss.artscidev.wustl.edu'] = 'mcss.wustl.edu';
$sites['mcss.artscistage.wustl.edu'] = 'mcss.wustl.edu';
$sites['mcss.wustl.edu'] = 'mcss.wustl.edu';


// Directory aliases for music.wustl.edu.
$sites['music.ddev.site'] = 'music.wustl.edu';
$sites['music.artscidev.wustl.edu'] = 'music.wustl.edu';
$sites['music.artscistage.wustl.edu'] = 'music.wustl.edu';
$sites['music.wustl.edu'] = 'music.wustl.edu';


// Directory aliases for overseas.wustl.edu.
$sites['overseas.ddev.site'] = 'overseas.wustl.edu';
$sites['overseas.artscidev.wustl.edu'] = 'overseas.wustl.edu';
$sites['overseas.artscistage.wustl.edu'] = 'overseas.wustl.edu';
$sites['overseas.wustl.edu'] = 'overseas.wustl.edu';


// Directory aliases for pad.wustl.edu.
$sites['pad.ddev.site'] = 'pad.wustl.edu';
$sites['pad.artscidev.wustl.edu'] = 'pad.wustl.edu';
$sites['pad.artscistage.wustl.edu'] = 'pad.wustl.edu';
$sites['pad.wustl.edu'] = 'pad.wustl.edu';

// Directory aliases for philosophy.wustl.edu.
$sites['philosophy.ddev.site'] = 'philosophy.wustl.edu';
$sites['philosophy.artscidev.wustl.edu'] = 'philosophy.wustl.edu';
$sites['philosophy.artscistage.wustl.edu'] = 'philosophy.wustl.edu';
$sites['philosophy.wustl.edu'] = 'philosophy.wustl.edu';


// Directory aliases for physics.wustl.edu.
$sites['physics.ddev.site'] = 'physics.wustl.edu';
$sites['physics.artscidev.wustl.edu'] = 'physics.wustl.edu';
$sites['physics.artscistage.wustl.edu'] = 'physics.wustl.edu';
$sites['physics.wustl.edu'] = 'physics.wustl.edu';


// Directory aliases for pnp.wustl.edu.
$sites['pnp.ddev.site'] = 'pnp.wustl.edu';
$sites['pnp.artscidev.wustl.edu'] = 'pnp.wustl.edu';
$sites['pnp.artscistage.wustl.edu'] = 'pnp.wustl.edu';
$sites['pnp.wustl.edu'] = 'pnp.wustl.edu';


// Directory aliases for polisci.wustl.edu.
$sites['polisci.ddev.site'] = 'polisci.wustl.edu';
$sites['polisci.artscidev.wustl.edu'] = 'polisci.wustl.edu';
$sites['polisci.artscistage.wustl.edu'] = 'polisci.wustl.edu';
$sites['polisci.wustl.edu'] = 'polisci.wustl.edu';


// Directory aliases for postbaccpremed.wustl.edu.
$sites['postbaccpremed.ddev.site'] = 'postbaccpremed.wustl.edu';
$sites['postbaccpremed.artscidev.wustl.edu'] = 'postbaccpremed.wustl.edu';
$sites['postbaccpremed.artscistage.wustl.edu'] = 'postbaccpremed.wustl.edu';
$sites['postbaccpremed.wustl.edu'] = 'postbaccpremed.wustl.edu';


// Directory aliases for precollege.wustl.edu.
$sites['precollege.ddev.site'] = 'precollege.wustl.edu';
$sites['precollege.artscidev.wustl.edu'] = 'precollege.wustl.edu';
$sites['precollege.artscistage.wustl.edu'] = 'precollege.wustl.edu';
$sites['precollege.wustl.edu'] = 'precollege.wustl.edu';

// Directory aliases for precollege.wustl.edu.
$sites['prehealth.ddev.site'] = 'prehealth.wustl.edu';
$sites['prehealth.artscidev.wustl.edu'] = 'prehealth.wustl.edu';
$sites['prehealth.artscistage.wustl.edu'] = 'prehealth.wustl.edu';
$sites['prehealth.wustl.edu'] = 'prehealth.wustl.edu';

// Directory aliases for psych.wustl.edu.
$sites['psych.ddev.site'] = 'psych.wustl.edu';
$sites['psych.artscidev.wustl.edu'] = 'psych.wustl.edu';
$sites['psych.artscistage.wustl.edu'] = 'psych.wustl.edu';
$sites['psych.wustl.edu'] = 'psych.wustl.edu';


// Directory aliases for quantumleaps.wustl.edu.
$sites['quantumleaps.ddev.site'] = 'quantumleaps.wustl.edu';
$sites['quantumleaps.artscidev.wustl.edu'] = 'quantumleaps.wustl.edu';
$sites['quantumleaps.artscistage.wustl.edu'] = 'quantumleaps.wustl.edu';
$sites['quantumleaps.wustl.edu'] = 'quantumleaps.wustl.edu';


// Directory aliases for religiousstudies.wustl.edu.
$sites['religiousstudies.ddev.site'] = 'religiousstudies.wustl.edu';
$sites['religiousstudies.artscidev.wustl.edu'] = 'religiousstudies.wustl.edu';
$sites['religiousstudies.artscistage.wustl.edu'] = 'religiousstudies.wustl.edu';
$sites['religiousstudies.wustl.edu'] = 'religiousstudies.wustl.edu';


// Directory aliases for rll.wustl.edu.
$sites['rll.ddev.site'] = 'rll.wustl.edu';
$sites['rll.artscidev.wustl.edu'] = 'rll.wustl.edu';
$sites['rll.artscistage.wustl.edu'] = 'rll.wustl.edu';
$sites['rll.wustl.edu'] = 'rll.wustl.edu';


// Directory aliases for sds.wustl.edu.
$sites['sds.ddev.site'] = 'sds.wustl.edu';
$sites['sds.artscidev.wustl.edu'] = 'sds.wustl.edu';
$sites['sds.artscistage.wustl.edu'] = 'sds.wustl.edu';
$sites['sds.wustl.edu'] = 'sds.wustl.edu';


// Directory aliases for slavery.wustl.edu.
$sites['slavery.ddev.site'] = 'slavery.wustl.edu';
$sites['slavery.artscidev.wustl.edu'] = 'slavery.wustl.edu';
$sites['slavery.artscistage.wustl.edu'] = 'slavery.wustl.edu';
$sites['slavery.wustl.edu'] = 'slavery.wustl.edu';


// Directory aliases for sociology.wustl.edu.
$sites['sociology.ddev.site'] = 'sociology.wustl.edu';
$sites['sociology.artscidev.wustl.edu'] = 'sociology.wustl.edu';
$sites['sociology.artscistage.wustl.edu'] = 'sociology.wustl.edu';
$sites['sociology.wustl.edu'] = 'sociology.wustl.edu';


// Directory aliases for strategicplan.artsci.wustl.edu.
$sites['strategicplan.ddev.site'] = 'strategicplan.artsci.wustl.edu';
$sites['strategicplan.artscidev.wustl.edu'] = 'strategicplan.artsci.wustl.edu';
$sites['strategicplan.artscistage.wustl.edu'] = 'strategicplan.artsci.wustl.edu';
$sites['strategicplan.artsci.wustl.edu'] = 'strategicplan.artsci.wustl.edu';


// Directory aliases for summersession.wustl.edu.
$sites['summersession.ddev.site'] = 'summersession.wustl.edu';
$sites['summersession.artscidev.wustl.edu'] = 'summersession.wustl.edu';
$sites['summersession.artscistage.wustl.edu'] = 'summersession.wustl.edu';
$sites['summersession.wustl.edu'] = 'summersession.wustl.edu';


// Directory aliases for transdisciplinaryfutures.wustl.edu.
$sites['transdisciplinaryfutures.ddev.site'] = 'transdisciplinaryfutures.wustl.edu';
$sites['transdisciplinaryfutures.artscidev.wustl.edu'] = 'transdisciplinaryfutures.wustl.edu';
$sites['transdisciplinaryfutures.artscistage.wustl.edu'] = 'transdisciplinaryfutures.wustl.edu';
$sites['transdisciplinaryfutures.wustl.edu'] = 'transdisciplinaryfutures.wustl.edu';


// Directory aliases for triads.wustl.edu.
$sites['triads.ddev.site'] = 'triads.wustl.edu';
$sites['triads.artscidev.wustl.edu'] = 'triads.wustl.edu';
$sites['triads.artscistage.wustl.edu'] = 'triads.wustl.edu';
$sites['triads.wustl.edu'] = 'triads.wustl.edu';


// Directory aliases for undergradresearch.wustl.edu.
$sites['undergradresearch.ddev.site'] = 'undergradresearch.wustl.edu';
$sites['undergradresearch.artscidev.wustl.edu'] = 'undergradresearch.wustl.edu';
$sites['undergradresearch.artscistage.wustl.edu'] = 'undergradresearch.wustl.edu';
$sites['undergradresearch.wustl.edu'] = 'undergradresearch.wustl.edu';

// Directory aliases for wc.wustl.edu.
$sites['wc.ddev.site'] = 'wc.wustl.edu';
$sites['wc.artscidev.wustl.edu'] = 'wc.wustl.edu';
$sites['wc.artscistage.wustl.edu'] = 'wc.wustl.edu';
$sites['wc.wustl.edu'] = 'wc.wustl.edu';


// Directory aliases for wgss.wustl.edu.
$sites['wgss.ddev.site'] = 'wgss.wustl.edu';
$sites['wgss.artscidev.wustl.edu'] = 'wgss.wustl.edu';
$sites['wgss.artscistage.wustl.edu'] = 'wgss.wustl.edu';
$sites['wgss.wustl.edu'] = 'wgss.wustl.edu';


// Directory aliases for olympian.wustl.edu.
$sites['olympian.ddev.site'] = 'olympian.wustl.edu';
$sites['olympian.artscidev.wustl.edu'] = 'olympian.wustl.edu';
$sites['olympian.artscistage.wustl.edu'] = 'olympian.wustl.edu';
$sites['olympian.wustl.edu'] = 'olympian.wustl.edu';

// Directory aliases for movingstories.wustl.edu.
$sites['movingstories.ddev.site'] = 'movingstories.wustl.edu';
$sites['movingstories.artscidev.wustl.edu'] = 'movingstories.wustl.edu';
$sites['movingstories.artscistage.wustl.edu'] = 'movingstories.wustl.edu';
$sites['movingstories.wustl.edu'] = 'movingstories.wustl.edu';

// Directory aliases for fellowshipsoffice.wustl.edu.
$sites['fellowshipsoffice.ddev.site'] = 'fellowshipsoffice.wustl.edu';
$sites['fellowshipsoffice.artscidev.wustl.edu'] = 'fellowshipsoffice.wustl.edu';
$sites['fellowshipsoffice.artscistage.wustl.edu'] = 'fellowshipsoffice.wustl.edu';
$sites['fellowshipsoffice.wustl.edu'] = 'fellowshipsoffice.wustl.edu';

// Directory aliases for aggregator.artsci.wustl.edu.
$sites['aggregator.ddev.site'] = 'aggregator.artsci.wustl.edu';
$sites['aggregator.artscidev.wustl.edu'] = 'aggregator.artsci.wustl.edu';
$sites['aggregator.artscistage.wustl.edu'] = 'aggregator.artsci.wustl.edu';
$sites['aggregator.artsci.wustl.edu'] = 'aggregator.artsci.wustl.edu';

// Directory aliases for publichealthandsociety.wustl.edu.
$sites['publichealthandsociety.ddev.site'] = 'publichealthandsociety.wustl.edu';
$sites['publichealthandsociety.artscidev.wustl.edu'] = 'publichealthandsociety.wustl.edu';
$sites['publichealthandsociety.artscistage.wustl.edu'] = 'publichealthandsociety.wustl.edu';
$sites['publichealthandsociety.wustl.edu'] = 'publichealthandsociety.wustl.edu';

// Directory aliases for mindfulness.wustl.edu.
$sites['mindfulness.ddev.site'] = 'mindfulness.wustl.edu';
$sites['mindfulness.artscidev.wustl.edu'] = 'mindfulness.wustl.edu';
$sites['mindfulness.artscistage.wustl.edu'] = 'mindfulness.wustl.edu';
$sites['mindfulness.wustl.edu'] = 'mindfulness.wustl.edu';

// Directory aliases for immersivetech.wustl.edu.
$sites['immersivetech.ddev.site'] = 'immersivetech.wustl.edu';
$sites['immersivetech.artscidev.wustl.edu'] = 'immersivetech.wustl.edu';
$sites['immersivetech.artscistage.wustl.edu'] = 'immersivetech.wustl.edu';
$sites['immersivetech.wustl.edu'] = 'immersivetech.wustl.edu';

// Directory aliases for literacies.wustl.edu.
$sites['literacies.ddev.site'] = 'literacies.wustl.edu';
$sites['literacies.artscidev.wustl.edu'] = 'literacies.wustl.edu';
$sites['literacies.artscistage.wustl.edu'] = 'literacies.wustl.edu';
$sites['literacies.wustl.edu'] = 'literacies.wustl.edu';

// Directory aliases for it.artsci.wustl.edu.
$sites['it.ddev.site'] = 'it.artsci.wustl.edu';
$sites['it.artscidev.wustl.edu'] = 'it.artsci.wustl.edu';
$sites['it.artscistage.wustl.edu'] = 'it.artsci.wustl.edu';
$sites['it.artsci.wustl.edu'] = 'it.artsci.wustl.edu';

// Directory aliases for collegewriting.wustl.edu.
$sites['collegewriting.ddev.site'] = 'collegewriting.wustl.edu';
$sites['collegewriting.artscidev.wustl.edu'] = 'collegewriting.wustl.edu';
$sites['collegewriting.artscistage.wustl.edu'] = 'collegewriting.wustl.edu';
$sites['collegewriting.wustl.edu'] = 'collegewriting.wustl.edu';

// Directory aliases for graduation.artsci.wustl.edu.
$sites['graduation.ddev.site'] = 'graduation.artsci.wustl.edu';
$sites['graduation.artscidev.wustl.edu'] = 'graduation.artsci.wustl.edu';
$sites['graduation.artscistage.wustl.edu'] = 'graduation.artsci.wustl.edu';
$sites['graduation.artsci.wustl.edu'] = 'graduation.artsci.wustl.edu';

// Directory aliases for migrate.wustl.edu.
$sites['migrate.ddev.site'] = 'migrate.wustl.edu';
$sites['migrate.artscidev.wustl.edu'] = 'migrate.wustl.edu';
$sites['migrate.artscistage.wustl.edu'] = 'migrate.wustl.edu';
$sites['migrate.wustl.edu'] = 'migrate.wustl.edu';

// Directory aliases for rev.wustl.edu.
$sites['rev.ddev.site'] = 'rev.wustl.edu';
$sites['rev.artscidev.wustl.edu'] = 'rev.wustl.edu';
$sites['rev.artscistage.wustl.edu'] = 'rev.wustl.edu';
$sites['rev.wustl.edu'] = 'rev.wustl.edu';

// Directory aliases for forms.artsci.wustl.edu.
$sites['forms.ddev.site'] = 'forms.artsci.wustl.edu';
$sites['forms.artscidev.wustl.edu'] = 'forms.artsci.wustl.edu';
$sites['forms.artscistage.wustl.edu'] = 'forms.artsci.wustl.edu';
$sites['forms.artsci.wustl.edu'] = 'forms.artsci.wustl.edu';
