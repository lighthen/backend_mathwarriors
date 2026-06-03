<?php
// JWT Secret (Ganti dengan random string Anda)
define('JWT_SECRET', 'mathwarriors-secret-key-2024');

// ============================================================
// GOOGLE LOGIN - Cara Setup:
// 1. Buka https://console.cloud.google.com
// 2. Buat project baru -> "APIs & Services" -> "Credentials"
// 3. Buat "OAuth 2.0 Client ID" untuk Web Application
// 4. Tambahkan Authorized redirect URIs:
//    - http://localhost/math-warriors/backend/api/auth/google_login.php
// 5. Copy Client ID dan paste di bawah (ganti 'YOUR_WEB_CLIENT_ID_HERE')
// 6. Di Flutter, masuk ke firebase console, buat project,
//    lalu enable Google Sign-In. Download google-services.json
//    dan masukkan ke flutter_app/android/app/
// 7. Untuk iOS: masukkan GoogleService-Info.plist ke Runner/
// ============================================================
define('GOOGLE_CLIENT_ID', '871654390558-edil1ft3scok4u84ppspigcprce4l7ef.apps.googleusercontent.com');

// API Base URL
define('API_URL', 'http://localhost/math-warriors/backend/api');

// Rank System
function getTierByPoints($points) {
    if ($points >= 1500) return 'Legend';
    if ($points >= 1000) return 'Grandmaster';
    if ($points >= 600) return 'Master';
    if ($points >= 300) return 'Ahli';
    if ($points >= 100) return 'Menengah';
    return 'Pemula';
}

// Scoring Rules
define('POINT_CORRECT', 3);
define('POINT_WRONG', -2);
define('TIME_BONUS_CORRECT', 3);
define('TIME_PENALTY_WRONG', 3);
define('INITIAL_TIME', 60);
?>