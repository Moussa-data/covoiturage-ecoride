<?php
/**
 * lib/mailer.php — Envoi d'e-mails par le serveur SMTP d'AlwaysData
 *
 * NOTIONS CLÉS :
 *
 * 1. SMTP = le protocole des serveurs mail. On ouvre une connexion,
 *    on se présente (EHLO), on chiffre (STARTTLS), on donne l'expéditeur
 *    (MAIL FROM), le destinataire (RCPT TO), puis le message (DATA).
 *    Chaque commande reçoit une réponse avec un code : 2xx/3xx = OK.
 *
 * 2. Chez AlwaysData, un site hébergé chez eux peut envoyer sans mot de
 *    passe SMTP. L'expéditeur doit être une adresse qui existe
 *    (créée dans E-mails > Adresses).
 *
 * 3. Si le SMTP échoue, on essaie la fonction mail() de PHP en secours.
 *    Chaque étape est notée dans un "journal" : la page admin
 *    /diagnostic-email.php l'affiche pour comprendre un problème.
 */

const SMTP_HOTE = 'smtp-moussa.alwaysdata.net';
const SMTP_PORT = 587;

/**
 * Envoie un e-mail texte.
 * @return array ['ok' => bool, 'methode' => string, 'journal' => string[]]
 */
function envoyerEmail(string $destinataire, string $sujet, string $corps): array
{
  $journal = [];

  if (!filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
    return ['ok' => false, 'methode' => 'aucune', 'journal' => ['Adresse destinataire invalide.']];
  }

  // ---- 1. Essai par SMTP ----
  $ok = envoyerParSmtp($destinataire, $sujet, $corps, $journal);
  if ($ok) {
    return ['ok' => true, 'methode' => 'SMTP', 'journal' => $journal];
  }

  // ---- 2. Secours : fonction mail() de PHP ----
  $journal[] = '— SMTP en échec, essai avec la fonction mail() de PHP —';
  $entetes = [
    'From'         => 'EcoRide <' . MAIL_EXPEDITEUR . '>',
    'Reply-To'     => 'm.wanecamara@gmail.com',
    'MIME-Version' => '1.0',
    'Content-Type' => 'text/plain; charset=UTF-8',
  ];
  $ok = @mail($destinataire, encoderSujet($sujet), $corps, $entetes, '-f' . MAIL_EXPEDITEUR);
  $journal[] = $ok ? 'mail() a accepté l\'e-mail.' : 'mail() a refusé l\'e-mail.';
  if (!$ok) {
    error_log('[EcoRide] Échec envoi e-mail vers ' . $destinataire . ' : ' . implode(' | ', $journal));
  }

  return ['ok' => $ok, 'methode' => 'mail()', 'journal' => $journal];
}

function encoderSujet(string $sujet): string
{
  return '=?UTF-8?B?' . base64_encode($sujet) . '?=';
}

/** Lit la réponse du serveur (elle peut tenir sur plusieurs lignes). */
function smtpLire($connexion): string
{
  $reponse = '';
  while (($ligne = fgets($connexion, 515)) !== false) {
    $reponse .= $ligne;
    // La dernière ligne a un espace après le code ("250 OK"), les autres un tiret ("250-...")
    if (strlen($ligne) >= 4 && $ligne[3] === ' ') {
      break;
    }
  }
  return trim($reponse);
}

/** Envoie une commande, vérifie le code de réponse attendu. */
function smtpCommande($connexion, string $commande, array $codesOk, array &$journal): bool
{
  fwrite($connexion, $commande . "\r\n");
  $reponse = smtpLire($connexion);
  $journal[] = '> ' . $commande . '  →  ' . ($reponse === '' ? '(pas de réponse)' : strtok($reponse, "\n"));
  return in_array((int) substr($reponse, 0, 3), $codesOk, true);
}

function envoyerParSmtp(string $destinataire, string $sujet, string $corps, array &$journal): bool
{
  $errno = 0; $errstr = '';
  $connexion = @stream_socket_client('tcp://' . SMTP_HOTE . ':' . SMTP_PORT, $errno, $errstr, 10);
  if (!$connexion) {
    $journal[] = 'Connexion à ' . SMTP_HOTE . ':' . SMTP_PORT . ' impossible : ' . $errstr;
    return false;
  }
  stream_set_timeout($connexion, 10);
  $journal[] = 'Connecté à ' . SMTP_HOTE . ':' . SMTP_PORT . '  →  ' . strtok(smtpLire($connexion), "\n");

  $domaine = substr(strrchr(MAIL_EXPEDITEUR, '@'), 1);

  $ok = smtpCommande($connexion, 'EHLO ' . $domaine, [250], $journal)
     && smtpCommande($connexion, 'STARTTLS', [220], $journal);

  if ($ok) {
    $chiffre = @stream_socket_enable_crypto($connexion, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    $journal[] = $chiffre ? 'Connexion chiffrée (TLS).' : 'Échec du chiffrement TLS.';
    $ok = $chiffre && smtpCommande($connexion, 'EHLO ' . $domaine, [250], $journal);
  }

  $ok = $ok
     && smtpCommande($connexion, 'MAIL FROM:<' . MAIL_EXPEDITEUR . '>', [250], $journal)
     && smtpCommande($connexion, 'RCPT TO:<' . $destinataire . '>', [250, 251], $journal)
     && smtpCommande($connexion, 'DATA', [354], $journal);

  if ($ok) {
    $message  = 'Date: ' . date(DATE_RFC2822) . "\r\n";
    $message .= 'From: EcoRide <' . MAIL_EXPEDITEUR . ">\r\n";
    $message .= 'To: <' . $destinataire . ">\r\n";
    $message .= "Reply-To: m.wanecamara@gmail.com\r\n";
    $message .= 'Subject: ' . encoderSujet($sujet) . "\r\n";
    $message .= 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domaine . ">\r\n";
    $message .= "MIME-Version: 1.0\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    // Une ligne commençant par "." doit être doublée (règle du protocole SMTP)
    $texte = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], "\r\n", $corps));
    $ok = smtpCommande($connexion, $message . $texte . "\r\n.", [250], $journal);
  }

  smtpCommande($connexion, 'QUIT', [221], $journal);
  fclose($connexion);
  return $ok;
}
