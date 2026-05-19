<?php
// lib_retriever.php
// ============================================================
// Lexical retrieval over the library knowledge base.
// Pure-PHP: tokenisation -> intent check -> weighted keyword
// matching -> ranked book results. No external API calls.
// ============================================================

class LibraryRetriever
{
    private $kb;

    private static $stopwords = [
        'i','me','my','have','had','am','is','are','a','an','the',
        'of','to','for','and','or','with','some','also','really',
        'just','very','been','got','feel','feeling','having','since',
        'about','around','it','its','this','that','these','those',
        'on','in','at','from','was','were','do','does','did',
        'would','could','should','can','want','like','need', 'get',
        'give','tell','show','find','know','think','look','see',
        'something', 'anything', 'please', 'maybe', 'suggest'
    ];

    public function __construct($kbPath)
    {
        if (!file_exists($kbPath)) {
            throw new Exception("Knowledge base not found at: $kbPath");
        }
        $raw = file_get_contents($kbPath);
        $this->kb = json_decode($raw, true);
        if ($this->kb === null) {
            throw new Exception("Invalid JSON in knowledge base: " . json_last_error_msg());
        }
    }

    // ── Normalise user text ────────────────────────────────────────────────────
    private function normalise($text)
    {
        $text = strtolower($text);
        $text = str_replace(["\u{2019}", "\u{2018}", "`"], "'", $text);
        $text = preg_replace('/\s+/', ' ', trim($text));
        return $text;
    }

    // ── Tokenise into words with stopwords removed ─────────────────────────────
    private function tokens($normText)
    {
        $clean = preg_replace('/[^a-z0-9 \']+/', ' ', $normText);
        $parts = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
        $out = [];
        foreach ($parts as $p) {
            if (in_array($p, self::$stopwords, true)) continue;
            if (strlen($p) < 2) continue;
            $out[] = $p;
        }
        return $out;
    }

    // ── Phrase containment check ───────────────────────────────────────────────
    private function phraseMatches($phrase, $normText)
    {
        $phrase = strtolower(trim($phrase));
        if ($phrase === '') return false;

        // Exact substring with word boundaries
        $pattern = '/(^|[^a-z0-9])' . preg_quote($phrase, '/') . '([^a-z0-9]|$)/';
        if (preg_match($pattern, $normText)) return true;

        // Fuzzy fallback for multi-word phrases
        return $this->fuzzyPhraseMatch($phrase, $normText);
    }

    private static $phraseFiller = [
        'when','in','of','a','an','the','and','or','to','for','with',
        'at','on','my','i','is','are','am','it','its','this','that',
        'be','been','have','has','had','do','does'
    ];

    private function fuzzyPhraseMatch($phrase, $normText)
    {
        $words = preg_split('/\s+/', preg_replace('/[^a-z0-9 ]+/', ' ', $phrase));
        $content = [];
        foreach ($words as $w) {
            if ($w === '') continue;
            if (in_array($w, self::$phraseFiller, true)) continue;
            if (strlen($w) < 3) continue;
            $content[] = $this->stem($w);
        }
        if (count($content) < 2) return false;

        $textWords = preg_split('/\s+/', preg_replace('/[^a-z0-9 ]+/', ' ', $normText));
        $textStems = [];
        foreach ($textWords as $w) {
            if ($w === '' || strlen($w) < 2) continue;
            $textStems[$this->stem($w)] = true;
        }
        foreach ($content as $stem) {
            if (!isset($textStems[$stem])) return false;
        }
        return true;
    }

    // ── Conservative rule-based stemmer ───────────────────────────────────────
    private function stem($w)
    {
        $len = strlen($w);
        if ($len >= 6 && substr($w, -3) === 'ing') return substr($w, 0, $len - 3);
        if ($len >= 6 && substr($w, -3) === 'ies') return substr($w, 0, $len - 3) . 'y';
        if ($len >= 5 && substr($w, -2) === 'ed')  return substr($w, 0, $len - 2);
        if ($len >= 5 && substr($w, -2) === 'es')  return substr($w, 0, $len - 2);
        if ($len >= 5 && substr($w, -1) === 'y')   return substr($w, 0, $len - 1);
        if ($len >= 5 && substr($w, -1) === 'e')   return substr($w, 0, $len - 1);
        if ($len >= 4 && substr($w, -1) === 's' && substr($w, -2) !== 'ss')
            return substr($w, 0, $len - 1);
        return $w;
    }

    // ── Check intents (FAQ matching) ───────────────────────────────────────────
    public function checkIntent($normText)
    {
        foreach ($this->kb['intents'] as $intent) {
            foreach ($intent['keywords'] as $kw) {
                if ($this->phraseMatches($kw, $normText)) {
                    return $intent;
                }
            }
        }
        return null;
    }

    // ── Score a single book against the query ─────────────────────────────────
    private function scoreBook($book, $normText, $userTokens)
    {
        $totalWeight   = 0;
        $matchedWeight = 0;
        $matchedTerms  = [];

        // Direct author/genre match gives a strong base signal
        $authorNorm = strtolower($book['author']);
        $genreNorm  = strtolower($book['genre']);
        $titleNorm  = strtolower($book['title']);

        // Check if query directly mentions the author name
        $authorWords = preg_split('/\s+/', preg_replace('/[^a-z]+/', ' ', $authorNorm));
        foreach ($authorWords as $aw) {
            if (strlen($aw) > 3 && strpos($normText, $aw) !== false) {
                $matchedWeight += 2.5;
                $totalWeight   += 2.5;
                $matchedTerms[] = $book['author'];
                break;
            }
        }
        $totalWeight += 2.5; // author slot always counts

        // Check if query mentions genre
        if (strpos($normText, $genreNorm) !== false) {
            $matchedWeight += 2.0;
            $matchedTerms[] = $book['genre'];
        }
        $totalWeight += 2.0; // genre slot always counts

        // Score topics
        foreach ($book['topics'] as $topic) {
            $w = $topic['weight'];
            $totalWeight += $w;
            $hit = false;
            foreach ($topic['keywords'] as $kw) {
                if ($this->phraseMatches($kw, $normText)) {
                    $hit = true;
                    $matchedTerms[] = $kw;
                    break;
                }
                // Single distinctive word fallback
                if (!str_contains($kw, ' ') && strlen($kw) >= 4
                    && in_array($kw, $userTokens, true)) {
                    $hit = true;
                    $matchedTerms[] = $kw;
                    break;
                }
            }
            if ($hit) $matchedWeight += $w;
        }

        if ($totalWeight == 0 || $matchedWeight == 0) return null;

        $recall     = $matchedWeight / $totalWeight;
        $countBonus = min(0.15, count($matchedTerms) * 0.04);
        $score      = $recall + $countBonus;

        return [
            'book'          => $book,
            'score'         => $score,
            'matched_terms' => array_values(array_unique($matchedTerms)),
        ];
    }

    // ── Top-K book retrieval ───────────────────────────────────────────────────
    public function retrieve($userText, $k = 5)
    {
        $norm   = $this->normalise($userText);
        $tokens = $this->tokens($norm);

        // 1. Check intents first
        $intent = $this->checkIntent($norm);
        if ($intent !== null) {
            return ['intent' => $intent, 'books' => [], 'tokens' => $tokens];
        }

        // 2. Score every book
        $scored = [];
        foreach ($this->kb['books'] as $book) {
            $r = $this->scoreBook($book, $norm, $tokens);
            if ($r !== null) $scored[] = $r;
        }

        // 3. Sort descending
        usort($scored, function ($a, $b) {
            return $a['score'] <=> $b['score'] ? ($a['score'] < $b['score'] ? 1 : -1) : 0;
        });

        // 4. Confidence threshold — drop very weak matches
        $filtered = array_filter($scored, fn($r) => $r['score'] >= 0.18);

        return [
            'intent' => null,
            'books'  => array_slice(array_values($filtered), 0, $k),
            'tokens' => $tokens,
        ];
    }
}
