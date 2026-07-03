<?php

declare(strict_types=1);

/**
 * Ensures SubmissionCsv::build works on PHP 8.2 (no 5th fputcsv argument).
 * Run: php tests/submission-csv-php82-compat.php
 */

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__ . '/');
    }

    function __($text, $domain = 'default')
    {
        return $text;
    }
}

namespace RRZE\Formular\Common\Form {
    require __DIR__ . '/../includes/Common/Form/SubmissionCsv.php';

    $rows = [['Name', 'Test'], ['Value', '1,2"3']];
    $content = SubmissionCsv::build($rows);

    if ($content === '' || !str_contains($content, 'Name')) {
        fwrite(STDERR, "FAIL: empty or invalid CSV output\n");
        exit(1);
    }

    fwrite(STDOUT, "OK\n");
}
