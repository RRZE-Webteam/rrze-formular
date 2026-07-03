<?php

declare(strict_types=1);

/**
 * Run: php tests/submission-csv-build.php
 */

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__ . '/');
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

    $headers = [
        'Vorname',
        'Nachname',
        'E-Mail-Adresse',
        'Zugehörigkeit',
        'Titel des Beitrags',
        'Abstract',
        'Schlagwörter',
        'Bevorzugtes Format',
    ];

    $values = [
        'Benjamin',
        'Klemencic',
        'benjamin.klemencic@fau.de',
        'RRZE AI',
        'Das ist mein Titel',
        'Und das ist das Abstract.',
        'eins,zwei,drei',
        'Beides möglich',
    ];

    $content = SubmissionCsv::build($headers, $values);
    $lines = array_values(array_filter(preg_split('/\r\n|\n/', ltrim($content, "\xEF\xBB\xBF")) ?: [], static fn(string $line): bool => $line !== ''));

    if (count($lines) !== 2) {
        fwrite(STDERR, "FAIL: expected 2 lines, got " . count($lines) . "\n{$content}\n");
        exit(1);
    }

    if (str_contains($lines[0], 'Field') || str_contains($lines[0], 'Value')) {
        fwrite(STDERR, "FAIL: must not contain Field/Value header columns\n{$content}\n");
        exit(1);
    }

  foreach ($headers as $header) {
        if (!str_contains($lines[0], $header)) {
            fwrite(STDERR, "FAIL: header row missing {$header}\n{$lines[0]}\n");
            exit(1);
        }
    }

    if (!str_contains($lines[1], 'Benjamin') || !str_contains($lines[1], 'Beides möglich') || !str_contains($lines[1], '"eins,zwei,drei"')) {
        fwrite(STDERR, "FAIL: value row invalid\n{$lines[1]}\n");
        exit(1);
    }

    fwrite(STDOUT, "OK\n{$lines[0]}\n{$lines[1]}\n");
}
