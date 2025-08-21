# Pantheon Content Publisher for Drupal

The Pantheon Content Publisher module allows you to integrate content created in Google Docs to your Drupal website.

## Requirements

-  A Google Workspace Account
-  Drupal 10+ site with PHP 8.2+
-  For sites hosted on Pantheon, enable [Pantheon Search](https://docs.pantheon.io/solr) and configure the search version in your `pantheon.yml`. Follow the instructions [here](https://docs.pantheon.io/guides/solr-drupal/solr-drupal#enable-at-the-site-level)
-  PCC CLI (Pantheon Content Publisher Command Line Tool). Instructions to install the CLI are [available here](https://pcc.pantheon.io/pcc-cli-setup).

## Installation

To install this module via Composer, run the following command
```
composer require drupal/pantheon_content_publisher:"^1.0"

```
The module requires a backend Search API plugin. For sites hosted on Pantheon, it is recommended to use the Search API Pantheon module.
To install Search API Pantheon Module, run the following command
```
composer require drupal/search_api_pantheon:^8

```
After installing the Content Publisher and Search API Pantheon modules locally with Composer, push the `composer.json` and `composer.lock` files to your Pantheon environment.
Refer the documentation for more information about installation and configuration of module [pcc.pantheon.io/pantheon-content-publisher-for-drupal](https://pcc.pantheon.io/pantheon-content-publisher-for-drupal)

## Feedback and Collaboration

Bug reports and feature requests should be posted in the Github repository. For code changes, please submit pull requests against the GitHub repository rather than posting pull requests or patches to drupal.org.

## Documentation

Documentation is available at [pcc.pantheon.io/pantheon-content-publisher-for-drupal](https://pcc.pantheon.io/pantheon-content-publisher-for-drupal)

