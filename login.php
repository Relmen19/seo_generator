<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

if (isAuthenticated()) {
    header('Location: /admin_simple/articles.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['user'] ?? '');
    $pass = $_POST['password'] ?? '';

    if (attemptLogin($user, $pass)) {
        header('Location: /admin_simple/articles.php');
        exit;
    }
    $error = 'Неверный логин или пароль';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход — SEO Generator</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{
            font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
            background:#0f172a;
            color:#e2e8f0;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
        }
        .login-card{
            background:#1e293b;
            border:1px solid #334155;
            border-radius:16px;
            padding:40px 36px;
            width:100%;
            max-width:400px;
            box-shadow:0 25px 50px rgba(0,0,0,.4);
        }
        .logo{
            text-align:center;
            margin-bottom:32px;
        }
        .logo svg{
            width:48px;height:48px;
            fill:#6366f1;
            margin-bottom:12px;
        }
        .logo h1{
            font-size:1.4rem;
            font-weight:600;
            color:#f1f5f9;
        }
        .logo p{
            font-size:.85rem;
            color:#64748b;
            margin-top:4px;
        }
        .field{
            margin-bottom:20px;
        }
        .field label{
            display:block;
            font-size:.8rem;
            font-weight:500;
            color:#94a3b8;
            margin-bottom:6px;
            text-transform:uppercase;
            letter-spacing:.5px;
        }
        .field input{
            width:100%;
            padding:12px 14px;
            background:#0f172a;
            border:1px solid #334155;
            border-radius:8px;
            color:#e2e8f0;
            font-size:.95rem;
            outline:none;
            transition:border-color .2s;
        }
        .field input:focus{
            border-color:#6366f1;
            box-shadow:0 0 0 3px rgba(99,102,241,.15);
        }
        .field input::placeholder{
            color:#475569;
        }
        .btn{
            width:100%;
            padding:12px;
            background:#6366f1;
            border:none;
            border-radius:8px;
            color:#fff;
            font-size:.95rem;
            font-weight:600;
            cursor:pointer;
            transition:background .2s;
            margin-top:4px;
        }
        .btn:hover{background:#4f46e5}
        .btn:active{background:#4338ca}
        .error{
            background:rgba(239,68,68,.12);
            border:1px solid rgba(239,68,68,.3);
            color:#fca5a5;
            padding:10px 14px;
            border-radius:8px;
            font-size:.85rem;
            margin-bottom:20px;
            text-align:center;
        }
        .footer{
            text-align:center;
            margin-top:24px;
            font-size:.75rem;
            color:#475569;
        }
    </style>
</head>
<body>
<div class="login-card">
    <div class="logo">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
        </svg>
        <h1>SEO Generator</h1>
        <p>Панель управления</p>
    </div>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="on">
        <div class="field">
            <label for="user">Логин</label>
            <input type="text" id="user" name="user" placeholder="admin" required autofocus
                   value="<?= htmlspecialchars($_POST['user'] ?? '') ?>">
        </div>
        <div class="field">
            <label for="password">Пароль</label>
            <input type="password" id="password" name="password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;" required>
        </div>
        <button type="submit" class="btn">Войти</button>
    </form>

    <div class="footer">SEO Generator</div>
</div>
</body>
</html>
