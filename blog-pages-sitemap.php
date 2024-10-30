<?php
/*
Plugin Name: Blog Pages Sitemap
Description: Create sitemap for blog pages with cron schedule
Author: Evgeniy Kozenok
Version: 0.0.2
*/

use PageSitemap\AdminSettings;
use PageSitemap\PagesSitemapCronSchedule;

include_once __DIR__ . '/vendor/autoload.php';

if (!defined('PAGES_SITEMAP_INDEX_FILE')) {
    define('PAGES_SITEMAP_INDEX_FILE', __FILE__);
}

new PagesSitemapCronSchedule();

AdminSettings::start();