<?php

namespace BDCGenerator\DocumentGenerator\Generator;

use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use BDCGenerator\DocumentGenerator\Contracts\Generator as GeneratorContract;
use BDCGenerator\DocumentGenerator\DTO\GenerateResult;
use BDCGenerator\DocumentGenerator\Exceptions\DocumentGeneratorException;
use BDCGenerator\DocumentGenerator\Pdf\LibreOfficeConverter;
use BDCGenerator\DocumentGenerator\Support\PathBuilder;

class PhpWordGenerator implements GeneratorContract
{
    public function __construct(
        private string $libreofficeBin,
        private string $tempDisk,
        private string $uploadDisk,
        private string $visibility,
        private bool   $keepLocal,
        private string $docxPattern,
        private string $pdfPattern,
        private ?TemplateManager $tm = null, // diisi saat generateByKey
    ) {
        $this->tm ??= new TemplateManager();
    }

    /** =================== Static mapping API =================== */
    public function generate(array $params): GenerateResult
    {
        // Required
        $templatePath = $params['template_path'] ?? null;
        if (!$templatePath) throw new DocumentGeneratorException('template_path is required');
        $values       = $params['values'] ?? [];
        $imageCfg     = $params['image'] ?? null;

        $category = (string)($params['category'] ?? 'document');
        $year     = (string)($params['year'] ?? date('Y'));
        $filename = PathBuilder::safeFilename($params['filename'] ?? ('doc_' . time()));
        $doUpload = (bool)($params['upload'] ?? true);

        // Save DOCX (local temp disk)
        $docxRel = PathBuilder::build($this->docxPattern, compact('category', 'year', 'filename'));
        $docxLocal = storage_path('app/' . $this->tempDisk . '/' . $docxRel);
        @mkdir(dirname($docxLocal), 0777, true);

        $tp = new TemplateProcessor($this->resolvePath($templatePath));
        foreach ($values as $k => $v) $tp->setValue($k, $v ?? '');

        if ($imageCfg && !empty($imageCfg['key'])) {
            $tp->setImageValue($imageCfg['key'], [
                'path'   => $this->resolvePath($imageCfg['path'] ?? ''),
                'width'  => $imageCfg['width']  ?? 130,
                'height' => $imageCfg['height'] ?? 169,
                'ratio'  => (bool)($imageCfg['ratio'] ?? false),
            ]);
        }

        $tp->saveAs($docxLocal);

        // Convert to PDF
        $converter  = new LibreOfficeConverter($this->libreofficeBin);
        $pdfLocal   = $converter->toPdf($docxLocal, dirname($docxLocal));

        // Upload
        $uploadedUrl = null;
        if ($doUpload) {
            $pdfRel = PathBuilder::build($this->pdfPattern, compact('category', 'year', 'filename'));
            $disk   = Storage::disk($this->uploadDisk);
            $disk->put($pdfRel, file_get_contents($pdfLocal), $this->visibility);
            $uploadedUrl = $disk->url($pdfRel);
        }

        if (!$this->keepLocal) {
            @unlink($pdfLocal);
            @unlink($docxLocal);
        }

        return new GenerateResult($docxLocal, $this->keepLocal ? $pdfLocal : null, $uploadedUrl);
    }

    /** =================== Dynamic (descriptor in DB) =================== */
    public function generateByKey(string $templateKey, array $payload): GenerateResult
    {
        $descriptor = $this->tm->findActiveDescriptor($templateKey);
        if (!$descriptor) throw new DocumentGeneratorException("Descriptor not found for key: $templateKey");

        $data    = $payload['data']    ?? [];
        $context = $payload['context'] ?? [];

        // 1) Template path
        $templatePath = $descriptor['template_path'] ?? null;
        if (!$templatePath) throw new DocumentGeneratorException('descriptor.template_path is missing');

        $tp = new TemplateProcessor($this->resolvePath($templatePath));
        $filters = new Filters();

        // 2) Fields
        foreach (($descriptor['fields'] ?? []) as $placeholder => $def) {
            $source = $def['source'] ?? null;
            $value  = $source ? data_get($data, $source) : null;
            $chain  = $def['filters'] ?? [];
            $value  = $filters->applyChain($value, $chain);
            $tp->setValue($placeholder, $value ?? '');
        }

        // 3) Images
        foreach (($descriptor['images'] ?? []) as $imgKey => $img) {
            $src = $img['source'] ?? null;
            $path = $src ? data_get($data, $src) : null;
            if ($path) {
                $tp->setImageValue($imgKey, [
                    'path'   => $this->resolvePath($path),
                    'width'  => $img['width']  ?? 130,
                    'height' => $img['height'] ?? 169,
                    'ratio'  => (bool)($img['ratio'] ?? false),
                ]);
            }
        }

        // 4) Repeaters (cloneBlock basic)
        foreach (($descriptor['repeaters'] ?? []) as $blockName => $rep) {
            $rows = data_get($data, $rep['rows_source'] ?? '', []);
            $rows = is_array($rows) ? $rows : [];
            $tp->cloneBlock($blockName, count($rows), true, true);
            $i = 1;
            foreach ($rows as $row) {
                foreach (($rep['map'] ?? []) as $ph => $src) {
                    $tp->setValue("$ph#{$i}", data_get($row, $src, ''));
                }
                $i++;
            }
        }

        // 5) Conditions (sederhana: delete_blocks jika predicate true)
        foreach (($descriptor['conditions'] ?? []) as $cond) {
            // predicate sangat sederhana: evaluasi "==" untuk 1 ekspresi umum
            $if = $cond['if'] ?? null;
            if ($if && $this->simpleEval($if, ['context' => $context, 'data' => $data])) {
                foreach (($cond['delete_blocks'] ?? []) as $blk) {
                    $tp->deleteBlock($blk);
                }
            }
        }

        // 6) Bangun path dinamis
        $category = data_get($context, $descriptor['output']['category_from'] ?? 'context.category', 'document');
        $year     = data_get($context, $descriptor['output']['year_from']     ?? 'context.year', date('Y'));
        $filename = PathBuilder::safeFilename(
            data_get($context, $descriptor['output']['filename_from'] ?? 'context.filename', 'doc_' . time())
        );

        // 7) Simpan DOCX
        $docxRel   = PathBuilder::build($this->docxPattern, compact('category', 'year', 'filename'));
        $docxLocal = storage_path('app/' . $this->tempDisk . '/' . $docxRel);
        @mkdir(dirname($docxLocal), 0777, true);
        $tp->saveAs($docxLocal);

        // 8) Convert PDF
        $converter  = new LibreOfficeConverter($this->libreofficeBin);
        $pdfLocal   = $converter->toPdf($docxLocal, dirname($docxLocal));

        // 9) Upload (descriptor bisa override)
        $uploadedUrl = null;
        $up = $descriptor['upload'] ?? [];
        $doUpload   = array_key_exists('disk', $up) ? true : true; // default true
        if ($doUpload) {
            $diskName   = $up['disk']       ?? $this->uploadDisk;
            $visibility = $up['visibility'] ?? $this->visibility;
            $pdfRel     = PathBuilder::build($this->pdfPattern, compact('category', 'year', 'filename'));

            $disk = Storage::disk($diskName);
            $disk->put($pdfRel, file_get_contents($pdfLocal), $visibility);
            $uploadedUrl = $disk->url($pdfRel);
        }

        // 10) Cleanup
        $keep = array_key_exists('keep_local', $up) ? (bool)$up['keep_local'] : $this->keepLocal;
        if (!$keep) {
            @unlink($pdfLocal);
            @unlink($docxLocal);
        }

        return new GenerateResult($docxLocal, $keep ? $pdfLocal : null, $uploadedUrl);
    }

    /** ---------------- Helpers ---------------- */

    private function resolvePath(string $input): string
    {
        if (str_starts_with($input, 'storage://')) {
            // format: storage://disk/path/to/file
            $rest = substr($input, 10); // after "storage://"
            [$disk, $path] = explode('/', $rest, 2);
            $fs = Storage::disk($disk);
            if (!$fs->exists($path)) {
                throw new DocumentGeneratorException("File not found at storage://$disk/$path");
            }
            $local = storage_path("app/$disk/$path");
            @mkdir(dirname($local), 0777, true);
            if (!is_file($local)) {
                file_put_contents($local, $fs->get($path));
            }
            return $local;
        }

        if (!is_file($input)) {
            throw new DocumentGeneratorException("Invalid path: $input");
        }
        return $input;
    }

    /** super simple evaluator: "context.category == 'ijazah'" */
    private function simpleEval(string $expr, array $vars): bool
    {
        // Mendukung pattern sangat basic: "a == b" dan "&&"
        $parts = preg_split('/\s+&&\s+/', $expr);
        foreach ($parts as $p) {
            if (!preg_match('/^\s*([a-zA-Z0-9\._]+)\s*==\s*\'([^\']*)\'\s*$/', $p, $m)) return false;
            $lhs = data_get($vars, $m[1]);
            if ((string)$lhs !== $m[2]) return false;
        }
        return true;
    }
}
