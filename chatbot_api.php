<?php
// chatbot_api.php
// ============================================================
// Library chatbot endpoint. Pure-PHP keyword matching — no
// external AI API. Always returns valid JSON.
// ============================================================

ob_start();
ini_set('display_errors', '0');
ini_set('log_errors',     '1');
error_reporting(E_ALL);

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/lib_retriever.php';
    require_once __DIR__ . '/session.php';

    if (empty($_SESSION['user']) || $_SESSION['user']['role'] === 'admin') {
        ob_end_clean();
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ob_end_clean();
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed.']);
        exit;
    }

    $body    = file_get_contents('php://input');
    $data    = json_decode($body, true);
    $message = isset($data['message']) ? trim((string)$data['message']) : '';

    if ($message === '') {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Please type a message.']);
        exit;
    }
    if (mb_strlen($message) > 500) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Message too long. Keep it under 500 characters.']);
        exit;
    }

    $user   = $_SESSION['user'];
    $userId = (int)($user['id'] ?? 0);

    // ── Dynamic query detection ────────────────────────────────────────────────
    // Handle personalised queries before running the retriever.
    $norm = strtolower(trim($message));

    $dynamicPatterns = [
        'my_books'      => ['what am i reading', 'my borrowed books', 'currently reading',
                            'books i have', 'reading now', 'books i borrowed',
                            'what do i have', 'my active books', 'what books do i have'],
        'due_dates'     => ['when is due', 'due date', 'when do i return', 'return by',
                            'deadline', 'overdue', 'late book', 'when must i return',
                            'when should i return', 'my due'],
        'my_favs'       => ['my favorites', 'my favourites', 'books i liked',
                            'saved books', 'my saved', 'favorited books'],
        'my_balance'    => ['my balance', 'wallet balance', 'how much money do i have',
                            'my wallet', 'check balance', 'my funds'],
        'my_history'    => ['reading history', 'books i read', 'previously borrowed',
                            'what have i read', 'past books', 'books i returned'],
        'mood_query'    => ["i'm sad", 'im sad', 'feeling sad', 'feel sad', 'i am sad',
                            'feeling down', 'feeling blue', 'im unhappy', 'i am unhappy',
                            "i'm happy", 'im happy', 'feeling happy', 'i am happy',
                            "i'm excited", 'im excited', 'feeling excited',
                            "i'm bored", 'im bored', 'feeling bored', 'i am bored', 'so bored',
                            "i'm stressed", 'im stressed', 'feeling stressed', 'i am stressed',
                            "i'm anxious", 'im anxious', 'feeling anxious', 'feeling overwhelmed',
                            "i'm lonely", 'im lonely', 'feeling lonely', 'i am lonely'],
        'best_books'    => ['best book', 'top book', 'popular book', 'must read', 'must-read',
                            'best books', 'top books', 'most popular', 'top picks',
                            'best from your library', 'best in your library', 'highly recommended',
                            'suggest me the best', 'recommend the best'],
        'about_library' => ['about this library', 'about your library',
                            'new to this library', 'new here', 'just joined',
                            'how does this library work', 'what is this library',
                            'tell me about this library', 'what can i do here',
                            'what is techgiants library', 'library overview'],
        'book_search'   => ['do you have a book', 'do you have the book', 'is it available',
                            'is that available', 'available to borrow', 'can i borrow',
                            'is available', 'check if available', 'available in library',
                            'find book called', 'find the book', 'search for book',
                            'search book', 'look for book', 'book available'],
        'author_search' => ['books by ', 'written by ', 'novels by ', 'works by ',
                            'something by ', 'any books by ', 'book by '],

        'genre_search'    => ['show me books in', 'books from genre', 'browse by genre', 'books by genre',
                              'show me fantasy', 'fantasy books', 'i want fantasy',
                              'show me fiction', 'fiction books', 'i want fiction',
                              'science fiction books', 'show me science fiction', 'show me sci-fi',
                              'sci-fi books', 'i want sci-fi', 'scifi books',
                              'self-help books', 'show me self-help', 'self help books', 'i want self-help',
                              'history books', 'show me history', 'historical books', 'i want history',
                              'biography books', 'show me biography', 'i want biography',
                              'technology books', 'show me technology', 'tech books', 'programming books', 'coding books',
                              'business books', 'show me business', 'i want business',
                              'design books', 'show me design',
                              'science books', 'show me science books',
                              'non-fiction books', 'nonfiction books', 'show me non-fiction',
                              'thriller books', 'show me thriller', 'i want thriller',
                              'mystery books', 'show me mystery', 'i want mystery',
                              'give me books in', 'show me books from'],

        'overdue_fines'   => ['how much do i owe', 'my fines', 'fine amount', 'total fine',
                              'do i owe money', 'do i have fines', 'what are my fines',
                              'what do i owe', 'pending fines', 'any fines',
                              'overdue charges', 'late fees', 'how much fine',
                              'overdue fine', 'fines outstanding', 'outstanding fine'],

        'extension_check' => ['can i extend', 'extension left', 'extensions remaining',
                              'how many extensions', 'can i still extend', 'extension available',
                              'already extended', 'times extended', 'extensions used',
                              'extensions i have', 'extend available', 'extend limit'],

        'request_status'  => ['is my request approved', 'borrow request status', 'my borrow request',
                              'pending request', 'waiting for approval', 'request status',
                              'approval status', 'any pending borrows', 'pending borrow',
                              'request pending', 'my requests', 'did my request',
                              'request been approved', 'request approved yet'],

        'book_reviews'    => ['reviews for ', 'review of ', 'what do people think of',
                              'what do people say about', 'rating for ', 'ratings for ',
                              'how many stars for', 'star rating for', 'what is the rating of',
                              'reviews of ', 'community rating for', 'book review for',
                              'what rating does', 'how good is the book'],

        'reading_stats'   => ['how many books have i read', 'reading stats', 'reading statistics',
                              'my reading stats', 'total books read', 'how many books did i read',
                              'my stats', 'books completed', 'how many have i read',
                              'books i finished', 'my reading count', 'how much have i read',
                              'total books i read', 'my reading summary'],

        'rec_by_history'  => ['recommend based on', 'suggest based on', 'based on my reading',
                              'based on what i read', 'based on my history', 'books like what i read',
                              'from my history', 'based on my taste', 'from what i\'ve read',
                              'similar to my reading', 'suggest from my history',
                              'recommend from history', 'based on my past'],

        'newest_books'    => ["what's new", 'whats new', 'newest books', 'recently added',
                              'new books', 'latest books', 'new arrivals', 'new in the library',
                              'latest additions', 'just added', 'new titles', 'latest titles'],

        'lost_book'       => ['i lost my book', 'i lost the book', 'lost my borrowed book',
                              'lost a book', 'book is lost', 'i lost a book', 'lost book',
                              'how to report lost', 'report lost book', 'report a lost book',
                              'lost book charge', 'lost book fine', 'lost book fee',
                              'what happens if i lose', 'what if i lose', 'if i lose a book',
                              'accidentally lost', 'cannot find the book', "can't find the book",
                              'misplaced the book', 'lost status', 'cancel lost report',
                              'found the book after reporting', 'lost book replacement cost'],

        'damaged_book'    => ['i damaged a book', 'i damaged the book', 'damaged a book',
                              'damaged the book', 'book is damaged', 'book got damaged',
                              'accidentally damaged', 'returned damaged', 'returning damaged',
                              'damage fine', 'damage charge', 'damage fee', 'damage fines',
                              'what if i damage', 'if i damage a book', 'what happens if i damage',
                              'book condition', 'condition fine', 'condition fee',
                              'fair condition fine', 'bad condition fine', 'damaged condition fine',
                              'my damage fines', 'check damage', 'damage history',
                              'how much for damage', 'damaged book cost', 'damaged book fine',
                              '80 percent', '80% of', 'book damaged fine', 'return condition',
                              'what condition', 'condition when returning', 'book damage charge'],
    ];

    foreach ($dynamicPatterns as $type => $patterns) {
        foreach ($patterns as $pat) {
            if (strpos($norm, $pat) !== false) {
                $response = handleDynamic($type, $userId, $conn, $norm);
                ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }
        }
    }

    // ── KB retrieval ───────────────────────────────────────────────────────────
    $retriever = new LibraryRetriever(__DIR__ . '/library_kb.json');
    $result    = $retriever->retrieve($message, 5);

    if ($result['intent'] !== null) {
        // Intent matched — return FAQ response
        $response = [
            'type'    => 'intent',
            'message' => $result['intent']['response'],
            'books'   => [],
        ];
    } elseif (empty($result['books'])) {
        // Nothing matched
        $response = [
            'type'    => 'empty',
            'message' => "I couldn't find anything matching that. Try describing a genre, topic, author, or mood — for example:\n• \"suggest a fantasy book\"\n• \"books about productivity\"\n• \"something by Orwell\"",
            'books'   => [],
        ];
    } else {
        // Books matched — enrich with DB availability
        $books = enrichWithDB($result['books'], $conn);
        $response = [
            'type'    => 'books',
            'message' => count($books) === 1
                ? "Here's a book that matches what you're looking for:"
                : "Here are some books that match what you're looking for:",
            'books'   => $books,
        ];
    }

    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;

} catch (Throwable $e) {
    $stray = ob_get_clean();
    error_log("[chatbot] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    exit;
}


// ── Enrich KB book results with live DB data ───────────────────────────────────
function enrichWithDB(array $kbResults, mysqli $conn): array
{
    if (empty($kbResults)) return [];

    $titles  = array_map(fn($r) => $r['book']['title'], $kbResults);
    $holders = implode(',', array_fill(0, count($titles), '?'));
    $types   = str_repeat('s', count($titles));

    $stmt = $conn->prepare(
        "SELECT id, title, author, genre, year, description
         FROM books WHERE title IN ($holders)"
    );
    $stmt->bind_param($types, ...$titles);
    $stmt->execute();
    $dbRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Index DB rows by normalised title
    $dbMap = [];
    foreach ($dbRows as $row) {
        $dbMap[strtolower($row['title'])] = $row;
    }

    $out = [];
    foreach ($kbResults as $r) {
        $kb         = $r['book'];
        $dbKey      = strtolower($kb['title']);
        $dbRow      = $dbMap[$dbKey] ?? null;
        $confidence = min(99, (int)round($r['score'] * 100));

        $out[] = [
            'title'         => $kb['title'],
            'author'        => $kb['author'],
            'genre'         => $kb['genre'],
            'year'          => $kb['year'],
            'description'   => $dbRow['description'] ?? $kb['description'],
            'confidence'    => $confidence,
            'in_library'    => $dbRow !== null,
            'db_id'         => $dbRow ? (int)$dbRow['id'] : null,
            'matched_terms' => $r['matched_terms'],
        ];
    }
    return $out;
}


// ── Dynamic (personalised DB) responses ───────────────────────────────────────
function handleDynamic(string $type, int $userId, mysqli $conn, string $norm = ''): array
{
    switch ($type) {

        case 'my_books': {
            $stmt = $conn->prepare(
                "SELECT b.title, b.author, b.genre, br.due_date,
                        DATEDIFF(br.due_date, CURDATE()) AS days_left
                 FROM borrowings br
                 JOIN books b ON br.book_id = b.id
                 WHERE br.user_id = ? AND br.status = 'borrowed'
                 ORDER BY br.due_date ASC"
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (empty($rows)) {
                return ['type' => 'dynamic',
                        'message' => "You don't have any books borrowed right now.\n\nHead to the **Bookstore** tab to find something you'd like to read!",
                        'books' => []];
            }
            $lines = ["You currently have **" . count($rows) . "** book(s) borrowed:\n"];
            foreach ($rows as $r) {
                $days = (int)$r['days_left'];
                $status = $days < 0
                    ? "⚠️ **OVERDUE** by " . abs($days) . " day(s)"
                    : ($days === 0 ? "⚠️ **Due TODAY**" : "Due in $days day(s) ({$r['due_date']})");
                $lines[] = "• **{$r['title']}** by {$r['author']} — $status";
            }
            return ['type' => 'dynamic', 'message' => implode("\n", $lines), 'books' => []];
        }

        case 'due_dates': {
            $stmt = $conn->prepare(
                "SELECT b.title, b.author, br.due_date,
                        DATEDIFF(br.due_date, CURDATE()) AS days_left
                 FROM borrowings br
                 JOIN books b ON br.book_id = b.id
                 WHERE br.user_id = ? AND br.status = 'borrowed'
                 ORDER BY br.due_date ASC"
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (empty($rows)) {
                return ['type' => 'dynamic',
                        'message' => "You have no active borrowings, so no due dates to worry about! 🎉",
                        'books' => []];
            }
            $lines = ["Here are your current due dates:\n"];
            foreach ($rows as $r) {
                $days = (int)$r['days_left'];
                if ($days < 0)      $flag = "⚠️ OVERDUE by " . abs($days) . " day(s)";
                elseif ($days === 0) $flag = "⚠️ Due TODAY!";
                elseif ($days <= 2)  $flag = "🔴 Due in $days day(s) — return soon!";
                else                 $flag = "🟢 Due in $days day(s) ({$r['due_date']})";
                $lines[] = "• **{$r['title']}** — $flag";
            }
            return ['type' => 'dynamic', 'message' => implode("\n", $lines), 'books' => []];
        }

        case 'my_favs': {
            $stmt = $conn->prepare(
                "SELECT b.title, b.author, b.genre
                 FROM favorites f
                 JOIN books b ON f.book_id = b.id
                 WHERE f.user_id = ?"
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (empty($rows)) {
                return ['type' => 'dynamic',
                        'message' => "You haven't saved any favorites yet.\n\nClick the ❤️ heart icon on any book in the Bookstore to save it!",
                        'books' => []];
            }
            $lines = ["Your **" . count($rows) . "** favorite book(s):\n"];
            foreach ($rows as $r) {
                $lines[] = "• **{$r['title']}** by {$r['author']} [{$r['genre']}]";
            }
            $lines[] = "\nWould you like me to suggest books similar to any of these?";
            return ['type' => 'dynamic', 'message' => implode("\n", $lines), 'books' => []];
        }

        case 'my_balance': {
            $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $balance = $row ? number_format((float)$row['balance'], 2) : '0.00';
            $msg = "Your current wallet balance is **Rs $balance**.\n\n";
            $msg .= "📖 Borrowing books is **free** — no balance needed!\n";
            $msg .= "Your wallet is used for PDF downloads (Rs 400), extension fees (Rs 80/day), and lost book charges.";
            return ['type' => 'dynamic', 'message' => $msg, 'books' => []];
        }

        case 'my_history': {
            $stmt = $conn->prepare(
                "SELECT b.title, b.author, b.genre, br.return_date
                 FROM borrowings br
                 JOIN books b ON br.book_id = b.id
                 WHERE br.user_id = ? AND br.status = 'returned'
                 ORDER BY br.return_date DESC LIMIT 8"
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (empty($rows)) {
                return ['type' => 'dynamic',
                        'message' => "You haven't returned any books yet — your reading history is empty.",
                        'books' => []];
            }
            $lines = ["Your recent reading history (" . count($rows) . " book(s)):\n"];
            foreach ($rows as $r) {
                $lines[] = "• **{$r['title']}** by {$r['author']} — returned {$r['return_date']}";
            }
            $lines[] = "\nWant me to suggest something based on what you've read?";
            return ['type' => 'dynamic', 'message' => implode("\n", $lines), 'books' => []];
        }

        case 'mood_query': {
            $moods = [
                'sad'      => ['sad', 'down', 'unhappy', 'blue', 'depressed', 'upset'],
                'happy'    => ['happy', 'excited', 'great', 'wonderful', 'joyful'],
                'bored'    => ['bored', 'restless', 'nothing to do'],
                'stressed' => ['stressed', 'anxious', 'overwhelmed', 'worried'],
                'lonely'   => ['lonely', 'alone', 'isolated'],
            ];
            $detected = 'bored';
            foreach ($moods as $mood => $keys) {
                foreach ($keys as $k) {
                    if (strpos($norm, $k) !== false) { $detected = $mood; break 2; }
                }
            }

            $moodGenres = [
                'sad'      => ['Self-Help', 'Biography', 'Fiction'],
                'happy'    => ['Fantasy', 'Fiction', 'Science Fiction'],
                'bored'    => ['Fantasy', 'Science Fiction', 'History'],
                'stressed' => ['Self-Help', 'Fiction', 'Biography'],
                'lonely'   => ['Fiction', 'Biography', 'Self-Help'],
            ];
            $moodMessages = [
                'sad'      => "I'm sorry you're feeling down. 💙 Here are some books that might comfort or inspire you:",
                'happy'    => "Love the good mood! 😊 Here are some books to keep that energy going:",
                'bored'    => "Let's fix that boredom! 🎯 These books will pull you right in:",
                'stressed' => "Take a breath — reading really helps. 🌿 Here are some books for when life feels overwhelming:",
                'lonely'   => "You're not alone — books are great companions. 📖 Here are some heartfelt reads:",
            ];

            $genres = $moodGenres[$detected];
            $holders = implode(',', array_fill(0, count($genres), '?'));
            $types   = str_repeat('s', count($genres));
            $stmt = $conn->prepare(
                "SELECT id, title, author, genre, year, description
                 FROM books WHERE genre IN ($holders)
                 ORDER BY RAND() LIMIT 4"
            );
            $stmt->bind_param($types, ...$genres);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (empty($rows)) {
                return ['type' => 'dynamic',
                        'message' => $moodMessages[$detected] . "\n\nUnfortunately I couldn't find books right now — try asking for a specific genre!",
                        'books' => []];
            }
            $books = array_map(fn($r) => [
                'title' => $r['title'], 'author' => $r['author'], 'genre' => $r['genre'],
                'year' => $r['year'], 'description' => $r['description'],
                'confidence' => null, 'in_library' => true,
                'db_id' => (int)$r['id'], 'matched_terms' => [],
            ], $rows);
            return ['type' => 'books', 'message' => $moodMessages[$detected], 'books' => $books];
        }

        case 'best_books': {
            $stmt = $conn->prepare(
                "SELECT id, title, author, genre, year, description
                 FROM books ORDER BY RAND() LIMIT 5"
            );
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (empty($rows)) {
                return ['type' => 'dynamic',
                        'message' => "Our library is being updated — please check back soon!",
                        'books' => []];
            }
            $books = array_map(fn($r) => [
                'title' => $r['title'], 'author' => $r['author'], 'genre' => $r['genre'],
                'year' => $r['year'], 'description' => $r['description'],
                'confidence' => null, 'in_library' => true,
                'db_id' => (int)$r['id'], 'matched_terms' => [],
            ], $rows);
            return ['type' => 'books', 'message' => "Here are some top picks from our library:", 'books' => $books];
        }

        case 'about_library': {
            return ['type' => 'dynamic', 'message' =>
                "Welcome to **TechGiants Library**! 📚\n\n" .
                "Here's what you can do:\n\n" .
                "**📖 Borrow Books**\n" .
                "• Browse the **Bookstore** tab and pick any book\n" .
                "• Request to borrow — admin approves quickly\n" .
                "• **Borrowing is free!** No charges for reading\n\n" .
                "**📋 Now Reading**\n" .
                "• Track your active borrowings and due dates\n" .
                "• Request extensions (up to 2×, max 7 days each)\n" .
                "• Return books and log their condition\n\n" .
                "**💰 Wallet**\n" .
                "• Used for PDF downloads (Rs 400), extension fees (Rs 80/day), and lost book charges\n" .
                "• Top up anytime from the sidebar\n\n" .
                "**❤️ Favorites & History**\n" .
                "• Save books you love with the heart icon\n" .
                "• View your full reading history in your profile\n\n" .
                "I can also **suggest books by genre, mood, or topic** — just ask!\n" .
                "What would you like to explore first?",
            'books' => []];
        }

        case 'book_search': {
            $search = $norm;
            $prefixes = [
                'do you have a book called ', 'do you have the book called ', 'do you have the book ',
                'do you have a book about ', 'do you have a book ', 'can i borrow the book ',
                'can i borrow a book called ', 'can i borrow ', 'is the book ', 'is there a book called ',
                'find book called ', 'find the book ', 'search for book ', 'search book ',
                'look for book ', 'find a book called ',
            ];
            foreach ($prefixes as $pref) {
                if (strpos($search, $pref) === 0) {
                    $search = substr($search, strlen($pref));
                    break;
                }
            }
            $search = preg_replace('/\s*(available\??|in (the |your )?library\??|for borrow\??|book\??)\s*$/', '', $search);
            $search = preg_replace('/^(is it |is there |is (the |a )?|a book called |the book called )/i', '', $search);
            $search = trim($search, '? ');

            if (strlen($search) < 2) {
                return ['type' => 'dynamic',
                        'message' => "What book are you looking for? Try asking like:\n• \"Is **Harry Potter** available?\"\n• \"Do you have **The Alchemist**?\"\n• \"Can I borrow **1984**?\"",
                        'books' => []];
            }

            $like = '%' . $conn->real_escape_string($search) . '%';
            $stmt = $conn->prepare(
                "SELECT b.id, b.title, b.author, b.genre, b.year, b.description,
                        (SELECT COUNT(*) FROM borrowings br WHERE br.book_id = b.id AND br.status = 'borrowed') AS borrowed_count
                 FROM books b WHERE b.title LIKE ? OR b.author LIKE ?
                 ORDER BY CASE WHEN b.title LIKE ? THEN 0 ELSE 1 END, b.title
                 LIMIT 4"
            );
            $stmt->bind_param('sss', $like, $like, $like);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (empty($rows)) {
                return ['type' => 'dynamic',
                        'message' => "I couldn't find \"**$search**\" in our library.\n\nTry a different spelling, or ask me to suggest books by genre!",
                        'books' => []];
            }

            $books = array_map(fn($r) => [
                'title' => $r['title'], 'author' => $r['author'], 'genre' => $r['genre'],
                'year'  => $r['year'],  'description' => $r['description'],
                'confidence' => null, 'in_library' => true,
                'available'  => (int)$r['borrowed_count'] === 0,
                'db_id' => (int)$r['id'], 'matched_terms' => [],
            ], $rows);

            if (count($rows) === 1) {
                $r    = $rows[0];
                $avail = (int)$r['borrowed_count'] === 0;
                $msg  = "**{$r['title']}** by {$r['author']}\n" .
                        ($avail ? "✅ Available — you can borrow this book right now!"
                                : "❌ Currently borrowed — check back later.");
            } else {
                $msg = "Found " . count($rows) . " books matching \"**$search**\":";
            }
            return ['type' => 'books', 'message' => $msg, 'books' => $books];
        }

        case 'author_search': {
            $authorQuery = $norm;
            $prefixes = ['books by ', 'written by ', 'novels by ', 'works by ',
                         'something by ', 'any books by ', 'what books by ', 'book by '];
            foreach ($prefixes as $pref) {
                $pos = strpos($authorQuery, $pref);
                if ($pos !== false) {
                    $authorQuery = substr($authorQuery, $pos + strlen($pref));
                    break;
                }
            }
            $authorQuery = trim($authorQuery, '? ');

            if (strlen($authorQuery) < 2) {
                return ['type' => 'dynamic',
                        'message' => "Which author are you looking for? Try:\n• \"Books by **Tolkien**\"\n• \"Written by **George Orwell**\"",
                        'books' => []];
            }

            $like = '%' . $conn->real_escape_string($authorQuery) . '%';
            $stmt = $conn->prepare(
                "SELECT b.id, b.title, b.author, b.genre, b.year, b.description,
                        (SELECT COUNT(*) FROM borrowings br WHERE br.book_id = b.id AND br.status = 'borrowed') AS borrowed_count
                 FROM books b WHERE b.author LIKE ?
                 ORDER BY b.title LIMIT 5"
            );
            $stmt->bind_param('s', $like);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (empty($rows)) {
                return ['type' => 'dynamic',
                        'message' => "I couldn't find any books by \"**$authorQuery**\" in our library.\n\nTry a different name or spelling!",
                        'books' => []];
            }

            $books = array_map(fn($r) => [
                'title' => $r['title'], 'author' => $r['author'], 'genre' => $r['genre'],
                'year'  => $r['year'],  'description' => $r['description'],
                'confidence' => null, 'in_library' => true,
                'available'  => (int)$r['borrowed_count'] === 0,
                'db_id' => (int)$r['id'], 'matched_terms' => [],
            ], $rows);

            $authorName = $rows[0]['author'];
            $msg = "Found " . count($rows) . " book(s) by **$authorName** in our library:";
            return ['type' => 'books', 'message' => $msg, 'books' => $books];
        }

        case 'genre_search': {
            // Ordered so multi-word genres (Science Fiction) are checked before single-word ones
            $genreMap = [
                'Science Fiction' => ['science fiction', 'sci-fi', 'scifi', 'sci fi'],
                'Non-Fiction'     => ['non-fiction', 'nonfiction', 'non fiction'],
                'Fantasy'         => ['fantasy'],
                'Fiction'         => ['fiction', 'literary'],
                'Self-Help'       => ['self-help', 'self help', 'selfhelp', 'personal development'],
                'History'         => ['history', 'historical', 'historic'],
                'Biography'       => ['biography', 'biograph', 'memoir'],
                'Design'          => ['design'],
                'Technology'      => ['technology', 'tech books', 'programming books', 'coding books'],
                'Business'        => ['business', 'entrepreneurship'],
                'Science'         => ['science books', 'scientific books'],
                'Mystery'         => ['mystery', 'detective'],
                'Thriller'        => ['thriller', 'suspense'],
            ];

            $detected = null;
            foreach ($genreMap as $genre => $keywords) {
                foreach ($keywords as $kw) {
                    if (strpos($norm, $kw) !== false) { $detected = $genre; break 2; }
                }
            }

            if (!$detected) {
                return ['type' => 'dynamic', 'message' =>
                    "Which genre would you like to browse? Here's what we have:\n\n" .
                    "📚 Fantasy · Fiction · Science Fiction\n" .
                    "🌍 History · Biography · Non-Fiction\n" .
                    "💡 Self-Help · Business · Technology\n" .
                    "🔬 Science · Design · Mystery · Thriller\n\n" .
                    'Just say **"Show me fantasy books"** or **"I want thriller books"**!',
                'books' => []];
            }

            $stmt = $conn->prepare(
                "SELECT b.id, b.title, b.author, b.genre, b.year, b.description,
                        (SELECT COUNT(*) FROM borrowings br WHERE br.book_id = b.id AND br.status = 'borrowed') AS borrowed_count
                 FROM books b WHERE b.genre = ? ORDER BY RAND() LIMIT 5"
            );
            $stmt->bind_param('s', $detected);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (empty($rows)) {
                return ['type' => 'dynamic',
                        'message' => "I couldn't find any **$detected** books in our library right now. Try another genre!",
                        'books' => []];
            }

            $books = array_map(fn($r) => [
                'title' => $r['title'], 'author' => $r['author'], 'genre' => $r['genre'],
                'year'  => $r['year'],  'description' => $r['description'],
                'confidence' => null, 'in_library' => true,
                'available' => (int)$r['borrowed_count'] === 0,
                'db_id' => (int)$r['id'], 'matched_terms' => [],
            ], $rows);

            return ['type' => 'books',
                    'message' => "Here are some **$detected** books from our library:",
                    'books' => $books];
        }

        case 'overdue_fines': {
            $stmt = $conn->prepare(
                "SELECT b.title, b.author, br.due_date,
                        DATEDIFF(CURDATE(), br.due_date) AS days_overdue
                 FROM borrowings br
                 JOIN books b ON br.book_id = b.id
                 WHERE br.user_id = ? AND br.status = 'borrowed' AND br.due_date < CURDATE()
                 ORDER BY br.due_date ASC"
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (empty($rows)) {
                return ['type' => 'dynamic',
                        'message' => "Great news — you have **no overdue fines**! 🎉\n\nAll your borrowed books are within their due dates.",
                        'books' => []];
            }

            $total = 0;
            $lines = ["You have **" . count($rows) . "** overdue book(s):\n"];
            foreach ($rows as $r) {
                $days   = (int)$r['days_overdue'];
                $fine   = $days * 100;
                $total += $fine;
                $lines[] = "• **{$r['title']}** — {$days} day(s) overdue → fine: **Rs " . number_format($fine) . "**";
            }
            $lines[] = "\n**Total outstanding: Rs " . number_format($total) . "**";
            $lines[] = "\nFines are deducted from your wallet when you return the book. Return soon — fines grow at Rs 100/day.";

            return ['type' => 'dynamic', 'message' => implode("\n", $lines), 'books' => []];
        }

        case 'extension_check': {
            $stmt = $conn->prepare(
                "SELECT br.id AS borrowing_id, b.title, b.author, br.due_date,
                        COALESCE(SUM(e.extend_days), 0) AS days_extended,
                        DATEDIFF(br.due_date, CURDATE()) AS days_left
                 FROM borrowings br
                 JOIN books b ON br.book_id = b.id
                 LEFT JOIN borrow_extensions e ON e.borrowing_id = br.id
                 WHERE br.user_id = ? AND br.status = 'borrowed'
                 GROUP BY br.id, b.title, b.author, br.due_date
                 ORDER BY br.due_date ASC"
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (empty($rows)) {
                return ['type' => 'dynamic',
                        'message' => "You don't have any active borrowings to extend right now.",
                        'books' => []];
            }

            $lines = ["**Extension status for your borrowed books:**\n"];
            foreach ($rows as $r) {
                $used      = (int)$r['days_extended'];
                $remaining = max(0, 7 - $used);
                $daysLeft  = (int)$r['days_left'];
                $dueStatus = $daysLeft < 0
                    ? "⚠️ Overdue by " . abs($daysLeft) . " day(s)"
                    : ($daysLeft === 0 ? "⚠️ Due today" : "Due in $daysLeft day(s)");

                $extStatus = $remaining === 0
                    ? "❌ No extension days left (used all 7 days)"
                    : "✅ **$remaining day(s)** of extension remaining (used $used/7) — Rs 80/day";

                $lines[] = "• **{$r['title']}** — $dueStatus\n  $extStatus";
            }
            $lines[] = "\nTo extend: go to the **Now Reading** tab → click the book → **Extend** (max 7 total extra days, Rs 80/day).";

            return ['type' => 'dynamic', 'message' => implode("\n", $lines), 'books' => []];
        }

        case 'request_status': {
            $stmt = $conn->prepare(
                "SELECT br.id, b.title, b.author, b.genre, br.borrow_days, br.requested_at
                 FROM borrow_requests br
                 JOIN books b ON br.book_id = b.id
                 WHERE br.user_id = ? AND br.status = 'pending'
                 ORDER BY br.requested_at DESC"
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (empty($rows)) {
                return ['type' => 'dynamic',
                        'message' => "You have **no pending borrow requests** right now.\n\n" .
                                     "If a request was already approved, the book is in your **Now Reading** tab.\n" .
                                     "To request a new book, browse the **Bookstore** and click **Request Borrow**.",
                        'books' => []];
            }

            $lines = ["You have **" . count($rows) . "** pending borrow request(s) waiting for admin approval:\n"];
            foreach ($rows as $r) {
                $date    = date('M j, Y', strtotime($r['requested_at']));
                $daysStr = $r['borrow_days'] ? " — for {$r['borrow_days']} day(s)" : '';
                $lines[] = "• **{$r['title']}** by {$r['author']}$daysStr\n  Requested: $date · ⏳ Awaiting approval";
            }
            $lines[] = "\nOnce approved, the book will appear in your **Now Reading** tab automatically.";

            return ['type' => 'dynamic', 'message' => implode("\n", $lines), 'books' => []];
        }

        case 'book_reviews': {
            $search  = $norm;
            $prefixes = [
                'what do people think of ', 'what do people say about ',
                'what is the rating of ', 'what are the reviews for ',
                'reviews for the book ', 'reviews for ', 'review of the book ',
                'review of ', 'ratings for the book ', 'ratings for ',
                'rating for the book ', 'rating for ', 'reviews of the book ',
                'reviews of ', 'book review for ', 'community rating for ',
                'how many stars for ', 'star rating for ', 'how good is the book ',
            ];
            foreach ($prefixes as $pref) {
                if (strpos($search, $pref) === 0) {
                    $search = substr($search, strlen($pref));
                    break;
                }
            }
            $search = trim($search, '?. ');

            if (strlen($search) < 2) {
                return ['type' => 'dynamic',
                        'message' => "Which book would you like to see reviews for? Try:\n" .
                                     "• \"Reviews for **Dune**\"\n" .
                                     "• \"What do people think of **1984**?\"\n" .
                                     "• \"Rating for **Atomic Habits**\"",
                        'books' => []];
            }

            $like     = '%' . $conn->real_escape_string($search) . '%';
            $bkStmt   = $conn->prepare("SELECT id, title, author FROM books WHERE title LIKE ? LIMIT 1");
            $bkStmt->bind_param('s', $like);
            $bkStmt->execute();
            $book = $bkStmt->get_result()->fetch_assoc();
            $bkStmt->close();

            if (!$book) {
                return ['type' => 'dynamic',
                        'message' => "I couldn't find a book called \"**$search**\" in our library.\n\nCheck the spelling or try a partial title.",
                        'books' => []];
            }

            $bookId = (int)$book['id'];

            $avgStmt = $conn->prepare("SELECT AVG(rating) AS avg_r, COUNT(*) AS total FROM book_reviews WHERE book_id = ?");
            $avgStmt->bind_param('i', $bookId);
            $avgStmt->execute();
            $avgRow = $avgStmt->get_result()->fetch_assoc();
            $avgStmt->close();

            if (!(int)$avgRow['total']) {
                return ['type' => 'dynamic',
                        'message' => "**{$book['title']}** by {$book['author']} has no reviews yet.\n\nBorrow it and be the first to leave a rating after you return it!",
                        'books' => []];
            }

            $revStmt = $conn->prepare(
                "SELECT r.rating, r.review, r.created_at, u.first_name
                 FROM book_reviews r JOIN users u ON u.id = r.user_id
                 WHERE r.book_id = ? ORDER BY r.created_at DESC LIMIT 4"
            );
            $revStmt->bind_param('i', $bookId);
            $revStmt->execute();
            $reviews = $revStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $revStmt->close();

            $avg   = (float)$avgRow['avg_r'];
            $stars = str_repeat('★', (int)round($avg)) . str_repeat('☆', 5 - (int)round($avg));
            $lines = [
                "**{$book['title']}** by {$book['author']}\n",
                "$stars **" . number_format($avg, 1) . "/5** based on {$avgRow['total']} review(s)\n",
            ];
            foreach ($reviews as $rev) {
                $rStars  = str_repeat('★', (int)$rev['rating']) . str_repeat('☆', 5 - (int)$rev['rating']);
                $revText = $rev['review'] ? '"' . $rev['review'] . '"' : '(no written review)';
                $lines[] = "• $rStars **{$rev['first_name']}** — $revText";
            }

            return ['type' => 'dynamic', 'message' => implode("\n", $lines), 'books' => []];
        }

        case 'reading_stats': {
            $s1 = $conn->prepare("SELECT COUNT(*) AS cnt FROM borrowings WHERE user_id = ? AND status = 'returned'");
            $s1->bind_param('i', $userId); $s1->execute();
            $totalRead = (int)$s1->get_result()->fetch_assoc()['cnt'];
            $s1->close();

            $s2 = $conn->prepare("SELECT COUNT(*) AS cnt FROM borrowings WHERE user_id = ? AND status = 'borrowed'");
            $s2->bind_param('i', $userId); $s2->execute();
            $currentlyReading = (int)$s2->get_result()->fetch_assoc()['cnt'];
            $s2->close();

            $s3 = $conn->prepare(
                "SELECT b.genre, COUNT(*) AS cnt
                 FROM borrowings br JOIN books b ON br.book_id = b.id
                 WHERE br.user_id = ? AND br.status = 'returned'
                 GROUP BY b.genre ORDER BY cnt DESC LIMIT 1"
            );
            $s3->bind_param('i', $userId); $s3->execute();
            $topGenreRow = $s3->get_result()->fetch_assoc();
            $s3->close();

            $s4 = $conn->prepare("SELECT AVG(rating) AS avg_r, COUNT(*) AS total_r FROM book_reviews WHERE user_id = ?");
            $s4->bind_param('i', $userId); $s4->execute();
            $ratingRow = $s4->get_result()->fetch_assoc();
            $s4->close();

            if ($totalRead === 0 && $currentlyReading === 0) {
                return ['type' => 'dynamic',
                        'message' => "You haven't borrowed any books yet — your reading journey is just beginning! 📖\n\nHead to the **Bookstore** tab to find your first book.",
                        'books' => []];
            }

            $lines = ["**Your Reading Stats:**\n"];
            $lines[] = "📚 **Books completed:** $totalRead";
            $lines[] = "📖 **Currently reading:** $currentlyReading";
            if ($topGenreRow) {
                $lines[] = "❤️ **Favourite genre:** {$topGenreRow['genre']} ({$topGenreRow['cnt']} book(s))";
            }
            if ((int)$ratingRow['total_r'] > 0) {
                $lines[] = "⭐ **Reviews given:** {$ratingRow['total_r']} (avg: " . number_format((float)$ratingRow['avg_r'], 1) . "/5)";
            }
            $lines[] = "\nKeep reading — every book counts! 🌟";

            return ['type' => 'dynamic', 'message' => implode("\n", $lines), 'books' => []];
        }

        case 'rec_by_history': {
            $s1 = $conn->prepare(
                "SELECT b.genre, COUNT(*) AS cnt
                 FROM borrowings br JOIN books b ON br.book_id = b.id
                 WHERE br.user_id = ? AND br.status = 'returned'
                 GROUP BY b.genre ORDER BY cnt DESC LIMIT 1"
            );
            $s1->bind_param('i', $userId); $s1->execute();
            $topRow = $s1->get_result()->fetch_assoc();
            $s1->close();

            if (!$topRow) {
                return ['type' => 'dynamic',
                        'message' => "You haven't returned any books yet, so I don't have enough reading history to personalise recommendations.\n\n" .
                                     "Borrow and return a few books first, then ask me again! In the meantime try:\n" .
                                     "• **\"Suggest me a book\"** for general picks\n" .
                                     "• **\"Show me fantasy books\"** to browse by genre",
                        'books' => []];
            }

            $favGenre = $topRow['genre'];

            $s2 = $conn->prepare(
                "SELECT b.id, b.title, b.author, b.genre, b.year, b.description,
                        (SELECT COUNT(*) FROM borrowings br2 WHERE br2.book_id = b.id AND br2.status = 'borrowed') AS borrowed_count
                 FROM books b
                 WHERE b.genre = ?
                 AND b.id NOT IN (SELECT book_id FROM borrowings WHERE user_id = ? AND status = 'borrowed')
                 ORDER BY RAND() LIMIT 4"
            );
            $s2->bind_param('si', $favGenre, $userId);
            $s2->execute();
            $rows = $s2->get_result()->fetch_all(MYSQLI_ASSOC);
            $s2->close();

            if (empty($rows)) {
                return ['type' => 'dynamic',
                        'message' => "You've read or are reading all the **$favGenre** books we have — impressive! 🏆\n\nWant to try a new genre? Say **\"Show me [genre] books\"**.",
                        'books' => []];
            }

            $books = array_map(fn($r) => [
                'title' => $r['title'], 'author' => $r['author'], 'genre' => $r['genre'],
                'year'  => $r['year'],  'description' => $r['description'],
                'confidence' => null, 'in_library' => true,
                'available' => (int)$r['borrowed_count'] === 0,
                'db_id' => (int)$r['id'], 'matched_terms' => [],
            ], $rows);

            return ['type' => 'books',
                    'message' => "Based on your reading history, you love **$favGenre** — here are some picks you haven't read yet:",
                    'books' => $books];
        }

        case 'newest_books': {
            $stmt = $conn->prepare(
                "SELECT b.id, b.title, b.author, b.genre, b.year, b.description,
                        (SELECT COUNT(*) FROM borrowings br WHERE br.book_id = b.id AND br.status = 'borrowed') AS borrowed_count
                 FROM books b ORDER BY b.created_at DESC LIMIT 5"
            );
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (empty($rows)) {
                return ['type' => 'dynamic', 'message' => "Our library is being updated — please check back soon!", 'books' => []];
            }

            $books = array_map(fn($r) => [
                'title' => $r['title'], 'author' => $r['author'], 'genre' => $r['genre'],
                'year'  => $r['year'],  'description' => $r['description'],
                'confidence' => null, 'in_library' => true,
                'available' => (int)$r['borrowed_count'] === 0,
                'db_id' => (int)$r['id'], 'matched_terms' => [],
            ], $rows);

            return ['type' => 'books', 'message' => "Here are the **latest additions** to our library:", 'books' => $books];
        }

        case 'damaged_book': {
            // Show actual damage fines charged to this user
            $stmt = $conn->prepare(
                "SELECT wt.description, ABS(wt.amount) AS fine_amount, wt.created_at
                 FROM wallet_transactions wt
                 WHERE wt.user_id = ? AND wt.type = 'damage_fine'
                 ORDER BY wt.created_at DESC LIMIT 5"
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $fineRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Also check currently borrowed books pending return
            $stmt2 = $conn->prepare(
                "SELECT b.title, b.author, b.price, br.due_date,
                        DATEDIFF(br.due_date, CURDATE()) AS days_left
                 FROM borrowings br
                 JOIN books b ON br.book_id = b.id
                 WHERE br.user_id = ? AND br.status = 'borrowed'
                 ORDER BY br.due_date ASC"
            );
            $stmt2->bind_param('i', $userId);
            $stmt2->execute();
            $borrowed = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt2->close();

            $lines = [];

            if (!empty($fineRows)) {
                $lines[] = "**Your damage fine history:**\n";
                $total = 0;
                foreach ($fineRows as $r) {
                    $date   = date('M j, Y', strtotime($r['created_at']));
                    $fine   = number_format((float)$r['fine_amount'], 2);
                    $total += (float)$r['fine_amount'];
                    $lines[] = "• Rs **$fine** — {$r['description']} ($date)";
                }
                if (count($fineRows) > 1) {
                    $lines[] = "\n**Total damage fines charged:** Rs " . number_format($total, 2);
                }
                $lines[] = "";
            }

            $lines[] = "**Damage fines when returning a book:**\n";
            $lines[] = "• **Excellent / Good** — No fine ✅";
            $lines[] = "• **Fair condition** — Rs 200";
            $lines[] = "• **Bad condition** — Rs 500";
            $lines[] = "• **Damaged** — 80% of the book's price\n";
            $lines[] = "⚠️ The **admin verifies** the actual condition when processing your return — the fine is based on the admin's assessment, not just what you select.\n";
            $lines[] = "**How to return a damaged book:**";
            $lines[] = "1. Go to **Now Reading** tab";
            $lines[] = "2. Click the book → **Return Book**";
            $lines[] = "3. Select the honest condition";
            $lines[] = "4. Add a description *(required for bad/damaged)*";
            $lines[] = "5. Submit — admin reviews and charges the fine";

            if (!empty($borrowed)) {
                $lines[] = "\n**Your currently borrowed book(s):**";
                foreach ($borrowed as $b) {
                    $days  = (int)$b['days_left'];
                    $price = number_format((float)$b['price'], 0);
                    $due   = $days < 0 ? "⚠️ OVERDUE by " . abs($days) . " day(s)" : "due in $days day(s)";
                    $lines[] = "• **{$b['title']}** — $due (book price: Rs $price → damaged fine would be Rs " . number_format((float)$b['price'] * 0.8, 0) . ")";
                }
            }

            return ['type' => 'dynamic', 'message' => implode("\n", $lines), 'books' => []];
        }

        case 'lost_book': {
            // Check if user has any lost-reported or confirmed-lost books
            $stmt = $conn->prepare(
                "SELECT b.title, b.author, b.price, br.status, br.lost_reported
                 FROM borrowings br
                 JOIN books b ON br.book_id = b.id
                 WHERE br.user_id = ? AND (br.lost_reported = 1 OR br.status = 'lost')
                 ORDER BY br.id DESC LIMIT 5"
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (!empty($rows)) {
                $pending   = [];
                $confirmed = [];
                foreach ($rows as $r) {
                    if ($r['status'] === 'lost') $confirmed[] = $r;
                    else                          $pending[]   = $r;
                }

                $lines = [];

                if (!empty($pending)) {
                    $lines[] = "**⏳ Pending — awaiting admin confirmation:**\n";
                    foreach ($pending as $r) {
                        $price   = number_format((float)($r['price'] ?? 1500), 2);
                        $lines[] = "• **{$r['title']}** by {$r['author']} — Rs $price will be charged once confirmed";
                    }
                    $lines[] = "\n**What you should do now:**";
                    $lines[] = "• 🔍 **Found the book?** Contact the admin immediately to cancel the report and avoid the charge";
                    $lines[] = "• 💰 **Make sure your wallet has enough balance** — Rs $price will be deducted when the admin confirms";
                    $lines[] = "• ⏳ **If it's truly lost** — just wait, the admin will process the report shortly";
                    $lines[] = "";
                }

                if (!empty($confirmed)) {
                    $lines[] = "**✅ Confirmed lost — already processed:**\n";
                    foreach ($confirmed as $r) {
                        $price   = number_format((float)($r['price'] ?? 1500), 2);
                        $lines[] = "• **{$r['title']}** by {$r['author']} — Rs $price was charged to your wallet";
                    }
                    $lines[] = "\n**What you should do now:**";
                    $lines[] = "• 📋 The borrowing is **fully closed** — no further action needed";
                    $lines[] = "• 💰 Check your **wallet balance** to see your remaining funds";
                    $lines[] = "• 📚 You can still **borrow other books** — head to the Bookstore tab";
                    $lines[] = "";
                }

                // Always append the general process info below the status
                $lines[] = "---";
                $lines[] = "**How to report a lost book:**\n";
                $lines[] = "1. Go to the **Now Reading** tab";
                $lines[] = "2. Click on the borrowed book";
                $lines[] = "3. Click **Report as Lost**";
                $lines[] = "4. Confirm the report\n";
                $lines[] = "**What happens next:**";
                $lines[] = "• Your report is sent to the admin for confirmation";
                $lines[] = "• Once the admin confirms, the **full replacement cost** of the book is deducted from your wallet";
                $lines[] = "• The borrowing is marked as lost and closed\n";
                $lines[] = "💡 **If you find the book** before the admin confirms, contact the admin right away to cancel the report and avoid the charge.";
                return ['type' => 'dynamic', 'message' => implode("\n", $lines), 'books' => []];
            }

            // No active lost reports — explain the process
            $msg  = "**How to report a lost book:**\n\n";
            $msg .= "1. Go to the **Now Reading** tab\n";
            $msg .= "2. Click on the borrowed book\n";
            $msg .= "3. Click **Report as Lost**\n";
            $msg .= "4. Confirm the report\n\n";
            $msg .= "**What happens next:**\n";
            $msg .= "• Your report is sent to the admin for confirmation\n";
            $msg .= "• Once the admin confirms, the **full replacement cost** of the book is deducted from your wallet\n";
            $msg .= "• The borrowing is marked as lost and closed\n\n";
            $msg .= "💡 **If you find the book** before the admin confirms, contact the admin right away to cancel the report and avoid the charge.\n\n";
            $msg .= "You currently have no active lost book reports.";
            return ['type' => 'dynamic', 'message' => $msg, 'books' => []];
        }

    }

    return ['type' => 'dynamic', 'message' => 'I could not fetch that information.', 'books' => []];
}
