<?php
namespace RRZE\Formular\Common;

defined('ABSPATH') || exit;

/**
 * Reports a plugin exception through the shared RRZE error-log action.
 */
class CustomException extends \Exception
{
    /**
     * Create the exception and emit its message through rrze.log.error.
     *
     * @param string $message Human-readable error message.
     * @param int $code Exception code.
     * @param CustomException|null $previous Earlier plugin exception that caused this error.
     */
    public function __construct($message = "", $code = 0, CustomException $previous = null)
    {
        parent::__construct($message, $code, $previous);

        do_action('rrze.log.error', ['plugin' => 'rrze-formular', 'wp-error' => $message]);
    }
}
