<?php

return [
    'libreoffice_bin'   => env('LIBREOFFICE_BIN', 'soffice'),
    'pdf_driver'        => env('BDC_PDF_DRIVER', 'auto'),
    'gotenberg_url'     => env('GOTENBERG_URL'),
    'gotenberg_verify_ssl' => (bool) env('GOTENBERG_VERIFY_SSL', true),
    'pdf_timeout'       => (int) env('DOC_PDF_TIMEOUT', 300),
    'temp_disk'         => env('DOC_TEMP_DISK', 'local'),
    'upload_disk'       => env('DOC_UPLOAD_DISK', 's3'),
    'upload_visibility' => env('DOC_UPLOAD_VIS', 'public'),
    'keep_local'        => (bool) env('DOC_KEEP_LOCAL', false),
    'docx_path'         => 'documents/word/{category}/{year}/{filename}.docx',
    'pdf_path'          => 'documents/pdf/{category}/{year}/{filename}.pdf',
];
