<?php

return [
    'libreoffice_bin'   => env('LIBREOFFICE_BIN', 'soffice'),
    'temp_disk'         => env('DOC_TEMP_DISK', 'local'),
    'upload_disk'       => env('DOC_UPLOAD_DISK', 's3'),
    'upload_visibility' => env('DOC_UPLOAD_VIS', 'public'),
    'keep_local'        => (bool) env('DOC_KEEP_LOCAL', false),
    'docx_path'         => 'documents/word/{category}/{year}/{filename}.docx',
    'pdf_path'          => 'documents/pdf/{category}/{year}/{filename}.pdf',
];
