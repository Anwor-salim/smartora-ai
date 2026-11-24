<?php

namespace App\Services;

use Exception;
use Symfony\Component\Process\Process;
use ZipArchive;

class DocumentParserService
{
    /**
     * Parse uploaded file and return extracted text and structured data.
     *
     * @param string $filePath Absolute path to the file
     * @param string $extension File extension (xlsx, xls, csv, pdf, txt)
     * @return array ['text' => string, 'data' => array, 'total_items' => int]
     */
    public function parseFile(string $filePath, string $extension): array
    {
        $extension = strtolower($extension);

        return match ($extension) {
            'xlsx' => $this->parseXlsx($filePath),
            'csv' => $this->parseCsv($filePath),
            'pdf' => $this->parsePdf($filePath),
            'txt', 'text', 'json' => $this->parsePlainText($filePath),
            default => [
                'text' => file_get_contents($filePath) ?: '',
                'data' => [],
                'total_items' => 0,
            ],
        };
    }

    /**
     * Native, high-performance XLSX Parser using ZipArchive and XML.
     */
    protected function parseXlsx(string $filePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new Exception("Unable to open Excel (.xlsx) file.");
        }

        // 1. Read Shared Strings
        $sharedStrings = [];
        $sharedXmlContent = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXmlContent) {
            $xml = simplexml_load_string($sharedXmlContent);
            if ($xml) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } elseif (isset($si->r)) {
                        $text = '';
                        foreach ($si->r as $r) {
                            $text .= (string) $r->t;
                        }
                        $sharedStrings[] = $text;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        // 2. Read first worksheet
        $sheetXmlContent = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (!$sheetXmlContent) {
            $zip->close();
            return ['text' => '', 'data' => [], 'total_items' => 0];
        }

        $xml = simplexml_load_string($sheetXmlContent);
        $zip->close();

        if (!$xml || !isset($xml->sheetData->row)) {
            return ['text' => '', 'data' => [], 'total_items' => 0];
        }

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $rowData = [];
            foreach ($row->c as $cell) {
                $cellType = (string) $cell['t'];
                $val = (string) $cell->v;

                if ($cellType === 's' && isset($sharedStrings[(int) $val])) {
                    $cellValue = $sharedStrings[(int) $val];
                } elseif ($cellType === 'inlineStr' && isset($cell->is->t)) {
                    $cellValue = (string) $cell->is->t;
                } else {
                    $cellValue = $val;
                }

                $rowData[] = trim($cellValue);
            }
            if (!empty(array_filter($rowData))) {
                $rows[] = $rowData;
            }
        }

        if (empty($rows)) {
            return ['text' => '', 'data' => [], 'total_items' => 0];
        }

        $headers = array_shift($rows);
        $items = [];
        $markdownRows = [];

        // Build header markdown
        $markdownRows[] = '| ' . implode(' | ', $headers) . ' |';
        $markdownRows[] = '| ' . implode(' | ', array_fill(0, count($headers), '---')) . ' |';

        foreach ($rows as $row) {
            $item = [];
            foreach ($headers as $idx => $header) {
                $item[$header ?: "col_$idx"] = $row[$idx] ?? '';
            }
            $items[] = $item;
            $markdownRows[] = '| ' . implode(' | ', $row) . ' |';
        }

        $textRepresentation = "### بيانات جدول الإكسل المرفق:\n" . implode("\n", $markdownRows);

        return [
            'text' => $textRepresentation,
            'data' => array_slice($items, 0, 500), // structured data preview
            'total_items' => count($items),
        ];
    }

    /**
     * CSV Parser.
     */
    protected function parseCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new Exception("Unable to open CSV file.");
        }

        $rows = [];
        while (($data = fgetcsv($handle, 2000, ",")) !== false) {
            if (!empty(array_filter($data))) {
                $rows[] = array_map('trim', $data);
            }
        }
        fclose($handle);

        if (empty($rows)) {
            return ['text' => '', 'data' => [], 'total_items' => 0];
        }

        $headers = array_shift($rows);
        $items = [];
        $markdownRows = [];

        $markdownRows[] = '| ' . implode(' | ', $headers) . ' |';
        $markdownRows[] = '| ' . implode(' | ', array_fill(0, count($headers), '---')) . ' |';

        foreach ($rows as $row) {
            $item = [];
            foreach ($headers as $idx => $header) {
                $item[$header ?: "col_$idx"] = $row[$idx] ?? '';
            }
            $items[] = $item;
            $markdownRows[] = '| ' . implode(' | ', $row) . ' |';
        }

        return [
            'text' => "### بيانات CSV المرفقة:\n" . implode("\n", $markdownRows),
            'data' => array_slice($items, 0, 500),
            'total_items' => count($items),
        ];
    }

    /**
     * Clean PDF Text Extractor using Poppler pdftotext with automatic visual-Arabic BiDi repair.
     */
    protected function parsePdf(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("الملف غير موجود في المسار المحدد.");
        }

        $text = '';

        // 1. Primary extractor: poppler pdftotext (UTF-8, handles ReportLab ASCII85/FlateDecode & ToUnicode CMaps)
        try {
            $process = new Process(['pdftotext', '-enc', 'UTF-8', $filePath, '-']);
            $process->setTimeout(20);
            $process->run();

            if ($process->isSuccessful()) {
                $text = (string) $process->getOutput();
            }
        } catch (\Throwable $e) {
            // Fall through to PHP fallback
        }

        // 2. Fallback extractor: smalot/pdfparser if available
        if (trim($text) === '' && class_exists(\Smalot\PdfParser\Parser::class)) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($filePath);
                $text = (string) $pdf->getText();
            } catch (\Throwable $e) {
                // Fall through
            }
        }

        // 3. UTF-8 safe sanitization and Arabic Presentation Forms normalization
        $text = @iconv('UTF-8', 'UTF-8//IGNORE', $text) ?: '';
        if (class_exists(\Normalizer::class) && $text !== '') {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_KC);
            if (is_string($normalized)) {
                $text = $normalized;
            }
        }

        // 4. Strip invisible BiDi directional formatting characters
        $text = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $text) ?? $text;

        // 5. Normalize line breaks, remove standalone page numbers around form feeds, and strip control chars
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/\n*\s*\d{1,3}\s*\n*\f\s*/u', "\n", $text) ?? $text;
        $text = str_replace("\f", "\n", $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text) ?? $text;

        // 6. Detect and repair visual-order reversed Arabic (common in ReportLab/PDF generators)
        if ($this->isReversedArabic($text)) {
            $lines = explode("\n", $text);
            foreach ($lines as &$line) {
                if (preg_match('/\p{Arabic}/u', $line)) {
                    $line = $this->reverseVisualRtlLine($line);
                }
            }
            unset($line);
            $text = implode("\n", $lines);
        }

        // 7. Normalize horizontal spacing while preserving paragraph breaks
        $lines = array_map(
            fn (string $l): string => trim(preg_replace('/[ \t]+/u', ' ', $l) ?? $l),
            explode("\n", $text)
        );
        $text = implode("\n", $lines);
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        $text = trim($text);

        if (mb_strlen($text) < 5) {
            throw new Exception('تعذر استخراج نص مقروء من ملف PDF. قد يكون الملف عبارة عن صور ممسوحة ضوئياً (Scanned PDF) بدون طبقة نصية.');
        }

        $sections = $this->extractPdfSections($text);

        return [
            'text' => "### محتوى ملف PDF المرفق:\n" . mb_substr($text, 0, 50000),
            'data' => array_slice($sections, 0, 500),
            'total_items' => max(1, count($sections)),
        ];
    }

    /**
     * Detect whether Arabic text was extracted in reversed visual order (e.g. "برعلا ىفشتسم").
     */
    protected function isReversedArabic(string $text): bool
    {
        if (!preg_match('/\p{Arabic}/u', $text)) {
            return false;
        }

        preg_match_all('/\p{Arabic}{2,}/u', $text, $wordMatches);
        $words = $wordMatches[0] ?? [];
        if (count($words) < 4) {
            return false;
        }

        $normalScore = 0;
        $reversedScore = 0;

        $normalExact = ['في', 'من', 'على', 'إلى', 'الى', 'هذا', 'هذه', 'عن', 'مع', 'أو', 'او', 'بعد', 'قبل', 'داخل', 'باسم', 'غير', 'متوفر', 'العيادة', 'عيادة', 'المستشفى'];
        $reversedExact = ['يف', 'نم', 'ىلع', 'ىلإ', 'ىلا', 'اذه', 'هذه', 'نع', 'عم', 'وأ', 'وا', 'دعب', 'لبق', 'لخاد', 'مساب', 'ريغ', 'رفوتم', 'ةدايعلا', 'ةدايع', 'ىفشتسملا'];

        foreach ($words as $w) {
            $first = mb_substr($w, 0, 1);
            $last = mb_substr($w, -1);
            $first2 = mb_substr($w, 0, 2);
            $last2 = mb_substr($w, -2);

            // Taa marboota (ة) or Alef maksura (ى) normally appear at the END of words, never at the START
            if ($last === 'ة' || $last === 'ى') {
                $normalScore += 2;
            }
            if ($first === 'ة' || $first === 'ى') {
                $reversedScore += 3;
            }

            // Definite article "ال" normally starts a word; reversed it ends with "لا" (excluding "لا" itself or verbs ending in لا)
            if ($first2 === 'ال' && mb_strlen($w) >= 4) {
                $normalScore += 2;
            }
            if ($last2 === 'لا' && mb_strlen($w) >= 4) {
                $reversedScore += 2;
            }

            if (in_array($w, $normalExact, true)) {
                $normalScore += 4;
            }
            if (in_array($w, $reversedExact, true)) {
                $reversedScore += 4;
            }
        }

        return $reversedScore > $normalScore;
    }

    /**
     * Reverse a visual-order RTL line back to logical reading order while keeping LTR runs intact.
     */
    protected function reverseVisualRtlLine(string $line): string
    {
        $trimmed = trim($line);
        if ($trimmed === '') {
            return '';
        }

        // Split line keeping LTR segments (list numbers like "1.", phone numbers, English/Latin codes, URLs) intact
        $parts = preg_split(
            '/(\d+\.(?=\s|$)|[A-Za-z0-9]+(?:[._\/:@+\-][A-Za-z0-9]+)*)/u',
            $trimmed,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        if (!$parts) {
            return $trimmed;
        }

        $mirrorMap = [
            '(' => ')',
            ')' => '(',
            '[' => ']',
            ']' => '[',
            '{' => '}',
            '}' => '{',
            '<' => '>',
            '>' => '<',
            '«' => '»',
            '»' => '«',
        ];

        $reversedParts = array_reverse($parts);
        $out = '';

        foreach ($reversedParts as $seg) {
            if (preg_match('/^(?:\d+\.|[A-Za-z0-9]+(?:[._\/:@+\-][A-Za-z0-9]+)*)$/u', $seg)) {
                $out .= $seg;
            } else {
                $chars = preg_split('//u', $seg, -1, PREG_SPLIT_NO_EMPTY);
                if ($chars) {
                    $chars = array_reverse($chars);
                    foreach ($chars as &$ch) {
                        if (isset($mirrorMap[$ch])) {
                            $ch = $mirrorMap[$ch];
                        }
                    }
                    unset($ch);
                    $out .= implode('', $chars);
                }
            }
        }

        // Clean punctuation spacing after token reversal
        $out = preg_replace('/\s+([،,:؛.])/u', '$1', $out) ?? $out;
        $out = preg_replace('/:\s*:/u', ':', $out) ?? $out;
        $out = preg_replace('/(\p{Arabic})(\d{5,})/u', '$1: $2', $out) ?? $out;
        $out = preg_replace('/([\)»\]])(\p{Arabic})/u', '$1 $2', $out) ?? $out;
        $out = preg_replace('/[ \t]+/u', ' ', $out) ?? $out;

        return trim($out);
    }

    /**
     * Convert extracted PDF text into a sequential list of structured section records.
     */
    protected function extractPdfSections(string $text): array
    {
        $blocks = preg_split("/\n{2,}/u", $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!$blocks) {
            return [];
        }

        $sections = [];
        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            $lines = array_values(array_filter(
                array_map('trim', explode("\n", $block)),
                fn (string $l): bool => $l !== ''
            ));

            if (empty($lines)) {
                continue;
            }

            $heading = array_shift($lines);
            $details = !empty($lines) ? implode("\n", $lines) : $heading;

            $sections[] = [
                'القسم / البند' => mb_substr($heading, 0, 200),
                'التفاصيل' => mb_substr($details, 0, 1500),
            ];
        }

        return $sections;
    }

    /**
     * Plain text / Markdown parser.
     */
    protected function parsePlainText(string $filePath): array
    {
        $content = file_get_contents($filePath) ?: '';
        return [
            'text' => $content,
            'data' => [],
            'total_items' => 1,
        ];
    }
}