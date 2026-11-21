<?php

namespace App\Services;

use Exception;
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
     * Clean PDF Text Extractor.
     */
    protected function parsePdf(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if (!$content) {
            return ['text' => '', 'data' => [], 'total_items' => 0];
        }

        $text = '';

        // Extract text streams: /Filter /FlateDecode ... stream ... endstream
        if (preg_match_all('#stream[\r\n]+(.*?)[\r\n]+endstream#s', $content, $matches)) {
            foreach ($matches[1] as $stream) {
                // Try decompressing
                $decompressed = @gzuncompress($stream);
                if ($decompressed === false) {
                    $decompressed = @gzinflate($stream);
                }

                $target = $decompressed !== false ? $decompressed : $stream;

                // Match text in (text) Tj or [(text)] TJ
                if (preg_match_all('#\((.*?)\)\s*Tj#s', $target, $tjMatches)) {
                    foreach ($tjMatches[1] as $t) {
                        $cleaned = stripcslashes($t);
                        $text .= $cleaned . ' ';
                    }
                } elseif (preg_match_all('#\[(.*?)\]\s*TJ#s', $target, $tjArrayMatches)) {
                    foreach ($tjArrayMatches[1] as $tArray) {
                        if (preg_match_all('#\((.*?)\)#s', $tArray, $inner)) {
                            foreach ($inner[1] as $t) {
                                $text .= stripcslashes($t) . ' ';
                            }
                        }
                    }
                }
            }
        }

        $text = trim(preg_replace('/\s+/', ' ', $text));

        // If extraction is sparse, include readable ASCII/UTF strings
        if (strlen($text) < 50) {
            $sanitized = preg_replace('/[^\p{Arabic}\p{L}\p{N}\s.,:\-\/]/u', ' ', $content);
            $sanitized = preg_replace('/\s+/', ' ', $sanitized);
            $text = trim($sanitized);
        }

        return [
            'text' => "### محتوى ملف PDF المرفق:\n" . mb_substr($text, 0, 15000),
            'data' => ['char_count' => mb_strlen($text)],
            'total_items' => 1,
        ];
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