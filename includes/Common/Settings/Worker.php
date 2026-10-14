<?php

namespace RRZE\Formular\Common\Settings;

defined('ABSPATH') || exit;

/**
 * Provides access to the shared settings-callback builder.
 *
 * Set a Builder instance before adding callbacks or executing the queue.
 */
class Worker
{
    /**
     * Shared builder installed by setBuilder().
     *
     * @var Builder
     */
    protected static Builder $builder;

    /**
     * Replace the shared settings builder.
     *
     * @param Builder $builder Builder to use for subsequent operations.
     * @return void
     */
    public static function setBuilder($builder)
    {
        static::$builder = $builder;
    }

    /**
     * Return the previously installed shared builder.
     *
     * @return Builder Current settings builder.
     */
    public static function builder()
    {
        return static::$builder;
    }

    /**
     * Forward a named callback to the shared builder.
     *
     * @param string $handle Unique callback identifier.
     * @param callable(): void $callback Settings callback to queue.
     * @return void
     */
    public static function add($handle, $callback)
    {
        static::builder()->add($handle, $callback);
    }

    /**
     * Remove a callback from the shared builder.
     *
     * @param string $handle Callback identifier.
     * @return void
     */
    public function remove($handle)
    {
        static::builder()->remove($handle);
    }

    /**
     * Execute the shared builder’s queued callbacks.
     *
     * @return void
     */
    public static function enqueue()
    {
        static::builder()->enqueue();
    }
}
