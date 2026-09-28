<?php
header('Content-type: text/html; charset=UTF-8');
session_start();
session_unset();
session_destroy();

$loginInvalido = isset($_SESSION['login_erro']) && $_SESSION['login_erro'] === true;
unset($_SESSION['login_erro']);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Troca de peças</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/login.css">
</head>

<body>
    <main class="login-page">
        <section class="login-card">
            <div class="logo-area">
                <img src="../img/logo.jpg" alt="DATABIT">
            </div>

            <div class="login-header">
                <span class="login-eyebrow">Troca de peças</span>
                <h1 class="login-title">Login</h1>
            </div>

            <?php if ($loginInvalido) { ?>
                <div class="login-error" role="alert">Usuário ou senha inválidos.</div>
            <?php } ?>

            <form class="login-form" method="post" action="../models/loginExec.php">
                <div class="form-group">
                    <label for="usuario">Usuário</label>
                    <div class="input-wrap">
                        <i class="bi bi-person"></i>
                        <input type="text" id="usuario" name="login" placeholder="Digite seu usuário"
                            autocomplete="username" autofocus required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="senha">Senha</label>
                    <div class="input-wrap">
                        <i class="bi bi-lock"></i>
                        <input type="password" id="senha" name="password" placeholder="Digite sua senha"
                            autocomplete="current-password" required>
                    </div>
                </div>

                <button type="submit" class="btn-login">Entrar</button>
            </form>
        </section>
    </main>
</body>

</html>
