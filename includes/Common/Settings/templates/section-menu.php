<?php

namespace RRZE\Formular\Common\Settings;

defined('ABSPATH') || exit;
?>
<?php if ($linkedSections = $settings->getActiveTab()->getSectionLinks()) { ?>
    <ul class="subsubsub rrze-formular-section-menu">
        <?php foreach ($linkedSections as $section) { ?>
            <li><a href="<?php echo esc_url($settings->getUrl() . '&tab=' . rawurlencode((string) $section->tab->slug) . '&section=' . rawurlencode((string) $section->slug)); ?>" class="<?php echo $section->slug === $settings->getActiveTab()->getActiveSection()->slug ? 'current' : ''; ?>"><?php echo esc_html((string) $section->title); ?></a> | </li>
        <?php } ?>
    </ul>
<?php } ?>
