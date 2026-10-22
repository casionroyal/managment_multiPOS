<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Système POS</title>
    <style>
        /* Fond d'écran moderne */
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #2c3e50 0%, #000000 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Boîte de connexion centrée */
        .login-box {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.5);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        /* Style de l'alerte d'erreur */
        .error-alert {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #f5c6cb;
            font-weight: 500;
        }

        .login-box h2 {
            margin-bottom: 30px;
            color: #2c3e50;
            font-size: 24px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: #34495e;
            font-weight: 600;
        }

        .input-group input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
            outline: none;
            transition: 0.3s;
        }

        .input-group input[type="text"]:focus {
            border-color: #3498db;
        }

        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-wrapper input {
            width: 100%;
            padding: 12px 45px 12px 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
            outline: none;
            transition: 0.3s;
        }

        .password-wrapper input:focus {
            border-color: #3498db;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            cursor: pointer;
            font-size: 1.2em;
            user-select: none;
            line-height: 1;
        }

        .btn-login {
            width: 100%;
            background: #27ae60;
            color: white;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
            transition: background 0.3s, transform 0.2s;
        }

        .btn-login:hover {
            background: #219150;
            transform: translateY(-2px);
        }

        .btn-login:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>

<div class="login-box">
    <?php if (isset($_GET['error'])): ?>
        <div class="error-alert">
            ⚠️ Accès refusé : Identifiants incorrects ou compte suspendu.
        </div>
    <?php endif; ?>

    <h1>Connexion </h1>
    <form action="login_process.php" method="POST">
        <div class="input-group">
            <label>Utilisateur</label>
            <input type="text" name="username" required placeholder="Ex: vendeur1">
        </div>

        <div class="input-group">
            <label>Mot de passe</label>
            <div class="password-wrapper">
                <input type="password" name="password" id="password" required placeholder="••••••••">
                <span class="toggle-password" onclick="togglePasswordVisibility()">
                    <i id="eye-icon" style="font-style: normal;">👁️</i>
                </span>
            </div>
        </div>

        <button type="submit" class="btn-login">SE CONNECTER</button>
    </form>
</div>

<script>
    function togglePasswordVisibility() {
        const passwordField = document.getElementById('password');
        const eyeIcon = document.getElementById('eye-icon');
        
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            eyeIcon.innerText = '🙈';
        } else {
            passwordField.type = 'password';
            eyeIcon.innerText = '👁️';
        }
    }
</script>

</body>
</html>