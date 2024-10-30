<?php

namespace PageSitemap;

use WPSEO_Meta;

class PagesSitemapCronSchedule
{
    private $siteUrl;
    private $optionName = 'c4601a0c4a53fc695582736ba26c6e84'; // md5('preparePagesSitemap')

    public function __construct()
    {
        $this->initSiteUrl();

        add_action(AdminSettings::getHookName(), [$this, 'preparePagesSitemapHandler']);

        $interval = get_option(AdminSettings::getPageSettingOptionName())[AdminSettings::getIntervalOption()] ?? 86400;
        if (!$interval) {
            $interval = 86400;
        }
        add_filter('cron_schedules', function ($schedules) use ($interval) {
            $schedules['sitemapInterval'] = [
                'interval' => (int) $interval,
                'display' => 'Sitemap Interval'
            ];
            return $schedules;
        });

        if (!wp_next_scheduled(AdminSettings::getHookName())) {
            wp_schedule_event(time(), 'sitemapInterval', AdminSettings::getHookName());
        }
    }

    public function preparePagesSitemapHandler()
    {
        global $wpdb;
        global $sitepress;
        if (!$sitepress) {
            wp_die();
        }

        $languages = $sitepress->get_active_languages();
        if (!$languages) {
            wp_die();
        }

        add_filter('home_url', function ($url) {
            if (strpos($url, $this->siteUrl) !== false) {
                return $url;
            }
            return str_replace(site_url() . '/', $this->siteUrl, $url);
        }, 100, 1);

        add_filter('wpml_url_converter_get_abs_home', function () {
            return $this->siteUrl;
        });

        $resultArr = [];

        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';

        $sqlQueryForGetPosts = "
        SELECT
            DISTINCT p.ID, p.post_modified
        FROM wp_posts p
        LEFT JOIN wp_postmeta m 
            ON (p.ID = m.post_id)
        LEFT JOIN wp_icl_translations tr 
            ON (p.ID = tr.element_id)
        WHERE p.post_type ='page' 
            AND  p.post_status ='publish'  
            AND tr.language_code IN ('" . implode("', '", array_keys($languages)) . "')
        ";
        $allPosts = $wpdb->get_results($sqlQueryForGetPosts);

        foreach ($allPosts as $post) {
            $link = get_permalink($post->ID);
            $checkLink = explode($this->siteUrl, $link);
            if (count($checkLink) == 2) {
                $link = $checkLink[1];
            }
            $info = wpml_get_language_information('', $post->ID);

            if ($info['language_code'] != 'en') {
                $checkLink = explode($info['language_code'] . '/', $link);
                if (count($checkLink) == 2 && !empty($checkLink[1]) && !empty($checkLink[0])) {
                    $link = $checkLink[1];
                }
            }
            $resultArr[$link] = [];
        }

        foreach ($allPosts as $post) {
            $id = $post->ID;

            $noindex = WPSEO_Meta::get_value('meta-robots-noindex', $id);
            if ($noindex) {
                continue;
            }

            $link = get_permalink($id);
            $links = $link;
            $checkLink = explode($this->siteUrl, $link);
            $myPostLanguageDetails = apply_filters('wpml_post_language_details', NULL, $id);
            $lang = $myPostLanguageDetails['language_code'] ?? '';

            if (count(explode('.html', $link)) == 1) {
                if (!function_exists('getOrderLanguages')) {
                    $cleverFilesSiteLangOrder = array_keys(getOrderLanguages());
                } else {
                    $cleverFilesSiteLangOrder = array_keys($languages);
                }

                $resultArr[$link] = [];

                foreach ($cleverFilesSiteLangOrder as $lags) {
                    $tmp = ['id' => $id, 'post_modified' => $post->post_modified];
                    if ($lags == 'en') {
                        $tmp['url'] = $this->siteUrl;
                    } else {
                        $tmp['url'] = $this->siteUrl . $lags;
                    }
                    $resultArr[$link] = array_merge($resultArr[$link], [$lags => $tmp]);
                }
            } else {
                if (count($checkLink) == 2) {
                    $link = $checkLink[1];
                }
                if ($lang != 'en') {
                    $checkLink = explode($lang . '/', $link);
                    if (count($checkLink) == 2) {
                        $link = $checkLink[1];
                    }
                }
                $wpmlPermalink = apply_filters('wpml_permalink', $links, $lang);
                $tmp = ['id' => $id, 'post_modified' => $post->post_modified, 'url' => $wpmlPermalink];
                $resultArr[$link] = array_merge($resultArr[$link], [$lang => $tmp]);
            }
        }

        foreach ($resultArr as $urls) {
            $sitemap .= $this->xmlParse($urls, $languages);
        }

        $sitemap .= "\n" . '</urlset>';

        $fileName =
            get_option(AdminSettings::getPageSettingOptionName())[AdminSettings::getFileNameOption()]
            ?? AdminSettings::getDefaultFileName();

        if (!$fileName) {
            $fileName = AdminSettings::getDefaultFileName();
        }
        $fp = fopen(get_home_path() . $fileName, 'w');

        fwrite($fp, $sitemap);
        fclose($fp);
    }

    private function xmlParse($urls, $languages)
    {
        $check = [];
        $sitemap = '';

        foreach ($languages as $item) {
            foreach ($urls as $lang => $url) {
                if (!in_array($lang, $check)) {
                    $arrs = $urls;
                    unset($arrs[$lang]);

                    $alternates = '';
                    foreach ($arrs as $lang2 => $url2) {
                        if ($lang2 == 'jp') {
                            $lang2 = 'ja';
                        }
                        $alternates .= "\n\t\t" . '<xhtml:link rel="alternate" hreflang="' . $lang2 . '" href="' . $url2['url'] . '" />';
                    }

                    $sitemap .= $this->getPageSitemapLayout(
                        $url['url'],
                        date('c', strtotime($url['post_modified'])),
                        $alternates
                    );
                    $check[] = $lang;
                }
            }

        }

        return $sitemap;
    }

    private function getPageSitemapLayout($url, $lastModify, $alternates)
    {
        return sprintf(
            '%1$s<url>%2$s<loc>%3$s</loc>%2$s<lastmod>%4$s</lastmod>%2$s<changefreq>weekly</changefreq>%2$s<priority>0.3</priority>%5$s%1$s</url>',
            "\n\t",
            "\n\t\t",
            $url,
            $lastModify,
            $alternates
        );
    }

    private function initSiteUrl()
    {
        $siteUrl = defined('WP_SITEURL') ? WP_SITEURL : site_url();
        if (substr($siteUrl, -1) != '/') {
            $siteUrl .= '/';
        }

        if (!in_array(str_replace('/', '', $siteUrl), ['http:', 'https:']) && $siteUrl != get_option($this->optionName)) {
            update_option($this->optionName, $siteUrl);
        }

        $this->siteUrl = get_option($this->optionName);
    }
}