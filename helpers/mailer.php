<?php
/**
 * Result email — plain PHP mail() (shared-hosting friendly), HTML body
 * matching the site's look. From/reply-to configurable in settings.
 */

require_once __DIR__ . '/app.php';

/**
 * @param string $to
 * @param array  $top    values_catalog rows of the top values (label_lt, meaning_lt)
 * @param string $tension pair tension text
 * @param string $meaning pair meaning text
 * @return bool
 */
function sendResultEmail($to, array $top, $tension, $meaning) {
    $siteName = getSetting('site_name', 'Vertybių testas');
    $fromName = getSetting('email_from_name', $siteName);
    $fromAddr = getSetting('email_from_address', 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $replyTo  = getSetting('email_reply_to', '');
    $booking  = getSetting('booking_url', '');

    $e = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

    $valueBlocks = '';
    $rankLabels = ['Pirma vertybė', 'Antra vertybė', 'Trečia vertybė', 'Ketvirta vertybė', 'Penkta vertybė', 'Šešta vertybė'];
    foreach ($top as $i => $v) {
        $rank = $rankLabels[$i] ?? ($i + 1) . '.';
        $valueBlocks .= '
        <div style="background:#ffffff;border:1px solid #e4dccb;border-top:4px solid #496E50;border-radius:12px;padding:20px 24px;margin-bottom:12px;">
          <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8a8f88;margin-bottom:6px;">' . $e($rank) . '</div>
          <div style="font-family:Georgia,serif;font-size:24px;color:#1A2E1D;font-weight:bold;">' . $e(mb_strtoupper($v['label_lt'])) . '</div>
        </div>';
    }

    $tensionBlock = $tension ? '
        <div style="background:#ffffff;border:1px solid #e4dccb;border-radius:12px;padding:20px 24px;margin:20px 0 12px;">
          <div style="font-family:Georgia,serif;font-size:17px;color:#1A2E1D;font-weight:bold;margin-bottom:8px;">Galima vidinė įtampa</div>
          <div style="color:#424842;line-height:1.6;">' . nl2br($e($tension)) . '</div>
        </div>' : '';

    $meaningBlock = $meaning ? '
        <div style="background:#496E50;border-radius:12px;padding:20px 24px;margin-bottom:20px;">
          <div style="font-family:Georgia,serif;font-size:17px;color:#ffffff;font-weight:bold;margin-bottom:8px;">Ką tai reiškia</div>
          <div style="color:#ffffff;line-height:1.6;">' . nl2br($e($meaning)) . '</div>
        </div>' : '';

    $bookingBlock = $booking ? '
        <div style="text-align:center;margin:28px 0;">
          <a href="' . $e($booking) . '" style="display:inline-block;background:#496E50;color:#ffffff;text-decoration:none;padding:14px 36px;border-radius:28px;font-weight:bold;letter-spacing:1px;">REZERVUOTI POKALBĮ</a>
        </div>' : '';

    $html = '<!DOCTYPE html><html lang="lt"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#F8F3EA;font-family:Helvetica,Arial,sans-serif;">
  <div style="max-width:560px;margin:0 auto;padding:32px 20px;">
    <div style="text-align:center;font-family:Georgia,serif;font-size:18px;color:#1A2E1D;font-weight:bold;margin-bottom:28px;">' . $e($siteName) . '</div>
    <h1 style="font-family:Georgia,serif;font-size:28px;color:#1A2E1D;margin:0 0 8px;">Tavo stipriausios vertybės</h1>
    <p style="color:#424842;margin:0 0 24px;">Šios vertybės šiuo metu stipriausiai veda tavo sprendimus.</p>
    ' . $valueBlocks . $tensionBlock . $meaningBlock . $bookingBlock . '
    <p style="color:#8a8f88;font-size:12px;text-align:center;margin-top:32px;">
      Šį laišką gavai, nes atlikai vertybių testą ir paprašei rezultato el. paštu.
    </p>
  </div>
</body></html>';

    $subject = 'Tavo vertybių testo rezultatas — ' .
        implode(' ir ', array_map(fn($v) => $v['label_lt'], array_slice($top, 0, 2)));

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: =?UTF-8?B?' . base64_encode($fromName) . "?= <$fromAddr>",
    ];
    if ($replyTo) $headers[] = "Reply-To: $replyTo";

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    return @mail($to, $encodedSubject, $html, implode("\r\n", $headers), "-f$fromAddr");
}
