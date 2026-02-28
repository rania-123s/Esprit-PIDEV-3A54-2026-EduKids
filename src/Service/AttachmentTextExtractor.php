<?php

namespace App\Service;

use App\Entity\MessageAttachment;
use Symfony\Component\Process\Process;

class AttachmentTextExtractor
{
    private const OCR_MAX_PAGES = 5;

    private const PDF_MIME_TYPES = [
        'application/pdf',
    ];

    private const DOCX_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    private const TXT_MIME_TYPES = [
        'text/plain',
    ];

    public function extractFromAttachment(MessageAttachment $attachment, string $absolutePath): string
    {
        if (!is_file($absolutePath)) {
            throw new \RuntimeException('Le fichier joint est introuvable.');
        }

        $format = $this->resolveFormat($attachment, $absolutePath);

        $text = match ($format) {
            'pdf' => $this->extractPdf($absolutePath),
            'docx' => $this->extractDocx($absolutePath),
            'txt' => $this->extractTxt($absolutePath),
            default => throw new \InvalidArgumentException('Type de fichier non supporte.'),
        };

        return $this->normalizeText($text);
    }

    private function resolveFormat(MessageAttachment $attachment, string $absolutePath): string
    {
        $mimeType = strtolower(trim($attachment->getMimeType()));
        $extension = strtolower((string) pathinfo($attachment->getOriginalName(), PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION));
        }

        if (in_array($mimeType, self::PDF_MIME_TYPES, true) || $extension === 'pdf') {
            return 'pdf';
        }

        if (in_array($mimeType, self::DOCX_MIME_TYPES, true) || $extension === 'docx') {
            return 'docx';
        }

        if (in_array($mimeType, self::TXT_MIME_TYPES, true) || $extension === 'txt') {
            return 'txt';
        }

        throw new \InvalidArgumentException('Seuls les fichiers PDF, DOCX et TXT sont supportes pour le resume.');
    }

    private function extractTxt(string $absolutePath): string
    {
        $content = @file_get_contents($absolutePath);
        if (!is_string($content)) {
            throw new \RuntimeException('Impossible de lire le fichier TXT.');
        }

        return $content;
    }

    private function extractDocx(string $absolutePath): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('L extension ZIP de PHP est requise pour lire les fichiers DOCX.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($absolutePath) !== true) {
            throw new \RuntimeException('Impossible d ouvrir le fichier DOCX.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (!is_string($xml) || trim($xml) === '') {
            throw new \RuntimeException('Le contenu du DOCX est vide ou illisible.');
        }

        $withNewLines = str_replace(
            ['</w:p>', '</w:tr>', '</w:tbl>', '<w:br/>', '<w:cr/>'],
            "\n",
            $xml
        );

        $plain = strip_tags($withNewLines);

        return html_entity_decode($plain, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function extractPdf(string $absolutePath): string
    {
        $pdftotextOutput = $this->runProcess(
            [$this->getBinary('ATTACHMENT_PDFTOTEXT_BIN', 'pdftotext', ['POPPLER_PDFTOTEXT']), '-layout', '-enc', 'UTF-8', $absolutePath, '-'],
            20
        );
        if ($pdftotextOutput !== '') {
            return $pdftotextOutput;
        }

        $raw = @file_get_contents($absolutePath);
        if (!is_string($raw) || $raw === '') {
            throw new \RuntimeException('Impossible de lire le fichier PDF.');
        }

        $buffer = '';
        if (preg_match_all('/\(([^()]*)\)\s*Tj/s', $raw, $matches) && isset($matches[1])) {
            $buffer = implode("\n", $matches[1]);
        }

        if ($buffer === '' && preg_match_all('/\[(.*?)\]\s*TJ/s', $raw, $batchMatches) && isset($batchMatches[1])) {
            $fragments = [];
            foreach ($batchMatches[1] as $chunk) {
                if (!is_string($chunk)) {
                    continue;
                }

                if (preg_match_all('/\(([^()]*)\)/s', $chunk, $chunkMatches) && isset($chunkMatches[1])) {
                    $fragments[] = implode(' ', $chunkMatches[1]);
                }
            }
            $buffer = implode("\n", $fragments);
        }

        if (trim($buffer) !== '') {
            return $buffer;
        }

        $ocrText = $this->extractPdfWithOcr($absolutePath);
        if ($ocrText !== '') {
            return $ocrText;
        }

        throw new \RuntimeException('Impossible d extraire le texte du PDF. Installez pdftotext ou activez OCR (tesseract + pdftoppm/magick).');
    }

    private function extractPdfWithOcr(string $absolutePath): string
    {
        $directOcr = $this->runTesseract($absolutePath, 45);
        if ($directOcr !== '') {
            return $directOcr;
        }

        $tempDir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'chat_ocr_' . bin2hex(random_bytes(6));
        if (!@mkdir($tempDir, 0775, true) && !is_dir($tempDir)) {
            return '';
        }

        try {
            $images = $this->renderPdfPagesAsImages($absolutePath, $tempDir);
            if ($images === []) {
                return '';
            }

            $parts = [];
            foreach ($images as $imagePath) {
                $chunk = $this->runTesseract($imagePath, 45);
                if ($chunk !== '') {
                    $parts[] = $chunk;
                }
            }

            return trim(implode("\n", $parts));
        } finally {
            $this->cleanupDirectory($tempDir);
        }
    }

    /**
     * @return string[]
     */
    private function renderPdfPagesAsImages(string $absolutePath, string $tempDir): array
    {
        $prefix = $tempDir . DIRECTORY_SEPARATOR . 'page';
        $maxPage = (string) self::OCR_MAX_PAGES;

        $pdftoppmOutput = $this->runProcess(
            [$this->getBinary('ATTACHMENT_PDFTOPPM_BIN', 'pdftoppm', ['POPPLER_PDFTOPPM']), '-f', '1', '-l', $maxPage, '-r', '220', '-png', $absolutePath, $prefix],
            60
        );
        unset($pdftoppmOutput);

        $images = glob($tempDir . DIRECTORY_SEPARATOR . 'page-*.png') ?: [];
        if ($images !== []) {
            sort($images);
            return $images;
        }

        $magickInput = $absolutePath . '[0-' . (self::OCR_MAX_PAGES - 1) . ']';
        $magickOutputPattern = $tempDir . DIRECTORY_SEPARATOR . 'page-%03d.png';
        $magickOutput = $this->runProcess(
            [$this->getBinary('ATTACHMENT_MAGICK_BIN', 'magick', ['IMAGEMAGICK_BIN']), '-density', '220', $magickInput, $magickOutputPattern],
            80
        );
        unset($magickOutput);

        $images = glob($tempDir . DIRECTORY_SEPARATOR . 'page-*.png') ?: [];
        if ($images === []) {
            return [];
        }

        sort($images);
        return $images;
    }

    private function cleanupDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = glob($directory . DIRECTORY_SEPARATOR . '*') ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($directory);
    }

    private function runProcess(array $command, int $timeoutSeconds): string
    {
        $process = new Process($command);
        $process->setTimeout($timeoutSeconds);

        try {
            $process->run();
        } catch (\Throwable) {
            return '';
        }

        if (!$process->isSuccessful()) {
            return '';
        }

        return trim((string) $process->getOutput());
    }

    private function runTesseract(string $inputPath, int $timeoutSeconds): string
    {
        $binary = $this->getBinary('ATTACHMENT_OCR_TESSERACT_BIN', 'tesseract', ['TESSERACT_PATH']);
        foreach ($this->getOcrLanguages() as $language) {
            $output = $this->runProcess([$binary, $inputPath, 'stdout', '-l', $language, '--psm', '3'], $timeoutSeconds);
            if ($output !== '') {
                return $output;
            }
        }

        return '';
    }

    /**
     * @return string[]
     */
    private function getOcrLanguages(): array
    {
        $raw = trim((string) ($_SERVER['ATTACHMENT_OCR_LANGS'] ?? $_ENV['ATTACHMENT_OCR_LANGS'] ?? ''));
        if ($raw === '') {
            return ['fra+eng', 'eng'];
        }

        $langs = array_values(array_unique(array_filter(array_map(
            static fn (string $lang): string => trim($lang),
            explode(',', $raw)
        ))));

        return $langs !== [] ? $langs : ['fra+eng', 'eng'];
    }

    private function getBinary(string $envVar, string $default, array $aliases = []): string
    {
        $fromEnv = trim((string) ($_SERVER[$envVar] ?? $_ENV[$envVar] ?? ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        foreach ($aliases as $alias) {
            $fromAlias = trim((string) ($_SERVER[$alias] ?? $_ENV[$alias] ?? ''));
            if ($fromAlias !== '') {
                return $fromAlias;
            }
        }

        return $default;
    }

    private function normalizeText(string $text): string
    {
        $normalized = mb_convert_encoding($text, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        $normalized = str_replace(["\r\n", "\r"], "\n", $normalized);
        $normalized = preg_replace('/[^\P{C}\n\t]/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/[ \t]{2,}/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace("/\n{3,}/", "\n\n", $normalized) ?? $normalized;
        $normalized = trim($normalized);

        if ($normalized === '') {
            throw new \RuntimeException('Le fichier ne contient pas de texte exploitable.');
        }

        return mb_substr($normalized, 0, 50000);
    }
}
