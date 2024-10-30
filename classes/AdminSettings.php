<?php

namespace PageSitemap;

class AdminSettings
{
    public static function getPageSlug()
    {
        return 'pages-sitemap-settings';
    }

    public static function getPageSettingId()
    {
        return 'pages_sitemap_settings';
    }

    public static function getPageSettingSlug()
    {
        return 'pages_sitemap_setting_page';
    }

    public static function getPageSettingOptionName()
    {
        return 'pages_sitemap_settings_options';
    }

    public static function getFileNameOption()
    {
        return 'pages_sitemap_option_file_name';
    }

    public static function getIntervalOption()
    {
        return 'pages_sitemap_interval';
    }

    public static function getRefreshNowOption()
    {
        return 'pages_sitemap_option_refresh_now';
    }

    public static function getDefaultFileName()
    {
        return 'sitemap-pages.xml';
    }

    public static function getHookName()
    {
        return 'preparePagesSitemap';
    }

    public static function start()
    {
        if (!is_admin()) {
            return false;
        }

        include_once(ABSPATH . 'wp-includes/pluggable.php');

        (new self())->handle();

        return true;
    }

    private function handle()
    {
        add_action('admin_menu', [$this, 'addAdminMenu']);

        add_action('admin_init', [$this, 'pluginSettings']);

        add_filter('plugin_action_links', [$this, 'pagesSitemapPluginActionLinks'], 10, 2);
    }

    public function addAdminMenu()
    {
        add_options_page('WP Pages Sitemap', 'WP Pages Sitemap', 'manage_options', self::getPageSlug(), [$this, 'optionsPageOutput']);
    }

    public function pagesSitemapPluginActionLinks($links, $file)
    {
        if (strpos($file, basename(PAGES_SITEMAP_INDEX_FILE)) === false) {
            return $links;
        }

        $pluginSettingsUrl = sprintf('options-general.php?page=%s&section=settings', self::getPageSlug());
        $pluginSettingsLink = sprintf('<a href="%s">%s</a>', admin_url($pluginSettingsUrl), __('Settings', 'Settings'));

        array_unshift($links, $pluginSettingsLink);

        return $links;
    }

    public function pluginSettings()
    {
        register_setting(self::getPageSettingId(), self::getPageSettingOptionName(), [$this, 'submitAction']);

        add_settings_section(self::getPageSettingId(), __('Base settings'), '', self::getPageSettingSlug());

        add_settings_field(
            self::getFileNameOption(),
            'Input file name<br/><span style="font-size: 10px; color: #f00">' . self::getDefaultFileName() . ' by default</span>',
            [$this, 'fillFileNameSettingField'],
            self::getPageSettingSlug(),
            self::getPageSettingId()
        );

        add_settings_field(
            self::getIntervalOption(),
            'Automatic Interval Refresh in sec<br/><span style="font-size: 10px; color: #f00">Daily by default (86400 s)</span>',
            [$this, 'fillIntervalSettingField'],
            self::getPageSettingSlug(),
            self::getPageSettingId()
        );

        add_settings_field(
            self::getRefreshNowOption(),
            'Refresh sitemap<br/><span style="font-size: 10px; color: #f00">after a minute</span>',
            [$this, 'refreshSitemapAction'],
            self::getPageSettingSlug(),
            self::getPageSettingId()
        );
    }


    public function optionsPageOutput()
    {
        ?>
        <div class="wrap">
            <h2><?php echo get_admin_page_title() ?></h2>

            <form method="POST" action="options.php">
                <?php
                settings_fields(self::getPageSettingId());
                do_settings_sections(self::getPageSettingSlug());
                submit_button(__('Save Changes'));
                ?>
            </form>
        </div>
        <?php
    }

    public function fillFileNameSettingField()
    {
        $fileNameOption = self::getFileNameOption();
        $value = get_option(self::getPageSettingOptionName());
        $value = $value[$fileNameOption] ?? null;
        $formOptionName = self::getPageSettingOptionName() . '[' . $fileNameOption . ']';

        ?>
        <label>
            <input type="text" name="<?php echo $formOptionName; ?>" value="<?php echo esc_attr($value) ?>"
                   placeholder="<?php echo self::getDefaultFileName(); ?>"/>
        </label>
        <?php
    }

    public function fillIntervalSettingField()
    {
        $fileNameOption = self::getIntervalOption();
        $value = get_option(self::getPageSettingOptionName());
        $value = $value[$fileNameOption] ?? null;
        $formOptionName = self::getPageSettingOptionName() . '[' . $fileNameOption . ']';

        ?>
        <label>
            <input type="text" name="<?php echo $formOptionName; ?>" value="<?php echo esc_attr($value) ?>"
                   placeholder="86400"/>
        </label>
        <?php
    }

    public function submitAction($options)
    {
        foreach ($options as &$val) {
            $val = strip_tags($val);
        }

        $refreshNowRequired = $options[self::getRefreshNowOption()] ?? null;

        if ($refreshNowRequired) {
            wp_schedule_single_event(time() + 60, self::getHookName());
        }
        $options[self::getRefreshNowOption()] = '';

        $interval = $options[self::getIntervalOption()] ?? null;
        if ($interval) {
            $interval = (int) $interval;
            if ($interval != get_option(self::getPageSettingOptionName())[self::getIntervalOption()] ?? null) {
                wp_unschedule_event(wp_next_scheduled(self::getHookName()), self::getHookName());
            }
        } elseif (get_option(self::getPageSettingOptionName())[self::getIntervalOption()] ?? null) {
            wp_unschedule_event(wp_next_scheduled(self::getHookName()), self::getHookName());
        }

        return $options;
    }

    public function refreshSitemapAction()
    {
        $refreshNowOption = self::getRefreshNowOption();
        $formOptionName = self::getPageSettingOptionName() . '[' . $refreshNowOption . ']';
        ?>
        <input id="button-<?php echo $refreshNowOption; ?>" type="button" class="button button-warning" value="Refresh now"/>
        <label>
            <input type="checkbox" id="<?php echo $refreshNowOption; ?>"
                   name="<?php echo $formOptionName; ?>" checked="false" style="visibility: hidden; display: none"/>
        </label>

        <script>
            var ck = document.getElementById('<?php echo $refreshNowOption;?>');
            var btn = document.getElementById('button-<?php echo $refreshNowOption;?>');
            ck.checked = false;
            btn.addEventListener('click', function () {
                if (confirm('Are your sure?')) {
                    document.getElementById('<?php echo $refreshNowOption;?>').checked = true;
                    document.getElementById('submit').click();
                }
            }, false);
        </script>
        <?php
    }
}