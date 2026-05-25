<?php
// chatbot_public_api.php
// ============================================================
// Public chatbot endpoint for login/signup pages.
// No authentication required. Pure keyword matching.
// ============================================================

ob_start();
ini_set('display_errors', '0');
ini_set('log_errors',     '1');
error_reporting(E_ALL);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$body    = file_get_contents('php://input');
$data    = json_decode($body, true);
$message = isset($data['message']) ? trim((string)$data['message']) : '';

if ($message === '') {
    ob_end_clean();
    echo json_encode(['error' => 'Please type a message.']);
    exit;
}
if (mb_strlen($message) > 500) {
    ob_end_clean();
    echo json_encode(['error' => 'Message too long. Keep it under 500 characters.']);
    exit;
}

$norm = strtolower(trim($message));

// Each intent: array of substring patterns and a response string.
// The first matching intent wins.
$intents = [

    // ── Greetings ──────────────────────────────────────────────────────────
    [
        'patterns' => ['hello', 'hi ', 'hi!', 'hey ', 'hey!', 'good morning', 'good afternoon',
                       'good evening', 'howdy', 'greetings', "what's up", 'whats up', 'sup '],
        'response' =>
            "Hi there! 👋 I'm **Gem**, TechGiants Library's assistant.\n\n" .
            "I can help you with:\n" .
            "• How to log in or sign up\n" .
            "• Forgot password or username\n" .
            "• Account rules & requirements\n" .
            "• What TechGiants Library offers\n\n" .
            "What do you need help with?",
    ],

    // ── How to log in ──────────────────────────────────────────────────────
    [
        'patterns' => ['how to log in', 'how do i log in', 'how to login', 'how do i login',
                       'how to sign in', 'how do i sign in', 'login steps', 'sign in steps',
                       'login process', 'how can i login', 'how can i log in', 'how can i sign in',
                       'logging in', 'signing in'],
        'response' =>
            "**How to Log In:**\n\n" .
            "1. Enter your **username** in the Username field\n" .
            "2. Enter your **password** in the Password field\n" .
            "3. Click the **Log In** button\n\n" .
            "You'll be redirected to your dashboard right away.\n\n" .
            "**Admin login:** Use the admin username and password set by the system.\n\n" .
            "Don't have an account yet? Click **Sign Up** to create one — it's free!",
    ],

    // ── Forgot password ────────────────────────────────────────────────────
    [
        'patterns' => ['forgot password', 'forget password', 'reset password', 'lost password',
                       'recover password', 'password reset', 'change my password', 'recover my account',
                       'recover account', 'password recovery', "can't login", 'cant login',
                       "can't log in", 'cant log in', 'wrong password', 'incorrect password',
                       'password wrong', 'lost my password', 'password forgotten', 'password help'],
        'response' =>
            "**Forgot your password? No worries!**\n\n" .
            "1. On the login page, click **\"Forgot password?\"** below the Log In button\n" .
            "2. Enter your **username** and **registered email address**\n" .
            "3. We'll send a **6-digit OTP** to your email\n" .
            "4. Enter the OTP, then choose a **new password** (min 6 characters)\n\n" .
            "The OTP expires in **10 minutes** — check your spam folder if you don't see it.\n\n" .
            "If your email isn't recognised, contact the library admin.",
    ],

    // ── Forgot username ────────────────────────────────────────────────────
    [
        'patterns' => ['forgot username', 'forget username', 'lost username',
                       "don't remember username", 'dont remember username',
                       'what is my username', 'my username', 'recover username',
                       "don't know my username", 'dont know my username',
                       "what's my username", 'whats my username', 'username forgotten'],
        'response' =>
            "**Forgot your username?**\n\n" .
            "There's no automated username recovery tool currently.\n\n" .
            "**What to try:**\n" .
            "• Check the email you received when you registered\n" .
            "• Try usernames you commonly use\n" .
            "• Your username was chosen by you during signup — it's not your email\n" .
            "• Contact the library admin and provide your registered email — they can look it up\n\n" .
            "Tip: once you're logged in, your username appears on your dashboard.",
    ],

    // ── How to sign up ─────────────────────────────────────────────────────
    [
        'patterns' => ['how to sign up', 'how do i sign up', 'how to signup', 'how do i signup',
                       'how to register', 'how do i register', 'how to create account',
                       'how do i create', 'create an account', 'register account',
                       'sign up steps', 'registration steps', 'signup process',
                       'how can i sign up', 'how can i register', 'new account',
                       'register here', 'join the library'],
        'response' =>
            "**How to Sign Up:**\n\n" .
            "1. Click **Sign Up** on the login page (or go to signup.php)\n" .
            "2. Fill in all the required fields:\n" .
            "   • First & Last name (letters only)\n" .
            "   • Email address (valid format, must be unique)\n" .
            "   • Username (your choice, must be unique)\n" .
            "   • Password (minimum 6 characters)\n" .
            "   • Confirm password (must match)\n" .
            "3. Click **Create Account**\n\n" .
            "You're instantly logged in! No email verification required.\n" .
            "You also receive a starting wallet balance of **Rs 35,000**. 🎉",
    ],

    // ── Required fields ────────────────────────────────────────────────────
    [
        'patterns' => ['what fields', 'required fields', 'what is required', 'what do i need',
                       'what information', 'what info do i need', 'what details', 'fields required',
                       'fields needed', 'signup fields', 'sign up fields', 'registration fields',
                       'what to fill', 'what to enter'],
        'response' =>
            "**Required fields to sign up:**\n\n" .
            "• **First Name** — letters only (no numbers)\n" .
            "• **Last Name** — letters only (no numbers)\n" .
            "• **Email** — valid format (e.g. you@example.com), must be unique\n" .
            "• **Username** — your choice, must be unique across all users\n" .
            "• **Password** — minimum 6 characters\n" .
            "• **Confirm Password** — must exactly match your password\n\n" .
            "All fields are required — none can be left blank.",
    ],

    // ── Username rules ─────────────────────────────────────────────────────
    [
        'patterns' => ['username rule', 'username requirement', 'username format', 'valid username',
                       'what username', 'username length', 'username limit', 'username allowed',
                       'choose username', 'pick username', 'username taken', 'username already',
                       'username criteria'],
        'response' =>
            "**Username Rules:**\n\n" .
            "• Must be **unique** — no two users can share the same username\n" .
            "• No specific format restriction — you choose what it is\n" .
            "• It's used to log in, so pick something memorable\n\n" .
            "**Tips:**\n" .
            "• Letters, numbers, and underscores work well\n" .
            "• Avoid spaces if possible\n" .
            "• If you see \"Username already exists\", someone else has it — try a variation\n\n" .
            "Note: you cannot change your username after signup, so choose carefully.",
    ],

    // ── Password rules ─────────────────────────────────────────────────────
    [
        'patterns' => ['password rule', 'password requirement', 'password format', 'password length',
                       'minimum password', 'password minimum', 'how long password', 'password limit',
                       'password criteria', 'strong password', 'valid password', 'password must',
                       'what password', 'password characters'],
        'response' =>
            "**Password Requirements:**\n\n" .
            "• Minimum **6 characters** long\n" .
            "• Both password and confirm password fields must **match exactly**\n" .
            "• No maximum length\n\n" .
            "**Tips for a strong password:**\n" .
            "• Mix uppercase and lowercase letters\n" .
            "• Include numbers or symbols (e.g. @, #, !)\n" .
            "• Avoid obvious choices like \"123456\" or \"password\"\n\n" .
            "Forgot your password later? Use the **Forgot password?** link on the login page.",
    ],

    // ── Email rules ────────────────────────────────────────────────────────
    [
        'patterns' => ['email format', 'valid email', 'email requirement', 'what email',
                       'email rule', 'email address format', 'email already', 'email taken',
                       'email exists', 'email address'],
        'response' =>
            "**Email Requirements:**\n\n" .
            "• Must be a **valid email address** (e.g. name@example.com)\n" .
            "• Must be **unique** — only one account per email address\n" .
            "• Used for **password recovery** (OTP is sent to this email)\n\n" .
            "If you see **\"Email already exists\"**, that email is linked to an existing account.\n" .
            "Try logging in instead, or use a different email address.",
    ],

    // ── Name rules ─────────────────────────────────────────────────────────
    [
        'patterns' => ['name rule', 'name requirement', 'name format', 'first name requirement',
                       'last name requirement', 'name allowed', 'name must', 'what name',
                       'letters only', 'name with number', 'number in name', 'name contain',
                       'name characters', 'name validation'],
        'response' =>
            "**Name Requirements:**\n\n" .
            "First name and last name must contain **letters only**.\n\n" .
            "**Allowed characters:**\n" .
            "• Letters (a–z, A–Z)\n" .
            "• Spaces\n" .
            "• Apostrophes (') — e.g. O'Brien\n" .
            "• Hyphens (-) — e.g. Mary-Jane\n" .
            "• Dots (.) — e.g. Dr. Smith\n\n" .
            "**Not allowed:** Numbers or other special characters.\n\n" .
            "This applies to both first name and last name fields.",
    ],

    // ── After signup ───────────────────────────────────────────────────────
    [
        'patterns' => ['after signup', 'after sign up', 'after registration', 'after creating account',
                       'after i sign up', 'after i register', 'what happens after', 'once i sign up',
                       'once i register', 'after account created', 'after joining'],
        'response' =>
            "**After you sign up:**\n\n" .
            "✅ You're **automatically logged in** — no extra steps needed\n" .
            "📚 You land on your **personal dashboard**\n" .
            "💰 You start with **Rs 35,000** in your wallet\n\n" .
            "**Right away you can:**\n" .
            "• Browse the Bookstore and **borrow books for free**\n" .
            "• Save books to your ❤️ Favorites\n" .
            "• Download book PDFs (Rs 400 each from wallet)\n" .
            "• Track due dates and reading history\n" .
            "• Request borrow extensions if you need more time",
    ],

    // ── Starting balance ───────────────────────────────────────────────────
    [
        'patterns' => ['starting balance', 'initial balance', 'start with', 'free money',
                       'free credit', 'starting wallet', 'how much do i get', 'do i get money',
                       'get credits', '35000', 'rs 35', 'wallet start', 'initial wallet',
                       'how much wallet', 'wallet amount'],
        'response' =>
            "**Starting Wallet Balance:**\n\n" .
            "Every new account starts with **Rs 35,000** in their wallet — automatically, no action needed.\n\n" .
            "**What the wallet is used for:**\n" .
            "• 📄 PDF downloads — **Rs 400** per book\n" .
            "• ⏳ Borrow extensions — **Rs 80/day** extra\n" .
            "• 📦 Lost book charges — varies by book\n\n" .
            "**Borrowing physical books is completely free** — the wallet only covers optional extras.",
    ],

    // ── Is it free to join ─────────────────────────────────────────────────
    [
        'patterns' => ['is it free', 'is signup free', 'is registration free', 'free to join',
                       'free to register', 'free to use', 'cost to join', 'how much to join',
                       'any fee', 'any charges', 'membership fee', 'is there a fee',
                       'do i need to pay', 'is this free', 'free library', 'free service',
                       'free to sign'],
        'response' =>
            "**Yes, TechGiants Library is free to join!** 🎉\n\n" .
            "• **Signing up** — completely free\n" .
            "• **Browsing books** — completely free\n" .
            "• **Borrowing books** — completely free\n\n" .
            "**Optional paid features** (covered by your starting Rs 35,000 wallet):\n" .
            "• PDF downloads — Rs 400 per book\n" .
            "• Borrow extensions — Rs 80/day\n" .
            "• Lost book replacement fees\n\n" .
            "You start with Rs 35,000 in your wallet, so you're covered from day one!",
    ],

    // ── Is borrowing free ──────────────────────────────────────────────────
    [
        'patterns' => ['is borrowing free', 'cost to borrow', 'borrow price', 'borrowing cost',
                       'borrow for free', 'free to borrow', 'borrowing fee', 'charge to borrow',
                       'how much to borrow', 'borrow a book free', 'reading free', 'is reading free'],
        'response' =>
            "**Yes, borrowing books is completely free!** 📖\n\n" .
            "• No charge to borrow physical books\n" .
            "• No membership fee\n" .
            "• Just sign up and start borrowing\n\n" .
            "**The only paid features are:**\n" .
            "• PDF downloads — Rs 400 each\n" .
            "• Borrow extensions — Rs 80/day extra\n" .
            "• Lost book replacement fees\n\n" .
            "Every account starts with Rs 35,000 wallet balance to cover these optional extras.",
    ],

    // ── About the library ──────────────────────────────────────────────────
    [
        'patterns' => ['what is techgiants', 'about techgiants', 'about this library',
                       'what is this', 'what can i do here', 'what is this site',
                       'what is this app', 'library overview', 'tell me about',
                       'how does this library', 'what is the library', 'what kind of site',
                       'what type of', 'what does this do', 'library info'],
        'response' =>
            "**Welcome to TechGiants Library!** 📚\n\n" .
            "A personal digital library where you can borrow and manage books.\n\n" .
            "**What you can do:**\n" .
            "• 📖 **Borrow books** — free, with a set due date\n" .
            "• 📄 **Download PDFs** — for offline reading (Rs 400)\n" .
            "• ❤️ **Save favorites** and track your reading history\n" .
            "• ⏳ **Extend due dates** if you need more time (Rs 80/day)\n" .
            "• 💰 **Wallet system** for paid features\n\n" .
            "**To get started:** Create a free account — you're up and running in under a minute!",
    ],

    // ── Already have an account ────────────────────────────────────────────
    [
        'patterns' => ['already have account', 'already have an account', 'already registered',
                       'have an account', 'existing account', 'i have an account',
                       'already signed up', 'already a member', 'go to login', 'back to login',
                       'login instead', 'i am already'],
        'response' =>
            "If you already have an account, head to the **Login page** and:\n\n" .
            "1. Enter your **username**\n" .
            "2. Enter your **password**\n" .
            "3. Click **Log In**\n\n" .
            "You'll be redirected straight to your dashboard. 🚀\n\n" .
            "Forgot your password? Use the **\"Forgot password?\"** link to reset it via email.",
    ],

    // ── Admin login ────────────────────────────────────────────────────────
    [
        'patterns' => ['admin login', 'admin credentials', 'admin password', 'how admin',
                       'login as admin', 'admin account', 'admin user', 'admin username',
                       'admin sign in', 'admin access'],
        'response' =>
            "**Admin Login:**\n\n" .
            "The admin account uses credentials set by the system — if you're the admin, you should already have them.\n\n" .
            "After logging in as admin you're redirected to the **Admin Dashboard** where you can:\n" .
            "• Approve and manage book borrowings\n" .
            "• Handle pending returns and condition reports\n" .
            "• Add, edit, or remove books\n" .
            "• View user transactions and borrowing history\n\n" .
            "If you're a regular user, use your own username and password.",
    ],

    // ── Login not working ──────────────────────────────────────────────────
    [
        'patterns' => ['login problem', 'login issue', 'login error', 'login failed',
                       'sign in failed', 'not logging in', 'keeps failing', 'error logging',
                       'not working', "won't login", 'wont login', 'unable to sign in',
                       'unable to login', 'having trouble', 'trouble signing in',
                       'trouble logging in'],
        'response' =>
            "**Login not working? Here's what to check:**\n\n" .
            "1. **Username is case-sensitive** — make sure it's typed exactly as when you registered\n" .
            "2. **Password check** — Caps Lock off, no extra spaces\n" .
            "3. **Forgot password?** — use the link below the Log In button to reset via email OTP\n" .
            "4. **No account yet?** — click Sign Up to create one (it's free)\n\n" .
            "If you're still stuck after resetting your password, contact the library admin.",
    ],

    // ── Can I change credentials ───────────────────────────────────────────
    [
        'patterns' => ['change username', 'change email', 'change password', 'update username',
                       'update email', 'update password', 'edit username', 'edit email',
                       'can i change', 'change my username', 'change my email', 'change my password',
                       'modify account', 'edit account'],
        'response' =>
            "**Changing account details after signup:**\n\n" .
            "• **Password** — yes! Reset it anytime via **Forgot password?** on the login page\n" .
            "• **Username** — currently cannot be changed after signup (choose carefully!)\n" .
            "• **Email** — cannot be changed via the user interface currently\n\n" .
            "For username or email changes, contact the library admin who can update it manually.",
    ],

    // ── Privacy / security ─────────────────────────────────────────────────
    [
        'patterns' => ['privacy', 'is my data safe', 'data security', 'secure', 'my information safe',
                       'personal data', 'data protection', 'safe to sign up', 'safe to use',
                       'is it secure'],
        'response' =>
            "**Your data is secure:**\n\n" .
            "• Passwords are stored using **bcrypt hashing** — never in plain text\n" .
            "• Sessions are protected against **CSRF attacks**\n" .
            "• Security headers (XSS protection, clickjacking prevention) are applied\n" .
            "• Only you can see your reading history, wallet balance, and personal data\n\n" .
            "Your information is only used to power your library account.",
    ],

    // ── What books are available ───────────────────────────────────────────
    [
        'patterns' => ['what books', 'available books', 'what can i read', 'what genres',
                       'book collection', 'books you have', 'what titles', 'browse books',
                       'books available', 'what do you have', 'what kind of books',
                       'types of books'],
        'response' =>
            "**Our Book Collection:**\n\n" .
            "TechGiants Library has titles across multiple genres:\n" .
            "• 📚 Fiction & Literary Fiction\n" .
            "• 🚀 Science Fiction & Fantasy\n" .
            "• 🕵️ Mystery & Thriller\n" .
            "• 📖 Biography & Memoir\n" .
            "• 🌍 History\n" .
            "• 💡 Self-Help & Personal Development\n" .
            "• 💻 Science & Technology\n\n" .
            "To browse titles and borrow: **sign up** (free!) or **log in** and visit the Bookstore tab.",
    ],

    // ── How long does signup take ──────────────────────────────────────────
    [
        'patterns' => ['how long signup', 'how long does it take', 'time to sign up',
                       'quick signup', 'fast signup', 'instant account', 'immediate access',
                       'how fast'],
        'response' =>
            "**Signing up takes less than a minute!** ⚡\n\n" .
            "Just fill in the form, click **Create Account**, and you're instantly logged in.\n\n" .
            "No email verification step — access is immediate. You'll land straight on your dashboard.",
    ],

    // ── Guest / browse without account ────────────────────────────────────
    [
        'patterns' => ['browse without', 'without signing up', 'without an account',
                       'without account', 'as a guest', 'guest access', 'guest browse',
                       'view books without', 'can i look without', 'without login',
                       'without registering'],
        'response' =>
            "**Currently, browsing requires an account.**\n\n" .
            "You need to sign up (free) or log in to browse the book collection and borrow books.\n\n" .
            "The good news: signing up takes **less than a minute** and costs nothing — and you immediately get Rs 35,000 wallet credit!\n\n" .
            "Click **Sign Up** to get started.",
    ],

    // ── Delete account ─────────────────────────────────────────────────────
    [
        'patterns' => ['delete my account', 'delete account', 'remove my account',
                       'close my account', 'deactivate account', 'cancel account',
                       'how to delete', 'remove account', 'unregister'],
        'response' =>
            "**Account deletion** is not available through the user interface directly.\n\n" .
            "To have your account removed:\n" .
            "• Contact the **library admin** and request deletion\n" .
            "• Provide your username and registered email for verification\n\n" .
            "Note: make sure you've returned all borrowed books before requesting deletion — any outstanding fines or borrowed books will need to be settled first.",
    ],

    // ── Mobile support ─────────────────────────────────────────────────────
    [
        'patterns' => ['mobile', 'phone', 'tablet', 'iphone', 'android', 'on my phone',
                       'work on mobile', 'responsive', 'smartphone', 'works on phone'],
        'response' =>
            "**Yes, TechGiants Library works on mobile!** 📱\n\n" .
            "The site is fully responsive — it adapts to phones and tablets automatically.\n\n" .
            "Just open the site in your phone's browser, log in, and everything works the same:\n" .
            "• Browse and borrow books\n" .
            "• Check due dates and reading history\n" .
            "• Manage your wallet\n\n" .
            "No app download required — it runs directly in the browser.",
    ],

    // ── Password confirm mismatch ──────────────────────────────────────────
    [
        'patterns' => ['password mismatch', 'passwords don\'t match', 'passwords dont match',
                       'confirm password', 'password not matching', 'passwords not matching',
                       'reenter password', 're-enter password'],
        'response' =>
            "**Passwords don't match?**\n\n" .
            "The **Password** and **Confirm Password** fields must be identical.\n\n" .
            "**Tips:**\n" .
            "• Re-type both fields carefully\n" .
            "• Check Caps Lock is off\n" .
            "• Both must be at least **6 characters**\n\n" .
            "Once they match, the form will submit successfully.",
    ],

    // ── OTP / email verification ───────────────────────────────────────────
    [
        'patterns' => ['otp', 'one time password', 'verification code', 'email code',
                       'code sent', 'didn\'t receive', 'didnt receive', 'no email',
                       'email not received', 'code expired', 'otp expired', 'resend otp',
                       'not getting email'],
        'response' =>
            "**About the OTP (One-Time Password):**\n\n" .
            "OTPs are sent during **password recovery** only — not during signup.\n\n" .
            "**If you didn't receive the OTP:**\n" .
            "• Check your **spam / junk folder**\n" .
            "• Make sure you entered the **correct email address**\n" .
            "• The OTP expires in **10 minutes** — request a new one if it expired\n" .
            "• Allow a minute for delivery\n\n" .
            "If the email still doesn't arrive, contact the library admin.",
    ],

    // ── Session / auto-logout ──────────────────────────────────────────────
    [
        'patterns' => ['session expired', 'logged out', 'auto logout', 'automatically logged out',
                       'kicked out', 'session timeout', 'keep logging out', 'getting logged out'],
        'response' =>
            "**Session expired or logged out?**\n\n" .
            "Your session expires after inactivity to keep your account secure.\n\n" .
            "Just **log in again** with your username and password — your data (borrowed books, history, wallet) is all saved.\n\n" .
            "If you keep getting logged out unexpectedly, make sure your browser allows session cookies.",
    ],

    // ── What is username vs email ──────────────────────────────────────────
    [
        'patterns' => ['username or email', 'email or username', 'login with email',
                       'can i use email to login', 'do i login with email',
                       'what do i use to login', 'what to enter to login'],
        'response' =>
            "**Login uses your username, not your email.**\n\n" .
            "• **Username field:** enter your chosen username (set during signup)\n" .
            "• **Password field:** enter your password\n\n" .
            "Your email is only used for password recovery (OTP delivery) — not for logging in.\n\n" .
            "Forgot your username? Try usernames you commonly use, or contact the admin.",
    ],

];

// ── Match intent (first match wins) ───────────────────────────────────────────
$matched = null;
foreach ($intents as $intent) {
    foreach ($intent['patterns'] as $pattern) {
        if (strpos($norm, $pattern) !== false) {
            $matched = $intent;
            break 2;
        }
    }
}

ob_end_clean();

if ($matched) {
    echo json_encode([
        'type'    => 'intent',
        'message' => $matched['response'],
    ]);
} else {
    echo json_encode([
        'type'    => 'empty',
        'message' =>
            "I'm not sure about that, but I can help with:\n\n" .
            "• **Logging in** — \"How do I log in?\"\n" .
            "• **Signing up** — \"How do I create an account?\"\n" .
            "• **Forgot password** — \"I forgot my password\"\n" .
            "• **Account rules** — \"What are the username requirements?\"\n" .
            "• **About the library** — \"What is TechGiants?\"\n\n" .
            "Try rephrasing or pick one of the topics above!",
    ]);
}
