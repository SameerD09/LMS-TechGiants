<?php
require 'session.php';
require 'db.php';
require 'vendor/autoload.php';

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$bookId = intval($_GET['book_id'] ?? 0);
if (!$bookId) {
    http_response_code(400);
    exit('Invalid book ID');
}

$userId = intval($_SESSION['user']['id']);

// ── Enforce PDF payment ────────────────────────────────────
$chk = $conn->prepare("SELECT id FROM pdf_purchases WHERE user_id = ? AND book_id = ?");
$chk->bind_param("ii", $userId, $bookId);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) {
    http_response_code(402);
    exit('PDF not purchased. Please buy this digital book first.');
}

// ── Enforce re-download limit (max 3) ─────────────────────
define('MAX_PDF_DOWNLOADS', 3);
$dlChk = $conn->prepare("SELECT COUNT(*) AS cnt FROM pdf_download_logs WHERE user_id = ? AND book_id = ?");
$dlChk->bind_param("ii", $userId, $bookId);
$dlChk->execute();
$dlCount = intval($dlChk->get_result()->fetch_assoc()['cnt']);
if ($dlCount >= MAX_PDF_DOWNLOADS) {
    http_response_code(429);
    exit('Download limit reached. You have already downloaded this PDF ' . MAX_PDF_DOWNLOADS . ' times.');
}

// Log this download
$dlLog = $conn->prepare("INSERT INTO pdf_download_logs (user_id, book_id) VALUES (?, ?)");
$dlLog->bind_param("ii", $userId, $bookId);
$dlLog->execute();

// Fetch book
$stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
$stmt->bind_param("i", $bookId);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();
if (!$book) {
    http_response_code(404);
    exit('Book not found');
}

// ── Language ───────────────────────────────────────────────
$lang     = (isset($_GET['lang']) && $_GET['lang'] === 'nepali') ? 'nepali' : 'english';
$isNepali = ($lang === 'nepali');

// ── HTML-escape helpers ────────────────────────────────────
function esc(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
$title       = esc($book['title']       ?? 'Unknown Title');
$author      = esc($book['author']      ?? 'Unknown Author');
$description = esc($book['description'] ?? 'No description available.');
$today       = date('F j, Y');

$rawIsbn  = $book['isbn']  ?? '';
$rawYear  = (string)($book['year'] ?? '');
$rawGenre = $book['genre'] ?? '';
$isbnDisp  = $rawIsbn  ? esc($rawIsbn)  : '&mdash;';
$yearDisp  = $rawYear  ? esc($rawYear)  : '&mdash;';
$genreDisp = $rawGenre ? esc($rawGenre) : '&mdash;';

// ── Labels & sample content ────────────────────────────────
if ($isNepali) {
    $lbl_badge     = 'टेकजाइन्ट्स पुस्तकालय — डिजिटल संस्करण';
    $lbl_digital   = 'डिजिटल प्रतिलिपि';
    $lbl_about     = 'यो पुस्तकको बारेमा';
    $lbl_details   = 'पुस्तक विवरण';
    $lbl_author_l  = 'लेखक';
    $lbl_genre_l   = 'प्रकार';
    $lbl_year_l    = 'साल';
    $lbl_isbn_l    = 'ISBN';
    $lbl_synopsis  = 'सारसंक्षेप';
    $lbl_chapter1  = 'अध्याय १';
    $lbl_footer    = 'टेकजाइन्ट्स पुस्तकालय | डिजिटल संस्करण';
    $lbl_dldate    = 'डाउनलोड मिति: ';
    $lbl_watermark = 'टेकजाइन्ट्स पुस्तकालय';
    $lbl_by        = 'लेखक: ';
    $sample_paras  = [
        '&ldquo;' . $title . '&rdquo; को यो डिजिटल संस्करण ' . $author . ' द्वारा लेखिएको हो, जुन टेकजाइन्ट्स पुस्तकालय प्रणालीमार्फत उपलब्ध गराइएको छ। यो प्रतिलिपि व्यक्तिगत पठन र शैक्षिक उद्देश्यका लागि तयार गरिएको हो।',
        'यो पुस्तकको पूर्ण सामग्री पुस्तकालयमार्फत उपलब्ध छ। थप अध्याय, टिप्पणी र पठन सामग्री टेकजाइन्ट्स पुस्तकालयमा गएर पहुँच गर्न सकिन्छ।',
        'घाम को प्रकाश झ्यालबाट आफ्नो बाटो बनाउँदै कोठाभर फैलिएको थियो। धुलोका कणहरू हावामा भासिरहेका थिए। किताबहरू थाकमा मिलाएर राखिएको थियो, मानो कसैले तिनीहरूलाई बिर्सिसकेको थियो।',
        'उनले प्रत्येक किताब दुईपल्ट पढे, तर पनि उत्तरहरूले उनलाई छोडिरहे। ज्ञान र बुद्धि दुइटा फरक कुरा हुन् — यो उनले बुझिसकेका थिए। जीवनको साँचो रहस्य सबैतिर छरिएको हुन्छ जस्तो लागिरहन्थ्यो।',
        'पुस्तकालयमा सन्नाटा थियो। धेरै समय भइसकेको थियो, कोही आएको थिएन। उनले किताब उठाएर हातमा लिए र पछाडि तर्फ हेरे — पहिलो छापले सधैँ सत्य बताउँदैन।',
    ];
} else {
    $lbl_badge     = 'TECHGIANTS LIBRARY — DIGITAL EDITION';
    $lbl_digital   = 'DIGITAL COPY';
    $lbl_about     = 'ABOUT THIS BOOK';
    $lbl_details   = 'Book Details';
    $lbl_author_l  = 'AUTHOR';
    $lbl_genre_l   = 'GENRE';
    $lbl_year_l    = 'YEAR';
    $lbl_isbn_l    = 'ISBN';
    $lbl_synopsis  = 'Synopsis';
    $lbl_chapter1  = 'Chapter 1';
    $lbl_footer    = 'TechGiants Library  |  Digital Edition';
    $lbl_dldate    = 'Downloaded on ';
    $lbl_watermark = 'TechGiants Library';
    $lbl_by        = 'by ';
    $sample_paras  = [
        'This is the digital edition of &ldquo;' . $title . '&rdquo; by ' . $author . ', made available through the TechGiants Library system. This copy has been prepared for personal reading and educational purposes.',
        'The full content of this book is available through the library. Please visit TechGiants Library to access additional chapters, annotations, and reading resources associated with this title.',
        'It was a bright cold day in April, and the clocks were striking thirteen. The wind cut through the narrow streets with quiet persistence, carrying with it the faint smell of old paper and forgotten things.',
        'She had read every book on the shelf twice, and still the answers escaped her. Knowledge, she had come to believe, was not the same as understanding — and understanding was not the same as wisdom.',
        'The library was quiet at this hour. Dust motes drifted lazily in the pale afternoon light. He pulled the book from its place on the shelf and turned it over in his hands, reading the back cover carefully.',
    ];
}

// Pre-escape all labels for HTML insertion
$e_badge     = esc($lbl_badge);
$e_digital   = esc($lbl_digital);
$e_about     = esc($lbl_about);
$e_details   = esc($lbl_details);
$e_author_l  = esc($lbl_author_l);
$e_genre_l   = esc($lbl_genre_l);
$e_year_l    = esc($lbl_year_l);
$e_isbn_l    = esc($lbl_isbn_l);
$e_synopsis  = esc($lbl_synopsis);
$e_chapter1  = esc($lbl_chapter1);
$e_footer    = esc($lbl_footer);
$e_dldate    = esc($lbl_dldate);
$e_watermark = esc($lbl_watermark);
$e_by        = esc($lbl_by);

$parasHtml = '';
foreach ($sample_paras as $p) {
    $parasHtml .= '<p class="para">' . $p . '</p>' . "\n";
}

$genrePill = $rawGenre
    ? '<p class="cover-genre">' . $genreDisp . '</p>'
    : '';

// ── Build HTML document ────────────────────────────────────
$html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>

body {
  font-family: freeserif, serif;
  color: #1a1a2e;
  font-size: 10.5pt;
  margin: 0;
  padding: 0;
}

/* ── COVER PAGE ──────────────────────────── */
.cover-cell {
  height: 297mm;
  background-color: #1a213f;
  vertical-align: middle;
  text-align: center;
  padding: 0 30mm;
}
.cover-badge {
  font-size: 7.5pt;
  letter-spacing: 2pt;
  color: #8890b0;
  text-transform: uppercase;
  margin: 0 0 8mm 0;
}
.cover-hr {
  width: 50mm;
  border: none;
  border-top: 0.3mm solid rgba(255,255,255,0.2);
  margin: 0 auto 10mm;
}
.cover-title {
  font-size: 28pt;
  font-weight: bold;
  color: #ffffff;
  line-height: 1.2;
  margin: 0 0 7mm 0;
}
.cover-author {
  font-size: 13.5pt;
  color: #ffffffb3;
  margin: 0 0 5mm 0;
  font-style: italic;
}
.cover-meta {
  font-size: 9pt;
  color: #ffffff66;
  letter-spacing: 0.5pt;
  margin: 0 0 8mm 0;
}
.cover-genre {
  font-size: 8.5pt;
  color: #ffffff8c;
  border: 0.3mm solid #ffffff47;
  display: inline-block;
  padding: 2mm 7mm;
  border-radius: 10mm;
  letter-spacing: 1pt;
  margin-bottom: 12mm;
}
.cover-wm {
  font-size: 7.5pt;
  color: #ffffff26;
  margin-top: 18mm;
}

/* ── PAGE HEADER ─────────────────────────── */
.page-header {
  background-color: #1a213f;
  padding: 7mm 18mm 6mm;
}
.header-label {
  font-size: 7pt;
  letter-spacing: 1.5pt;
  color: #ffffff80;
  text-transform: uppercase;
  margin-bottom: 2mm;
}
.header-title {
  font-size: 14pt;
  color: #ffffff;
  font-weight: bold;
}

/* ── PAGE BODY ───────────────────────────── */
.page-body {
  padding: 10mm 18mm 18mm;
}

.sec-heading {
  font-size: 13pt;
  font-weight: bold;
  color: #1a1a2e;
  margin: 0 0 2mm 0;
}
.sec-rule {
  border: none;
  border-top: 0.3mm solid #e0e0e0;
  margin: 0 0 7mm 0;
}

.detail-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 10mm;
}
.detail-cell {
  width: 50%;
  vertical-align: top;
  padding: 0 4mm 5mm 0;
}
.dl {
  font-size: 7pt;
  letter-spacing: 1pt;
  color: #bbbbbb;
  text-transform: uppercase;
  margin-bottom: 1mm;
}
.dv {
  font-size: 11pt;
  color: #1a1a2e;
  font-weight: bold;
}

.synopsis-box {
  background-color: #f8f8f6;
  border-left: 1mm solid #1a213f;
  padding: 5mm 8mm;
}
.synopsis-text {
  font-size: 10pt;
  line-height: 1.8;
  color: #333333;
}

.chapter-heading {
  font-size: 16pt;
  font-weight: bold;
  color: #1a213f;
  margin: 0 0 3mm 0;
}
.chapter-rule {
  border: none;
  border-top: 0.5mm solid #e0e0e0;
  width: 45mm;
  margin: 0 0 8mm 0;
}
.para {
  font-size: 10.5pt;
  line-height: 1.85;
  color: #222222;
  margin: 0 0 5mm 0;
  text-align: justify;
}

.page-footer {
  padding: 3mm 18mm;
  border-top: 0.3mm solid #e0e0e0;
  font-size: 7.5pt;
  color: #aaaaaa;
}

</style>
</head>
<body>

<!-- ══ PAGE 1: COVER ══════════════════════════════════════ -->
<table width="100%" cellpadding="0" cellspacing="0" style="page-break-after:always;">
  <tr>
    <td class="cover-cell">
      <p class="cover-badge">' . $e_badge . '</p>
      <hr class="cover-hr">
      <h1 class="cover-title">' . $title . '</h1>
      <p class="cover-author">' . $e_by . $author . '</p>
      <p class="cover-meta">ISBN: ' . $isbnDisp . ' &bull; ' . $yearDisp . '</p>'
      . $genrePill . '
      <p class="cover-wm">' . $e_watermark . '</p>
    </td>
  </tr>
</table>

<!-- ══ PAGE 2: ABOUT ══════════════════════════════════════ -->
<div style="page-break-after:always;">
  <div class="page-header">
    <div class="header-label">' . $e_about . '</div>
    <div class="header-title">' . $title . '</div>
  </div>
  <div class="page-body">
    <div class="sec-heading">' . $e_details . '</div>
    <hr class="sec-rule">
    <table class="detail-table">
      <tr>
        <td class="detail-cell">
          <div class="dl">' . $e_author_l . '</div>
          <div class="dv">' . $author . '</div>
        </td>
        <td class="detail-cell">
          <div class="dl">' . $e_genre_l . '</div>
          <div class="dv">' . $genreDisp . '</div>
        </td>
      </tr>
      <tr>
        <td class="detail-cell">
          <div class="dl">' . $e_year_l . '</div>
          <div class="dv">' . $yearDisp . '</div>
        </td>
        <td class="detail-cell">
          <div class="dl">' . $e_isbn_l . '</div>
          <div class="dv">' . $isbnDisp . '</div>
        </td>
      </tr>
    </table>
    <div class="sec-heading">' . $e_synopsis . '</div>
    <hr class="sec-rule">
    <div class="synopsis-box">
      <p class="synopsis-text">' . $description . '</p>
    </div>
  </div>
  <div class="page-footer">' . $e_dldate . $today . ' &nbsp;|&nbsp; ' . $e_footer . '</div>
</div>

<!-- ══ PAGE 3: SAMPLE CONTENT ════════════════════════════ -->
<div>
  <div class="page-header">
    <div class="header-label">' . $e_digital . '</div>
    <div class="header-title">' . $title . '</div>
  </div>
  <div class="page-body">
    <div class="chapter-heading">' . $e_chapter1 . '</div>
    <hr class="chapter-rule">
    ' . $parasHtml . '
  </div>
  <div class="page-footer">' . $title . ' &nbsp;|&nbsp; ' . $author . ' &nbsp;|&nbsp; ' . $e_watermark . '</div>
</div>

</body>
</html>';

// ── Generate PDF ───────────────────────────────────────────
$mpdf = new \Mpdf\Mpdf([
    'mode'             => 'utf-8',
    'format'           => 'A4',
    'margin_left'      => 0,
    'margin_right'     => 0,
    'margin_top'       => 0,
    'margin_bottom'    => 0,
    'margin_header'    => 0,
    'margin_footer'    => 0,
    'tempDir'          => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mpdf_lms',
    'autoScriptToLang' => true,
    'autoLangToFont'   => true,
]);

$mpdf->WriteHTML($html);

$safe = preg_replace('/[^a-zA-Z0-9_\- ]/u', '_', $book['title'] ?? 'book');
$safe = trim(preg_replace('/_+/', '_', $safe), '_') ?: 'book';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $safe . '.pdf"');
$mpdf->Output('', 'D');
