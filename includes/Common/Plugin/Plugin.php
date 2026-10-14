<?php

namespace RRZE\Formular\Common\Plugin;

defined('ABSPATH') || exit;

/**
 * Reads plugin header metadata and derives filesystem paths and URLs.
 *
 * Call loaded() before using accessors that depend on metadata or directory state.
 */
class Plugin
{
    /**
     * Absolute path to the main plugin PHP file.
     *
     * @var string
     */
    protected $pluginFile;

    /**
     * Plugin basename relative to the plugins directory, populated by loaded().
     *
     * @var string|null
     */
    protected $basename;

    /**
     * Plugin filesystem directory with a trailing slash, populated by loaded().
     *
     * @var string|null
     */
    protected $directory;

    /**
     * Plugin directory URL with a trailing slash, populated by loaded().
     *
     * @var string|null
     */
    protected $url;

    /**
     * Plugin header metadata, populated by loaded().
     *
     * @var array<string, mixed>|null
     */
    protected $data;

    /**
     * Store the main plugin file and load the WordPress plugin-metadata helpers.
     *
     * @param string $pluginFile Absolute path to the main plugin PHP file.
     */
    public function __construct(string $pluginFile)
    {
        $this->pluginFile = $pluginFile;
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    /**
     * Populate the plugin basename, directory, URL and header metadata.
     *
     * @return void
     */
    public function loaded()
    {
        $this->setBasename()
            ->setDirectory()
            ->setUrl()
            ->setData();
    }

    /**
     * Return the absolute path to the main plugin PHP file.
     *
     * @return string Main plugin file path.
     */
    public function getFile(): string
    {
        return $this->pluginFile;
    }

    /**
     * Return the initialized plugin basename.
     *
     * @return string Path relative to the plugins directory.
     */
    public function getBasename(): string
    {
        return $this->basename;
    }

    /**
     * Derive the plugin basename from the main PHP file.
     *
     * @return self This metadata object for method chaining.
     */
    public function setBasename(): object
    {
        $this->basename = plugin_basename($this->pluginFile);
        return $this;
    }

    /**
     * Return the initialized plugin filesystem directory.
     *
     * @return string Absolute directory path with a trailing slash.
     */
    public function getDirectory(): string
    {
        return $this->directory;
    }

    /**
     * Resolve the plugin filesystem directory with a trailing slash.
     *
     * @return self This metadata object for method chaining.
     */
    public function setDirectory(): object
    {
        $this->directory = rtrim(plugin_dir_path($this->pluginFile), '/') . '/';
        return $this;
    }

    /**
     * Build a filesystem directory path below the plugin root.
     *
     * @param string $path Optional relative directory path.
     * @return string Absolute path with a trailing slash; use getFile() for the main PHP file.
     */
    public function getPath(string $path = ''): string
    {
        return $this->directory . ($path ? trim($path, '/') . '/' : '');
    }

    /**
     * Build a directory URL below the plugin root.
     *
     * @param string $path Optional relative directory path.
     * @return string Plugin URL with a trailing slash.
     */
    public function getUrl(string $path = ''): string
    {
        return $this->url . ($path ? trim($path, '/') . '/' : '');
    }

    /**
     * Resolve the plugin directory URL with a trailing slash.
     *
     * @return self This metadata object for method chaining.
     */
    public function setUrl(): object
    {
        $this->url = rtrim(plugin_dir_url($this->pluginFile), '/') . '/';
        return $this;
    }

    /**
     * Derive a sanitized plugin slug from its basename directory.
     *
     * @return string Plugin directory slug.
     */
    public function getSlug(): string
    {
        return sanitize_key(dirname($this->basename));
    }

    /**
     * Read the plugin headers from the main PHP file.
     *
     * @return self This metadata object for method chaining.
     */
    public function setData(): object
    {
        $this->data = get_plugin_data($this->pluginFile, false);
        return $this;
    }

    /**
     * Return the initialized plugin header metadata.
     *
     * @return array<string, mixed> Header values keyed by WordPress metadata names.
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Read the display name from the plugin headers.
     *
     * @return string Plugin name.
     */
    public function getName(): string
    {
        return $this->data['Name'];
    }

    /**
     * Read the version from the plugin headers.
     *
     * @return string Current plugin version.
     */
    public function getVersion(): string
    {
        return $this->data['Version'];
    }

    /**
     * Read the minimum WordPress version from the plugin headers.
     *
     * @return string Required WordPress version.
     */
    public function getRequiresWP(): string
    {
        return $this->data['RequiresWP'];
    }

    /**
     * Read the minimum PHP version from the plugin headers.
     *
     * @return string Required PHP version.
     */
    public function getRequiresPHP(): string
    {
        return $this->data['RequiresPHP'];
    }

    /**
     * Report an unknown method call when WordPress debugging is enabled.
     *
     * Unknown method calls are ignored when WP_DEBUG is disabled.
     *
     * @param string $name Requested method name.
     * @param list<mixed> $arguments Supplied arguments; not used.
     * @return void
     * @throws \Exception When the method does not exist and WP_DEBUG is enabled.
     */
    public function __call(string $name, array $arguments)
    {
        if (!method_exists($this, $name)) {
            $message = sprintf('Call to undefined method %1$s::%2$s', __CLASS__, $name);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                throw new \Exception($message);
            }
        }
    }
}
