<?php

namespace RRZE\Formular\Common\Settings;

use RRZE\Formular\Common\Settings\Section;

defined('ABSPATH') || exit;

/**
 * Groups settings sections and resolves their selection from the current request.
 */
class Tab
{
    /**
     * Settings page that owns this tab.
     *
     * @var Settings
     */
    public $settings;

    /**
     * Visible tab label.
     *
     * @var string
     */
    public $title;

    /**
     * Tab identifier used in navigation URLs.
     *
     * @var string
     */
    public $slug;

    /**
     * Sections in display order.
     *
     * @var list<Section>
     */
    public $sections = [];

    /**
     * Attach a tab to its settings page and derive its slug when omitted.
     *
     * @param Settings $settings Owning settings page.
     * @param string $title Visible tab label.
     * @param string|null $slug Explicit tab slug, or null to derive it from the title.
     */
    public function __construct($settings, $title, $slug = null)
    {
        $this->title = $title;
        $this->settings = $settings;
        $this->slug = $slug ?? sanitize_title($title);
    }

    /**
     * Append a section to this tab.
     *
     * @param string $title Visible section title.
     * @param array<string, mixed>|string $args Section settings, or a legacy string that uses defaults.
     * @return Section Newly created section.
     */
    public function addSection($title, $args = [])
    {
        $section = new Section($this, $title, $args);

        $this->sections[] = $section;

        return $section;
    }

    /**
     * Return sections configured for separate navigation.
     *
     * @return array<int, Section> Linked sections, retaining their original array keys.
     */
    public function getSectionLinks()
    {
        return array_filter($this->sections, function ($section) {
            return $section->asLink;
        });
    }

    /**
     * Check whether every section is configured as a navigation link.
     *
     * @return bool True when all sections are links, including when no sections exist.
     */
    public function containsOnlySectionLinks()
    {
        return count($this->getSectionLinks()) === count($this->sections);
    }

    /**
     * Find a section by its slug.
     *
     * @param string $name Section slug to look up.
     * @return Section|false Matching section, or false when no slug matches.
     */
    public function getSectionByName($name)
    {
        foreach ($this->sections as $section) {
            if ($section->slug == $name) {
                return $section;
            }
        }

        return false;
    }

    /**
     * Resolve the requested linked section or the default linked section.
     *
     * @return Section|false|null Selected section, false for an unknown requested slug, or null when none is selected.
     */
    public function getActiveSection()
    {
        if (empty($this->getSectionLinks())) {
            return;
        }

        if (isset($_REQUEST['section'])) {
            return $this->getSectionByName((string) wp_unslash($_REQUEST['section']));
        }

        if ($this->containsOnlySectionLinks()) {
            return $this->sections[0];
        }
    }

    /**
     * Choose which sections to render for the current request.
     *
     * The tab is expected to contain at least one section.
     *
     * @return array<int, Section> Requested linked section, default linked section or inline sections.
     */
    public function getActiveSections()
    {
        if (!isset($_REQUEST['section']) && $this->containsOnlySectionLinks()) {
            return [$this->sections[0]];
        }

        $requestedSection = isset($_REQUEST['section'])
            ? (string) wp_unslash($_REQUEST['section'])
            : '';

        return \array_filter($this->sections, function ($section) use ($requestedSection) {
            if (isset($_REQUEST['section'])) {
                return $section->asLink && $requestedSection === $section->slug;
            }

            return !$section->asLink;
        });
    }
}
