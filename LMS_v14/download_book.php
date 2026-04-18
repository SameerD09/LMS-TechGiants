<?php
require 'session.php';
require 'db.php';

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$bookId = intval($_GET['book_id'] ?? 0);
if (!$bookId) {
    http_response_code(400);
    exit('Invalid book ID');
}

// Fetch book from DB
$stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
$stmt->bind_param("i", $bookId);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();

if (!$book) {
    http_response_code(404);
    exit('Book not found');
}

$title       = $book['title']       ?? 'Unknown Title';
$author      = $book['author']      ?? 'Unknown Author';
$genre       = $book['genre']       ?? '';
$year        = $book['year']        ?? '';
$isbn        = $book['isbn']        ?? '—';
$description = $book['description'] ?? 'No description available.';
$today       = date('F j, Y');

// Safe filename
$safeFilename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $title);
$safeFilename = preg_replace('/_+/', '_', $safeFilename);
$safeFilename = trim($safeFilename, '_');

// ────────────────────────────────────────────────────────────────────────────
// Minimal pure-PHP PDF builder — no external libraries required
// ────────────────────────────────────────────────────────────────────────────

class SimplePDF {
    private $objects  = [];
    private $offsets  = [];
    private $pages    = [];
    private $objCount = 0;
    private $width    = 595;   // A4 width  in points
    private $height   = 842;   // A4 height in points

    // Add a raw PDF object, return its object number
    private function addObj($content) {
        $this->objCount++;
        $this->objects[$this->objCount] = $content;
        return $this->objCount;
    }

    // Encode text safely for PDF streams
    private function esc($text) {
        $text = mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
        $text = str_replace(['\\','(',')',"\r"], ['\\\\','\\(','\\)',''], $text);
        return $text;
    }

    // Wrap text to fit within maxWidth (approx chars per line for Helvetica)
    private function wrapText($text, $fontSize, $maxWidth) {
        $charsPerLine = (int)($maxWidth / ($fontSize * 0.5));
        $words  = explode(' ', $text);
        $lines  = [];
        $line   = '';
        foreach ($words as $word) {
            $test = $line === '' ? $word : $line . ' ' . $word;
            if (strlen($test) <= $charsPerLine) {
                $line = $test;
            } else {
                if ($line !== '') $lines[] = $line;
                // Hard-wrap very long words
                while (strlen($word) > $charsPerLine) {
                    $lines[] = substr($word, 0, $charsPerLine);
                    $word    = substr($word, $charsPerLine);
                }
                $line = $word;
            }
        }
        if ($line !== '') $lines[] = $line;
        return $lines;
    }

    // Build a page stream and return stream string
    private function buildStream($commands) {
        return implode("\n", $commands);
    }

    public function generate($title, $author, $genre, $year, $isbn, $description, $today) {

        // ── Page 1: Cover ──────────────────────────────────────────────────
        $coverCmds = [];

        // Dark navy background (full page rectangle)
        $coverCmds[] = "0.102 0.129 0.192 rg";           // dark navy fill
        $coverCmds[] = "0 0 {$this->width} {$this->height} re f";

        // Subtle gradient feel — slightly lighter top band
        $coverCmds[] = "0.118 0.149 0.235 rg";
        $coverCmds[] = "0 650 {$this->width} 192 re f";

        // White thin rule
        $coverCmds[] = "1 1 1 RG";
        $coverCmds[] = "0.3 w";
        $coverCmds[] = "178 490 m 417 490 l S";

        // Library badge text
        $coverCmds[] = "0.6 0.6 0.6 rg";
        $coverCmds[] = "BT";
        $coverCmds[] = "/F2 8 Tf";
        $coverCmds[] = "1 0 0 1 " . (($this->width / 2) - 100) . " 560 Tm";
        $coverCmds[] = "(TECHGIANTS LIBRARY -- DIGITAL EDITION) Tj";
        $coverCmds[] = "ET";

        // Title — large, centered, white
        $titleEsc  = $this->esc($title);
        $titleSize = 36;
        $titleLen  = strlen($titleEsc);
        // Approximate center x
        $charW    = $titleSize * 0.52;
        $titleX   = max(60, ($this->width - ($titleLen * $charW)) / 2);
        // If title is long, reduce font
        if ($titleLen > 20) { $titleSize = 28; $charW = $titleSize * 0.52; $titleX = max(60, ($this->width - ($titleLen * $charW)) / 2); }
        if ($titleLen > 28) { $titleSize = 22; $titleX = 60; }

        $coverCmds[] = "1 1 1 rg";
        $coverCmds[] = "BT";
        $coverCmds[] = "/F1 {$titleSize} Tf";
        $coverCmds[] = "1 0 0 1 {$titleX} 490 Tm";
        $coverCmds[] = "({$titleEsc}) Tj";
        $coverCmds[] = "ET";

        // Author
        $authorEsc = $this->esc("by " . $author);
        $authorLen = strlen($authorEsc);
        $authorX   = max(80, ($this->width - ($authorLen * 0.45 * 14)) / 2);
        $coverCmds[] = "0.8 0.8 0.8 rg";
        $coverCmds[] = "BT";
        $coverCmds[] = "/F2 14 Tf";
        $coverCmds[] = "1 0 0 1 {$authorX} 455 Tm";
        $coverCmds[] = "({$authorEsc}) Tj";
        $coverCmds[] = "ET";

        // ISBN + Year
        $metaEsc = $this->esc("ISBN: {$isbn}   ·   {$year}");
        $metaLen = strlen($metaEsc);
        $metaX   = max(80, ($this->width - ($metaLen * 0.45 * 9)) / 2);
        $coverCmds[] = "0.5 0.5 0.5 rg";
        $coverCmds[] = "BT";
        $coverCmds[] = "/F2 9 Tf";
        $coverCmds[] = "1 0 0 1 {$metaX} 432 Tm";
        $coverCmds[] = "({$metaEsc}) Tj";
        $coverCmds[] = "ET";

        // Genre pill (outlined box)
        if ($genre) {
            $genreEsc  = $this->esc($genre);
            $genreW    = strlen($genreEsc) * 6 + 24;
            $genreX    = ($this->width - $genreW) / 2;
            $coverCmds[] = "0 0 0 rg";
            $coverCmds[] = "0.55 0.55 0.55 RG";
            $coverCmds[] = "0.5 w";
            $coverCmds[] = "{$genreX} 400 {$genreW} 20 re S";
            $coverCmds[] = "0.65 0.65 0.65 rg";
            $coverCmds[] = "BT";
            $coverCmds[] = "/F2 9 Tf";
            $textX = $genreX + 12;
            $coverCmds[] = "1 0 0 1 {$textX} 406 Tm";
            $coverCmds[] = "({$genreEsc}) Tj";
            $coverCmds[] = "ET";
        }

        // Watermark bottom right
        $coverCmds[] = "0.35 0.35 0.35 rg";
        $coverCmds[] = "BT";
        $coverCmds[] = "/F2 8 Tf";
        $coverCmds[] = "1 0 0 1 400 30 Tm";
        $coverCmds[] = "(TechGiants Library) Tj";
        $coverCmds[] = "ET";

        $coverStream = $this->buildStream($coverCmds);

        // ── Page 2: About / Info ───────────────────────────────────────────
        $aboutCmds = [];

        // White background
        $aboutCmds[] = "1 1 1 rg";
        $aboutCmds[] = "0 0 {$this->width} {$this->height} re f";

        // Dark top bar
        $aboutCmds[] = "0.102 0.129 0.192 rg";
        $aboutCmds[] = "0 {$this->height} {$this->width} -80 re f";

        // Header label
        $aboutCmds[] = "1 1 1 rg";
        $aboutCmds[] = "BT /F2 8 Tf 1 0 0 1 60 " . ($this->height - 30) . " Tm (ABOUT THIS BOOK) Tj ET";

        // Title in header
        $aboutCmds[] = "BT /F1 14 Tf 1 0 0 1 60 " . ($this->height - 55) . " Tm (" . $this->esc($title) . ") Tj ET";

        // Section: Book Details
        $y = $this->height - 120;

        $aboutCmds[] = "0.2 0.2 0.2 rg";
        $aboutCmds[] = "BT /F1 13 Tf 1 0 0 1 60 {$y} Tm (Book Details) Tj ET";
        $y -= 8;
        // underline
        $aboutCmds[] = "0.7 0.7 0.7 RG 0.5 w 60 {$y} m 280 {$y} l S";
        $y -= 22;

        $fields = [
            ["Author",  $author],
            ["Genre",   $genre],
            ["Year",    (string)$year],
            ["ISBN",    $isbn],
        ];
        foreach ($fields as $f) {
            $aboutCmds[] = "0.5 0.5 0.5 rg";
            $aboutCmds[] = "BT /F2 9 Tf 1 0 0 1 60 {$y} Tm (" . $this->esc(strtoupper($f[0])) . ") Tj ET";
            $aboutCmds[] = "0.15 0.15 0.15 rg";
            $aboutCmds[] = "BT /F2 11 Tf 1 0 0 1 60 " . ($y - 14) . " Tm (" . $this->esc($f[1]) . ") Tj ET";
            $y -= 44;
        }

        // Synopsis section
        $y -= 10;
        $aboutCmds[] = "0.2 0.2 0.2 rg";
        $aboutCmds[] = "BT /F1 13 Tf 1 0 0 1 60 {$y} Tm (Synopsis) Tj ET";
        $y -= 8;
        $aboutCmds[] = "0.7 0.7 0.7 RG 0.5 w 60 {$y} m 535 {$y} l S";
        $y -= 20;

        // Desc box background
        $descLines = $this->wrapText($description, 10, 450);
        $descBoxH  = count($descLines) * 16 + 28;
        $boxTop    = $y;
        $aboutCmds[] = "0.97 0.97 0.95 rg";
        $aboutCmds[] = "55 " . ($boxTop - $descBoxH + 10) . " 485 {$descBoxH} re f";
        // Left accent bar
        $aboutCmds[] = "0.102 0.129 0.192 rg";
        $aboutCmds[] = "55 " . ($boxTop - $descBoxH + 10) . " 4 {$descBoxH} re f";

        $y -= 14;
        foreach ($descLines as $line) {
            $aboutCmds[] = "0.2 0.2 0.2 rg";
            $aboutCmds[] = "BT /F2 10 Tf 1 0 0 1 68 {$y} Tm (" . $this->esc($line) . ") Tj ET";
            $y -= 16;
        }

        // Footer
        $aboutCmds[] = "0.75 0.75 0.75 RG 0.5 w 60 50 m 535 50 l S";
        $aboutCmds[] = "0.6 0.6 0.6 rg";
        $aboutCmds[] = "BT /F2 8 Tf 1 0 0 1 60 36 Tm (Downloaded on " . $this->esc($today) . "  |  TechGiants Library  |  Digital Edition) Tj ET";

        $aboutStream = $this->buildStream($aboutCmds);

        // ── Page 3: Sample Content ─────────────────────────────────────────
        $contentCmds = [];
        $contentCmds[] = "1 1 1 rg";
        $contentCmds[] = "0 0 {$this->width} {$this->height} re f";

        // Top bar
        $contentCmds[] = "0.102 0.129 0.192 rg";
        $contentCmds[] = "0 {$this->height} {$this->width} -80 re f";
        $contentCmds[] = "1 1 1 rg";
        $contentCmds[] = "BT /F2 8 Tf 1 0 0 1 60 " . ($this->height - 30) . " Tm (DIGITAL COPY) Tj ET";
        $contentCmds[] = "BT /F1 14 Tf 1 0 0 1 60 " . ($this->height - 55) . " Tm (" . $this->esc($title) . ") Tj ET";

        $cy = $this->height - 115;

        // Chapter 1 heading
        $contentCmds[] = "0.102 0.129 0.192 rg";
        $contentCmds[] = "BT /F1 16 Tf 1 0 0 1 60 {$cy} Tm (Chapter 1) Tj ET";
        $cy -= 10;
        $contentCmds[] = "0.75 0.75 0.75 RG 0.5 w 60 {$cy} m 200 {$cy} l S";
        $cy -= 22;

        $sampleParagraphs = [
            "This is the digital edition of \"{$title}\" by {$author}, made available through the TechGiants Library system. This copy has been prepared for personal reading and educational purposes.",
            "The full content of this book is available through the library. Please visit the TechGiants Library to access additional chapters, annotations, and reading resources associated with this title.",
            "It was a bright cold day in April, and the clocks were striking thirteen. The wind cut through the narrow streets with quiet persistence, carrying with it the faint smell of old paper and forgotten things. Somewhere in the distance, a door opened and closed.",
            "She had read every book on the shelf twice, and still the answers escaped her. Knowledge, she had come to believe, was not the same as understanding — and understanding was not the same as wisdom. These were three entirely different countries, each requiring a separate journey to reach.",
            "The library was quiet at this hour. Dust motes drifted lazily in the pale afternoon light that slanted through the high windows. He pulled the book from its place on the shelf and turned it over in his hands, reading the back cover with the careful attention of someone who knows that the first impression of a book is rarely the final one.",
        ];

        foreach ($sampleParagraphs as $para) {
            $lines = $this->wrapText($para, 10, 450);
            foreach ($lines as $line) {
                if ($cy < 80) break 2;
                $contentCmds[] = "0.15 0.15 0.15 rg";
                $contentCmds[] = "BT /F2 10.5 Tf 1 0 0 1 60 {$cy} Tm (" . $this->esc($line) . ") Tj ET";
                $cy -= 16;
            }
            $cy -= 10; // paragraph spacing
        }

        // Footer
        $contentCmds[] = "0.75 0.75 0.75 RG 0.5 w 60 50 m 535 50 l S";
        $contentCmds[] = "0.6 0.6 0.6 rg";
        $contentCmds[] = "BT /F2 8 Tf 1 0 0 1 60 36 Tm (" . $this->esc($title . "  |  " . $author . "  |  TechGiants Library") . ") Tj ET";
        $contentCmds[] = "BT /F2 8 Tf 1 0 0 1 510 36 Tm (3) Tj ET";

        $contentStream = $this->buildStream($contentCmds);

        // ── Assemble PDF ───────────────────────────────────────────────────
        // Object 1: Catalog (added last — needs pages obj num)
        // Object 2: Font Helvetica-Bold (F1)
        $font1 = $this->addObj("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>");
        // Object 3: Font Helvetica (F2)
        $font2 = $this->addObj("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>");

        // Page streams
        $cs1 = $this->addObj("<< /Length " . strlen($coverStream) . " >>\nstream\n" . $coverStream . "\nendstream");
        $cs2 = $this->addObj("<< /Length " . strlen($aboutStream) . " >>\nstream\n" . $aboutStream . "\nendstream");
        $cs3 = $this->addObj("<< /Length " . strlen($contentStream) . " >>\nstream\n" . $contentStream . "\nendstream");

        $fontDict = "<< /F1 {$font1} 0 R /F2 {$font2} 0 R >>";
        $pageResources = "<< /Font {$fontDict} >>";

        // Page objects
        $pg1 = $this->addObj("<< /Type /Page /MediaBox [0 0 {$this->width} {$this->height}] /Resources {$pageResources} /Contents {$cs1} 0 R /Parent 8 0 R >>");
        $pg2 = $this->addObj("<< /Type /Page /MediaBox [0 0 {$this->width} {$this->height}] /Resources {$pageResources} /Contents {$cs2} 0 R /Parent 8 0 R >>");
        $pg3 = $this->addObj("<< /Type /Page /MediaBox [0 0 {$this->width} {$this->height}] /Resources {$pageResources} /Contents {$cs3} 0 R /Parent 8 0 R >>");

        // Pages dict (object 8)
        $pagesObj = $this->addObj("<< /Type /Pages /Kids [{$pg1} 0 R {$pg2} 0 R {$pg3} 0 R] /Count 3 >>");

        // Catalog (object 9)
        $catalogObj = $this->addObj("<< /Type /Catalog /Pages {$pagesObj} 0 R >>");

        // ── Write PDF bytes ─────────────────────────────────────────────────
        $out  = "%PDF-1.4\n";
        $out .= "%\xe2\xe3\xcf\xd3\n"; // binary comment to flag binary file

        foreach ($this->objects as $num => $content) {
            $this->offsets[$num] = strlen($out);
            $out .= "{$num} 0 obj\n{$content}\nendobj\n";
        }

        // Cross-reference table
        $xrefOffset = strlen($out);
        $out .= "xref\n";
        $out .= "0 " . ($this->objCount + 1) . "\n";
        $out .= "0000000000 65535 f \n";
        foreach ($this->offsets as $num => $offset) {
            $out .= str_pad($offset, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $out .= "trailer\n<< /Size " . ($this->objCount + 1) . " /Root {$catalogObj} 0 R >>\n";
        $out .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $out;
    }
}

// Generate the PDF
$pdf = new SimplePDF();
$pdfBytes = $pdf->generate($title, $author, $genre, $year, $isbn, $description, $today);

// Stream to browser as download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $safeFilename . '.pdf"');
header('Content-Length: ' . strlen($pdfBytes));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $pdfBytes;
exit;
?>
