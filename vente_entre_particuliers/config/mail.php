<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function FluxoMailer(): PHPMailer
{
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;

    // EMAIL GMAIL
    $mail->Username = 'aaachchak@gmail.com';

    // MOT DE PASSE D’APPLICATION GOOGLE (16 caractères)
    $mail->Password = 'fmuaeqswnajqxaqg';

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->CharSet = 'UTF-8';

    $mail->setFrom('aaachchak@gmail.com', 'Fluxo');

    return $mail;
}

function sendVerificationEmail(string $toEmail, string $toName, string $code): bool
{
    try {
        $mail = FluxoMailer();

        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Vérification de ton email - Fluxo';

        $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
        $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

        $mail->Body = '
        <div style="margin:0;padding:0;background:#f6f7fb;font-family:Arial,Helvetica,sans-serif;">
          <div style="max-width:600px;margin:30px auto;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.08);">
            
            <div style="background:#111827;padding:28px 32px;color:#ffffff;">
              <h1 style="margin:0;font-size:28px;">Fluxo</h1>
              <p style="margin:8px 0 0 0;font-size:14px;opacity:.9;">Vérification de ton adresse email</p>
            </div>

            <div style="padding:32px;">
              <h2 style="margin-top:0;color:#111827;">Bonjour ' . $safeName . ',</h2>

              <p style="font-size:15px;color:#374151;line-height:1.7;">
                Merci pour ton inscription sur <b>Fluxo</b>. Voici ton code de vérification :
              </p>

              <div style="text-align:center;margin:28px 0;">
                <div style="
                  display:inline-block;
                  background:#f3f4f6;
                  border:1px solid #e5e7eb;
                  border-radius:14px;
                  padding:16px 28px;
                  font-size:34px;
                  font-weight:700;
                  letter-spacing:8px;
                  color:#111827;">
                  ' . $safeCode . '
                </div>
              </div>

              <p style="font-size:14px;color:#6b7280;">
                Ce code expire dans <b>15 minutes</b>.
              </p>

              <p style="font-size:14px;color:#6b7280;">
                Si tu n’es pas à l’origine de cette demande, ignore simplement cet email.
              </p>
            </div>

            <div style="padding:20px 32px;background:#f9fafb;border-top:1px solid #e5e7eb;">
              <p style="margin:0;font-size:12px;color:#9ca3af;">
                © Fluxo - Vérification email
              </p>
            </div>

          </div>
        </div>';

        $mail->AltBody =
            "Bonjour {$toName},\n\n" .
            "Ton code de vérification Fluxo est : {$code}\n\n" .
            "Ce code expire dans 15 minutes.\n\n" .
            "Si ce n'est pas toi, ignore cet email.";

        $mail->send();
        return true;

    } catch (Exception $e) {
        return false;
    }
}

function sendResetPasswordEmail(string $toEmail, string $toName, string $resetLink): bool
{
    try {
        $mail = FluxoMailer();

        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Réinitialisation du mot de passe - Fluxo';

        $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
        $safeLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');

        $mail->Body = '
        <div style="margin:0;padding:0;background:#f6f7fb;font-family:Arial,Helvetica,sans-serif;">
          <div style="max-width:600px;margin:30px auto;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.08);">
            
            <div style="background:#111827;padding:28px 32px;color:#ffffff;">
              <h1 style="margin:0;font-size:28px;">Fluxo</h1>
              <p style="margin:8px 0 0 0;font-size:14px;opacity:.9;">Réinitialisation du mot de passe</p>
            </div>

            <div style="padding:32px;">
              <h2 style="margin-top:0;color:#111827;">Bonjour ' . $safeName . ',</h2>

              <p style="font-size:15px;color:#374151;line-height:1.7;">
                Nous avons reçu une demande de réinitialisation de ton mot de passe.
              </p>

              <p style="font-size:15px;color:#374151;line-height:1.7;">
                Clique sur le bouton ci-dessous pour définir un nouveau mot de passe :
              </p>

              <div style="text-align:center;margin:30px 0;">
                <a href="' . $safeLink . '" style="
                  display:inline-block;
                  background:#111827;
                  color:#ffffff;
                  text-decoration:none;
                  padding:14px 24px;
                  border-radius:12px;
                  font-weight:700;">
                  Réinitialiser mon mot de passe
                </a>
              </div>

              <p style="font-size:14px;color:#6b7280;">
                Ce lien expire dans <b>1 heure</b>.
              </p>

              <p style="font-size:14px;color:#6b7280;">
                Si le bouton ne fonctionne pas, copie ce lien dans ton navigateur :
              </p>

              <p style="font-size:13px;word-break:break-all;color:#374151;">
                ' . $safeLink . '
              </p>

              <p style="font-size:14px;color:#6b7280;">
                Si tu n’es pas à l’origine de cette demande, ignore simplement cet email.
              </p>
            </div>

            <div style="padding:20px 32px;background:#f9fafb;border-top:1px solid #e5e7eb;">
              <p style="margin:0;font-size:12px;color:#9ca3af;">
                © Fluxo - Réinitialisation mot de passe
              </p>
            </div>

          </div>
        </div>';

        $mail->AltBody =
            "Bonjour {$toName},\n\n" .
            "Clique sur ce lien pour réinitialiser ton mot de passe : {$resetLink}\n\n" .
            "Ce lien expire dans 1 heure.\n\n" .
            "Si ce n'est pas toi, ignore cet email.";

        $mail->send();
        return true;

    } catch (Exception $e) {
        return false;
    }
}