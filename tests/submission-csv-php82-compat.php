<?php

declare(strict_types=1);

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__ . '/');
    }
}

namespace RRZE\Formular\Common\Form {
    require __DIR__ . '/../includes/Common/Form/SubmissionCsv.php';

    $content = SubmissionCsv::build(['Name', 'Value'], ['Test', '1,2"3']);

    if ($content === '' || !str_contains($content, "Test,\"1,2\"\"3\"")) {
        fwrite(STDERR, "FAIL\n{$content}\n");
        exit(1);
    }

    fwrite(STDOUT, "OK\n");
}
