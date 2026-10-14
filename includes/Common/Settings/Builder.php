<?php

namespace RRZE\Formular\Common\Settings;

defined('ABSPATH') || exit;

/**
 * Stores named settings callbacks and executes them in insertion order.
 */
class Builder
{
    /**
     * Callbacks keyed by handle; adding an existing handle replaces its callback.
     *
     * @var array<string, callable(): void>
     */
    public array $enqueued = [];

    /**
     * Store or replace a settings callback under its handle.
     *
     * @param string $handle Unique callback identifier.
     * @param callable(): void $callback Callback to execute when enqueue() is called.
     * @return void
     */
    public function add($handle, $callback)
    {
        $this->enqueued[$handle] = $callback;
    }

    /**
     * Remove a queued callback if its handle exists.
     *
     * @param string $handle Callback identifier.
     * @return void
     */
    public function remove($handle)
    {
        unset($this->enqueued[$handle]);
    }

    /**
     * Execute all stored callbacks in order without clearing the queue.
     *
     * @return void
     */
    public function enqueue()
    {
        foreach ($this->enqueued as $enqueue) {
            $enqueue();
        }
    }
}
