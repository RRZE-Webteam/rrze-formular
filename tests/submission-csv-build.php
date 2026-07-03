<?php

declare(strict_types=1);

/**
 * Verifies CSV content is non-empty for typical submission rows.
 * Run: php tests/submission-csv-build.php
 */

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__ . '/');
    }

    function __($text, $domain = 'default')
    {
        return $text;
    }

    function sanitize_title($title)
    {
        return strtolower(preg_replace('/[^a-z0-9]+/', '-', (string) $title) ?? '');
    }

    function sanitize_file_name($filename)
    {
        return preg_replace('/[^a-zA-Z0-9._-]/', '', (string) $filename) ?? '';
    }

    function wp_date($format, $timestamp = null)
    {
        return date($format, $timestamp ?? time());
    }
}

namespace RRZE\Formular\Common\Form {
    require __DIR__ . '/../includes/Common/Form/SubmissionCsv.php';

    $rows = [
        ['First name', 'Max'],
        ['E-mail', 'max@example.com'],
    ];

    $content = SubmissionCsv::build($rows);
    $lines = array_values(array_filter(explode("\n", trim($content, "\xEF\xBB\xBF")), static fn(string $line): bool => $line !== ''));

    if (
        count($lines) !== 2
        || !str_contains($lines[0], 'First name')
        || !str_contains($lines[0], 'E-mail')
        || !str_contains($lines[1], 'Max')
        || !str_contains($lines[1], 'max@example.com')
    ) {
        fwrite(STDERR, "FAIL: CSV content invalid:\n{$content}\n");
        exit(1);
    }

    $filename = SubmissionCsv::filename('Kontakt');
    if ($filename === '' || !str_ends_with(strtolower($filename), '.csv')) {
        fwrite(STDERR, "FAIL: invalid filename: {$filename}\n");
        exit(1);
    }

    fwrite(STDOUT, "OK: CSV build ({$filename}, " . strlen($content) . " bytes)\n");
}
