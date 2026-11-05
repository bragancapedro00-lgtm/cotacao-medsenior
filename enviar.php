<?php
// Recebe as respostas do formulário de cotação e envia por e-mail (SMTP Hostinger)
// De:   contato@metamaximadigital.com.br
// Para: multiplanos@metamaximadigital.com.br
//
// A SENHA NÃO FICA AQUI (o repositório é público).
// Ela fica no arquivo "senha-email.txt", uma pasta ACIMA da public_html,
// contendo só a senha da caixa contato@metamaximadigital.com.br.

header('Content-Type: application/json; charset=utf-8');

const SMTP_HOST  = 'smtp.hostinger.com';
const SMTP_PORTA = 465;
const EMAIL_DE   = 'contato@metamaximadigital.com.br';
const NOME_DE    = 'Cotação MedSênior';
const EMAIL_PARA = 'multiplanos@metamaximadigital.com.br';

function responde($codigo, $dados) {
    http_response_code($codigo);
    echo json_encode($dados);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responde(405, ['ok' => false]);

$b = json_decode(file_get_contents('php://input'), true);
if (!is_array($b)) $b = $_POST;

// campo escondido contra robôs: se vier preenchido, finge sucesso e não envia
if (!empty($b['site'])) responde(200, ['ok' => true]);

function limpa($v) {
    $v = is_array($v) ? implode(', ', $v) : (string)$v;
    $v = str_replace(["\r", "\n"], ' ', $v);
    return trim(mb_substr($v, 0, 300));
}
function esc($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$nome    = limpa($b['nome']   ?? '');
$idades  = limpa($b['idades'] ?? '');
$whats   = limpa($b['whats']  ?? '');
$plano   = limpa($b['plano']  ?? '');
$cidade  = limpa($b['cidade'] ?? '');
$digitos = preg_replace('/\D/', '', $whats);

if (mb_strlen($nome) < 2 || strlen($digitos) < 10) {
    responde(400, ['ok' => false, 'erro' => 'dados incompletos']);
}

date_default_timezone_set('America/Sao_Paulo');
$agora  = date('d/m/Y H:i');
$waLink = 'https://wa.me/55' . preg_replace('/^55/', '', $digitos);

$campos = [
    'Nome'        => $nome,
    'Idades'      => $idades,
    'WhatsApp'    => $whats,
    'Plano ativo' => $plano,
    'Cidade'      => $cidade,
];

/* ===== Modelo da mensagem ===== */
$linhasHtml = '';
foreach ($campos as $k => $v) {
    $linhasHtml .= '<tr>'
        . '<td style="padding:12px 16px;border-bottom:1px solid #e6efe8;color:#5b6b60;font-size:14px;width:130px">' . esc($k) . '</td>'
        . '<td style="padding:12px 16px;border-bottom:1px solid #e6efe8;color:#1d2b22;font-size:15px;font-weight:bold">' . esc($v !== '' ? $v : '-') . '</td>'
        . '</tr>';
}

$html = '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f2f5f3">'
  . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f2f5f3;padding:24px 0"><tr><td align="center">'
  . '<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;font-family:Arial,Helvetica,sans-serif">'
  . '<tr><td style="background:#15833B;padding:22px 24px;color:#ffffff">'
  .   '<div style="font-size:13px;opacity:.85;letter-spacing:.5px">NOVO LEAD</div>'
  .   '<div style="font-size:22px;font-weight:bold;margin-top:4px">Pedido de cotação MedSênior</div>'
  . '</td></tr>'
  . '<tr><td style="padding:20px 24px 4px;color:#1d2b22;font-size:15px;line-height:1.5">'
  .   'Olá! <b>' . esc($nome) . '</b> preencheu o formulário do site e quer receber uma cotação. Os dados estão abaixo:'
  . '</td></tr>'
  . '<tr><td style="padding:12px 24px"><table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e6efe8;border-radius:8px;border-collapse:separate">'
  .   $linhasHtml
  . '</table></td></tr>'
  . '<tr><td align="center" style="padding:12px 24px 24px">'
  .   '<a href="' . esc($waLink) . '" style="display:inline-block;background:#25D366;color:#ffffff;font-size:16px;font-weight:bold;padding:14px 28px;border-radius:8px;text-decoration:none">Chamar no WhatsApp</a>'
  . '</td></tr>'
  . '<tr><td style="padding:14px 24px;background:#f7faf8;color:#7a887f;font-size:12px;text-align:center">'
  .   'Recebido em ' . esc($agora) . ' pelo site planodesaudemedsenior.metamaximadigital.com.br'
  . '</td></tr>'
  . '</table></td></tr></table></body></html>';

$texto = "NOVO LEAD - Pedido de cotação MedSênior\r\n\r\n";
foreach ($campos as $k => $v) $texto .= $k . ': ' . ($v !== '' ? $v : '-') . "\r\n";
$texto .= "\r\nChamar no WhatsApp: " . $waLink . "\r\nRecebido em " . $agora . "\r\n";

/* ===== Montagem do e-mail ===== */
function cab($s) { return '=?UTF-8?B?' . base64_encode($s) . '?='; }

$assunto   = 'Novo lead MedSênior: ' . mb_substr($nome, 0, 80);
$fronteira = 'b' . bin2hex(random_bytes(12));
$dominio   = substr(strrchr(EMAIL_DE, '@'), 1);

$cabecalhos = 'Date: ' . date('r') . "\r\n"
    . 'From: ' . cab(NOME_DE) . ' <' . EMAIL_DE . ">\r\n"
    . 'To: <' . EMAIL_PARA . ">\r\n"
    . 'Reply-To: <' . EMAIL_DE . ">\r\n"
    . 'Subject: ' . cab($assunto) . "\r\n"
    . 'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . $dominio . ">\r\n"
    . "MIME-Version: 1.0\r\n"
    . 'Content-Type: multipart/alternative; boundary="' . $fronteira . '"' . "\r\n";

$corpo = '--' . $fronteira . "\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
    . chunk_split(base64_encode($texto))
    . '--' . $fronteira . "\r\n"
    . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
    . chunk_split(base64_encode($html))
    . '--' . $fronteira . "--\r\n";

/* ===== Envio SMTP autenticado ===== */
function smtp_envia($senha, $cabecalhos, $corpo, &$erro) {
    $s = @stream_socket_client('ssl://' . SMTP_HOST . ':' . SMTP_PORTA, $en, $es, 20);
    if (!$s) { $erro = "conexao: $es"; return false; }
    stream_set_timeout($s, 20);

    $le = function () use ($s) {
        $r = '';
        while (($l = fgets($s, 515)) !== false) { $r .= $l; if (isset($l[3]) && $l[3] === ' ') break; }
        return $r;
    };
    $cmd = function ($c, $espera) use ($s, $le, &$erro) {
        if ($c !== null) fwrite($s, $c . "\r\n");
        $r = $le();
        if ((int)substr($r, 0, 3) !== $espera) { $erro = trim($r); return false; }
        return true;
    };

    $ok = $cmd(null, 220)
       && $cmd('EHLO ' . (gethostname() ?: 'localhost'), 250)
       && $cmd('AUTH LOGIN', 334)
       && $cmd(base64_encode(EMAIL_DE), 334)
       && $cmd(base64_encode($senha), 235)
       && $cmd('MAIL FROM:<' . EMAIL_DE . '>', 250)
       && $cmd('RCPT TO:<' . EMAIL_PARA . '>', 250)
       && $cmd('DATA', 354);
    if ($ok) {
        $dados = preg_replace('/^\./m', '..', $cabecalhos . "\r\n" . $corpo);
        $ok = $cmd($dados . "\r\n.", 250);
    }
    fwrite($s, "QUIT\r\n");
    fclose($s);
    return $ok;
}

$arqSenha = dirname(__DIR__) . '/senha-email.txt';
$senha = is_readable($arqSenha) ? trim(file_get_contents($arqSenha)) : '';
if ($senha === '') {
    error_log('enviar.php: arquivo de senha nao encontrado em ' . $arqSenha);
    responde(500, ['ok' => false, 'erro' => 'senha nao configurada']);
}

$erro = '';
if (smtp_envia($senha, $cabecalhos, $corpo, $erro)) {
    responde(200, ['ok' => true]);
}
error_log('enviar.php: falha SMTP: ' . $erro);
responde(500, ['ok' => false, 'erro' => 'falha no envio']);
