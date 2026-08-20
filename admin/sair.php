<?php
/** Encerra a sessão do painel. */

require_once __DIR__ . '/../inc/auth.php';

encerrar_sessao();
header('Location: /admin/login.php?saiu=1');
exit;
