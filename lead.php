<?php
// lead.php — Processa o formulário da Vivare.care, envia e-mail e salva CSV
// Observação: requer PHP ativo no servidor (Hostinger já tem). 
// Ajuste os e-mails abaixo se desejar cópia para outro endereço.

// ====== CONFIGURAÇÕES ======
$to      = 'contato@vivare.care';   // destino principal
$from    = 'contato@vivare.care';   // remetente (usar um e-mail do seu domínio)
$cc      = 'ceo@vivare.care';       // opcional: cópia
$timezone= 'America/Sao_Paulo';
// ===========================

@date_default_timezone_set($timezone);

// Funções utilitárias
function clean_text($value){
  $value = is_string($value) ? trim($value) : '';
  // Remove quebras perigosas e normaliza
  $value = str_replace(["\r","\n"],' ', $value);
  // Sanitiza caracteres
  $value = filter_var($value, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);
  return $value;
}

// Honeypot anti-spam — se preenchido, descarta
if (!empty($_POST['website'])) {
  header('Location: /');
  exit;
}

// Coleta de campos
$nome     = clean_text($_POST['nome']    ?? '');
$telefone = clean_text($_POST['telefone']?? '');
$emailRaw = trim($_POST['email'] ?? '');
$email    = filter_var($emailRaw, FILTER_VALIDATE_EMAIL) ? $emailRaw : '';
$servico  = clean_text($_POST['servico'] ?? '');
$local    = clean_text($_POST['local']   ?? '');
$mensagem = trim($_POST['mensagem'] ?? '');

// UTMs
$utm_source   = clean_text($_POST['utm_source']   ?? '');
$utm_medium   = clean_text($_POST['utm_medium']   ?? '');
$utm_campaign = clean_text($_POST['utm_campaign'] ?? '');

// Valida mínimos
if ($nome === '' || $telefone === '' || $servico === '' || $local === '') {
  header('Location: /?erro=1');
  exit;
}

// Monta e-mail
$subject = '📥 [Lead Vivare] ' . $nome . ' — ' . $servico;
$body = "Novo lead recebido pelo site Vivare.care\n\n" .
        "Nome: {$nome}\n" .
        "Telefone/WhatsApp: {$telefone}\n" .
        "E-mail: " . ($email !== '' ? $email : '—') . "\n" .
        "Serviço: {$servico}\n" .
        "Bairro/Cidade: {$local}\n" .
        "Mensagem: " . ($mensagem !== '' ? $mensagem : '—') . "\n\n" .
        "UTM source: {$utm_source} | medium: {$utm_medium} | campaign: {$utm_campaign}\n" .
        "Data/Hora: " . date('d/m/Y H:i') . "\n" .
        "IP: " . ($_SERVER['REMOTE_ADDR'] ?? '—') . "\n";

$headers = [];
$headers[] = 'From: Vivare <' . $from . '>';
$headers[] = 'Reply-To: ' . ($email !== '' ? $email : $from);
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/plain; charset=UTF-8';
$headersStr = implode("\r\n", $headers);

// Envio (alguns servidores exigem o quinto parâmetro -f para envelope sender)
@mail($to, $subject, $body, $headersStr, '-f ' . $from);
if (!empty($cc)) {
  @mail($cc, $subject, $body, $headersStr, '-f ' . $from);
}

// Salva também em CSV (pasta atual). Você pode baixar depois via Gerenciador de Arquivos.
$csvPath = __DIR__ . '/leads.csv';
$fp = @fopen($csvPath, 'a');
if ($fp) {
  @fputcsv($fp, [date('c'), $nome, $telefone, $email, $servico, $local, $mensagem, $utm_source, $utm_medium, $utm_campaign], ';');
  @fclose($fp);
}

// Redireciona para a página de obrigado
http_response_code(303); // See Other
header('Location: /obrigado.html');
exit;
