# Laravel Document Generator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/bdcgenerator/laravel-document-generator.svg?style=flat-square)](https://packagist.org/packages/bdcgenerator/laravel-document-generator)
[![Total Downloads](https://img.shields.io/packagist/dt/bdcgenerator/laravel-document-generator.svg?style=flat-square)](https://packagist.org/packages/bdcgenerator/laravel-document-generator)

This library is made to make it easy to generate documents from DOCX templates and convert them to PDF using **PhpWord** and **LibreOffice**, with support for dynamic templates, images, repeaters, and S3 upload.

---

## Requirements

- PHP 8.1+
- Laravel 10 or 11
- LibreOffice installed on the server (`soffice` command available)
- PhpOffice PhpWord
- (Optional) AWS S3 configured in `filesystems.php`

---

## Installation

```bash
composer require bdcgenerator/laravel-document-generator
php artisan vendor:publish --tag=bdcgenerator-config
php artisan vendor:publish --tag=bdcgenerator-migrations
php artisan migrate
```
## ENV

```
LIBREOFFICE_BIN=/usr/bin/soffice
DOC_TEMP_DISK=local
DOC_UPLOAD_DISK=s3
DOC_UPLOAD_VIS=public
DOC_KEEP_LOCAL=false
```

## USAGE (STATIC TEMPLATE)

```
use Document;

$result = Document::generate([
    'template_path' => public_path('templates/SuratKeterangan.docx'),
    'values' => [
        'nama'   => $user->name,
        'alamat' => $user->address,
    ],
    'image'  => [
        'key'    => 'ttd_kepala',
        'path'   => storage_path('app/public/sign/kepala.png'),
        'width'  => 100,
        'height' => 50,
    ],
    'category' => 'surat',
    'year'     => date('Y'),
    'filename' => 'surat_keterangan_'.$user->id,
    'upload'   => true,
]);

```
## (Dynamic Template)

```
$result = Document::generateByKey('ijazah_v1', [
    'context' => [
        'category' => 'ijazah',
        'year'     => 2025,
        'filename' => 'ijazah_'.$mahasiswa->NIM
    ],
    'data' => $payload // structure matches your descriptor mapping
]);

```
## Features

```
Static generation: Map placeholder values directly in code.

Dynamic templates: Store descriptor mapping in DB for flexible document definitions.

Image insertion: Insert images into DOCX placeholders.

Repeaters: Clone table rows or blocks for list data.

Conditional blocks: Delete or keep sections based on conditions.

PDF conversion: Uses LibreOffice CLI for accurate DOCX to PDF conversion.

S3 upload: Directly upload the generated PDF to S3.

```
