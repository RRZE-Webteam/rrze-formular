<?php

namespace RRZE\Formular\Common\Settings;

use RRZE\Formular\Common\Settings\{
    Option,
    Tab
};

defined('ABSPATH') || exit;

/**
 * Groups settings fields under a title, optionally as a separately navigable section.
 */
class Section
{
    /**
     * Tab that owns this section.
     *
     * @var Tab
     */
    public $tab;

    /**
     * Whether the section is selected through section navigation.
     *
     * @var bool
     */
    public $asLink;

    /**
     * Visible section title.
     *
     * @var string
     */
    public $title;

    /**
     * Section configuration containing optional slug, description and as_link values.
     *
     * @var array<string, mixed>|string
     */
    public $args;

    /**
     * Section identifier used in navigation URLs.
     *
     * @var string
     */
    public $slug;

    /**
     * Optional explanatory text shown with the section.
     *
     * @var string|null
     */
    public $description;

    /**
     * Settings fields in display order.
     *
     * @var list<Option>
     */
    public $options = [];

    /**
     * Attach a section to its tab and apply navigation and display settings.
     *
     * @param Tab $tab Owning tab.
     * @param string $title Visible section title.
     * @param array<string, mixed>|string $args Optional slug, description and as_link settings; legacy string arguments use defaults.
     */
    public function __construct(Tab $tab, $title, $args = [])
    {
        $this->tab = $tab;
        $this->title = $title;
        $this->args = $args;
        $this->slug = $this->args['slug'] ?? sanitize_title($title);
        $this->description = $this->args['description'] ?? null;
        $this->asLink = $this->args['as_link'] ?? false;
    }

    /**
     * Create a typed field and append it to this section.
     *
     * @param string $type Registered field type, such as text or checkbox.
     * @param array<string, mixed> $args Field name, label, default and optional callbacks.
     * @return Option Newly created field wrapper.
     */
    public function addOption($type, $args = [])
    {
        $option = new Option($this, $type, $args);

        $this->options[] = $option;

        return $option;
    }
}
