<?php

declare(strict_types=1);

namespace Shopologic\Core\Export;

/**
 * Export manager for various file formats
 */
class ExportManager
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'output_path' => 'storage/exports',
            'temp_path' => 'storage/temp',
            'pdf_engine' => 'dompdf', // or 'wkhtmltopdf'
            'csv_delimiter' => ',',
            'csv_enclosure' => '"'
        ], $config);
    }

    /**
     * Export data to CSV
     */
    public function exportToCsv(array $data, string $filename): string
    {
        $outputPath = $this->getOutputPath($filename . '.csv');
        
        $file = fopen($outputPath, 'w');
        
        // Write UTF-8 BOM for Excel compatibility
        fwrite($file, "\xEF\xBB\xBF");
        
        // Write headers if data is not empty
        if (!empty($data)) {
            $headers = array_keys(reset($data));
            fputcsv($file, $headers, $this->config['csv_delimiter'], $this->config['csv_enclosure']);
            
            // Write data rows
            foreach ($data as $row) {
                fputcsv($file, $row, $this->config['csv_delimiter'], $this->config['csv_enclosure']);
            }
        }
        
        fclose($file);
        
        return $outputPath;
    }

    /**
     * Export data to Excel (using pure PHP)
     */
    public function exportToExcel(array $data, string $filename): string
    {
        $outputPath = $this->getOutputPath($filename . '.xlsx');
        
        // Create simple XLSX structure
        $excel = new SimpleXLSXWriter();
        
        // Add headers
        if (!empty($data)) {
            $headers = array_keys(reset($data));
            $excel->addRow($headers);
            
            // Add data rows
            foreach ($data as $row) {
                $excel->addRow(array_values($row));
            }
        }
        
        $excel->save($outputPath);
        
        return $outputPath;
    }

    /**
     * Export HTML to PDF
     */
    public function exportToPdf(string $html, string $filename): string
    {
        $outputPath = $this->getOutputPath($filename . '.pdf');
        
        // Use simple HTML to PDF conversion
        $pdf = new SimplePDFGenerator();
        $pdf->loadHtml($html);
        $pdf->render();
        
        file_put_contents($outputPath, $pdf->output());
        
        return $outputPath;
    }

    /**
     * Export data to JSON
     */
    public function exportToJson(array $data, string $filename): string
    {
        $outputPath = $this->getOutputPath($filename . '.json');
        
        file_put_contents(
            $outputPath,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        
        return $outputPath;
    }

    /**
     * Export data to XML
     */
    public function exportToXml(array $data, string $filename, string $rootElement = 'data'): string
    {
        $outputPath = $this->getOutputPath($filename . '.xml');
        
        $xml = new \SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><{$rootElement}></{$rootElement}>");
        
        $this->arrayToXml($data, $xml);
        
        // Format output
        $dom = new \DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        
        file_put_contents($outputPath, $dom->saveXML());
        
        return $outputPath;
    }

    /**
     * Create ZIP archive from files
     */
    public function createZip(array $files, string $filename): string
    {
        $outputPath = $this->getOutputPath($filename . '.zip');
        
        $zip = new \ZipArchive();
        $zip->open($outputPath, \ZipArchive::CREATE);
        
        foreach ($files as $file) {
            if (is_array($file)) {
                $zip->addFile($file['path'], $file['name']);
            } else {
                $zip->addFile($file, basename($file));
            }
        }
        
        $zip->close();
        
        return $outputPath;
    }

    /**
     * Stream file as download
     */
    public function streamDownload(string $filepath, string $filename = null): void
    {
        if (!file_exists($filepath)) {
            throw new \Exception("File not found: {$filepath}");
        }
        
        $filename = $filename ?: basename($filepath);
        $filesize = filesize($filepath);
        $mimetype = $this->getMimeType($filepath);
        
        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set headers
        header('Content-Type: ' . $mimetype);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $filesize);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Stream file
        readfile($filepath);
        exit;
    }

    // Private methods

    private function getOutputPath(string $filename): string
    {
        $directory = $this->config['output_path'];
        
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        return $directory . '/' . $filename;
    }

    private function arrayToXml(array $data, \SimpleXMLElement &$xml): void
    {
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                $key = 'item';
            }
            
            if (is_array($value)) {
                $subnode = $xml->addChild($key);
                $this->arrayToXml($value, $subnode);
            } else {
                $xml->addChild($key, htmlspecialchars((string)$value));
            }
        }
    }

    private function getMimeType(string $filepath): string
    {
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            'csv' => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'pdf' => 'application/pdf',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'zip' => 'application/zip'
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
}

/**
 * Simple XLSX writer (minimal implementation)
 */
class SimpleXLSXWriter
{
    private array $sheets = [];
    private int $currentSheet = 0;

    public function __construct()
    {
        $this->sheets[0] = ['name' => 'Sheet1', 'data' => []];
    }

    public function addRow(array $row): void
    {
        $this->sheets[$this->currentSheet]['data'][] = $row;
    }

    public function save(string $filename): void
    {
        $zip = new \ZipArchive();
        $zip->open($filename, \ZipArchive::CREATE);
        
        // Add required structure
        $this->addContentTypes($zip);
        $this->addRelationships($zip);
        $this->addWorkbook($zip);
        $this->addWorksheet($zip);
        $this->addStyles($zip);
        $this->addSharedStrings($zip);
        
        $zip->close();
    }

    private function addContentTypes(\ZipArchive $zip): void
    {
        $content = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
    <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>';
        
        $zip->addFromString('[Content_Types].xml', $content);
    }

    private function addRelationships(\ZipArchive $zip): void
    {
        // Root relationships
        $content = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
        
        $zip->addFromString('_rels/.rels', $content);
        
        // Workbook relationships
        $content = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>';
        
        $zip->addFromString('xl/_rels/workbook.xml.rels', $content);
    }

    private function addWorkbook(\ZipArchive $zip): void
    {
        $content = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Sheet1" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>';
        
        $zip->addFromString('xl/workbook.xml', $content);
    }

    private function addWorksheet(\ZipArchive $zip): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>';
        
        $rowNum = 1;
        foreach ($this->sheets[0]['data'] as $row) {
            $xml .= '<row r="' . $rowNum . '">';
            $colNum = 0;
            foreach ($row as $cell) {
                $cellRef = $this->getCellReference($rowNum, $colNum);
                $xml .= '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . htmlspecialchars((string)$cell) . '</t></is></c>';
                $colNum++;
            }
            $xml .= '</row>';
            $rowNum++;
        }
        
        $xml .= '</sheetData></worksheet>';
        
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
    }

    private function addStyles(\ZipArchive $zip): void
    {
        $content = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="1"><font/></fonts>
    <fills count="1"><fill/></fills>
    <borders count="1"><border/></borders>
    <cellXfs count="1"><xf/></cellXfs>
</styleSheet>';
        
        $zip->addFromString('xl/styles.xml', $content);
    }

    private function addSharedStrings(\ZipArchive $zip): void
    {
        $content = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="0" uniqueCount="0"></sst>';
        
        $zip->addFromString('xl/sharedStrings.xml', $content);
    }

    private function getCellReference(int $row, int $col): string
    {
        $letter = '';
        while ($col >= 0) {
            $letter = chr(65 + ($col % 26)) . $letter;
            $col = intval($col / 26) - 1;
        }
        return $letter . $row;
    }
}

/**
 * Simple PDF generator (minimal implementation)
 */
class SimplePDFGenerator
{
    private string $html = '';
    private array $options = [];

    public function loadHtml(string $html): void
    {
        $this->html = $html;
    }

    public function render(): void
    {
        // Process HTML for PDF generation
    }

    public function output(): string
    {
        // Very basic PDF generation
        $pdf = "%PDF-1.4\n";
        
        // Add basic PDF structure
        $content = $this->htmlToText($this->html);
        
        // This is a simplified example - real PDF generation is much more complex
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /MediaBox [0 0 612 792] /Contents 5 0 R >>\nendobj\n";
        $pdf .= "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
        
        $stream = "BT\n/F1 12 Tf\n50 750 Td\n(" . $this->escapeString($content) . ") Tj\nET";
        $pdf .= "5 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream\nendobj\n";
        
        $pdf .= "xref\n0 6\n0000000000 65535 f\n";
        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\n";
        $pdf .= "startxref\n0\n%%EOF";
        
        return $pdf;
    }

    private function htmlToText(string $html): string
    {
        // Remove HTML tags
        $text = strip_tags($html);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }

    private function escapeString(string $text): string
    {
        return str_replace(['(', ')', '\\'], ['\\(', '\\)', '\\\\'], $text);
    }
}