<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

class SubmissionCsv
{
    /**
     * @param list<array{0: string, 1: string}> $rows Label/value pairs.
     */
    public static function build(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [__('Field', 'rrze-formular'), __('Value', 'rrze-formular')], ',', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($handle, [(string) ($row[0] ?? ''), (string) ($row[1] ?? '')], ',', '"', '\\');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return is_string($content) ? $content : '';
    }

    public static function filename(string $formTitle): string
    {
        $slug = sanitize_title($formTitle);
        if ($slug === '') {
            $slug = 'formular';
        }

        return sanitize_file_name(wp_date('Y-m-d-His') . '-' . $slug . '.csv');
    }

    public static function writeTempFile(string $content, string $filename): ?string
    {
        if ($content === '') {
            return null;
        }

        $filename = sanitize_file_name($filename);
        if ($filename === '') {
            return null;
        }

        $path = wp_tempnam($filename);
        if ($path === false) {
            return null;
        }

        if (file_put_contents($path, $content) === false) {
            wp_delete_file($path);

            return null;
        }

        return $path;
    }
}
