<?php
// Recebe as respostas do formulário de cotação e envia por e-mail
// para a caixa multiplanos@metamaximadigital.com.br (Hostinger).

header('Content-Type: application/json; charset=utf-8');

const EMAIL_PARA = 'multiplanos@metamaximadigital.com.br';
const EMAIL_DE   = 'multiplanos@metamaximadigital.com.br'; // precisa ser uma caixa do próprio domínio

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$raw = file_get_contents('php://input');
$b = json_decode($raw, true);
if (!is_array($b)) { $b = $_POST; }

// campo escondido contra robôs: se vier preenchido, finge sucesso e não envia
if (!empty($b['site'])) {
    echo json_encode(['ok' => true]);
    exit;
}

function limpa($v) {
    $v = is_array($v) ? implode(', ', $v) : (string)$v;
    $v = str_replace(["\r", "\n"], ' ', $v);
    return trim(mb_substr($v, 0, 300));
}
function esc($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$nome   = limpa($b['nome']   ?? '');
$idades = limpa($b['idades'] ?? '');
$whats  = limpa($b['whats']  ?? '');
$plano  = limpa($b['plano']  ?? '');
$cidade = limpa($b['cidade'] ?? '');
$digitos = preg_replace('/\D/', '', $whats);

if (mb_strlen($nome) < 2 || strlen($digitos) < 10) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'dados incompletos']);
    exit;
}

date_default_timezone_set('America/Sao_Paulo');
$agora = date('d/m/Y H:i');

$linhas = [
    'Nome'        => $nome,
    'Idades'      => $idades,
    'WhatsApp'    => $whats,
    'Plano ativo' => $plano,
    'Cidade'      => $cidade,
    'Data e hora' => $agora,
];

$waLink = 'https://wa.me/55' . preg_replace('/^55/', '', $digitos);

$html = '<div style="font-family:Arial,sans-serif;font-size:15px;color:#222">'
      . '<h2 style="color:#15833B;margin:0 0 12px">Nova cotação MedSênior</h2>'
      . '<table cellpadding="8" style="border-collapse:collapse">';
foreach ($linhas as $k => $v) {
    $html .= '<tr><td style="border:1px solid #ddd;background:#f3f8f4"><b>' . esc($k) . '</b></td>'
           . '<td style="border:1px solid #ddd">' . esc($v) . '</td></tr>';
}
$html .= '</table>'
       . '<p style="margin-top:16px"><a href="' . esc($waLink) . '" style="background:#25D366;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none">Chamar no WhatsApp</a></p>'
       . '</div>';

$assunto = '=?UTF-8?B?' . base64_encode('Nova cotação MedSênior: ' . mb_substr($nome, 0, 80)) . '?=';
$remetente = '=?UTF-8?B?' . base64_encode('Cotação MedSênior') . '?=';

$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: $remetente <" . EMAIL_DE . ">\r\n";
$headers .= "Reply-To: " . EMAIL_DE . "\r\n";

$ok = mail(EMAIL_PARA, $assunto, $html, $headers, '-f' . EMAIL_DE);

if ($ok) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
