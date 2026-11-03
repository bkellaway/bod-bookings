<?php $app = Env::get('APP_NAME'); $contact = Env::get('MAIL_FROM'); ?>
<article class="prose">
<h1>Terms of Service</h1>
<p class="muted">Last updated: <?= date('F j, Y') ?></p>
<p>By using <?= h($app) ?> ("the Service"), operated by Back Office Designs, you agree to these terms.</p>
<h2>The Service</h2>
<p>The Service lets you publish booking pages connected to your Google Calendar. You are responsible for the accuracy of your availability and for the meetings you accept.</p>
<h2>Accounts</h2>
<p>You must sign in with a Google account you control. Keep your account secure and don't use the Service for unlawful purposes, spam, or to harvest guests' data beyond what is needed to hold the meeting.</p>
<h2>Plans and payment</h2>
<p>Free and paid plans are described on the pricing page. Paid plans are billed in advance by Stripe on a recurring basis and can be cancelled at any time from your billing page; cancellation takes effect at the end of the current period. Fees are non-refundable except where required by law.</p>
<h2>Availability and changes</h2>
<p>We aim for high availability but the Service depends on third parties (including Google). We may change or discontinue features with reasonable notice.</p>
<h2>Limitation of liability</h2>
<p>The Service is provided "as is". To the fullest extent permitted by law, Back Office Designs is not liable for indirect or consequential damages, including missed meetings, and our total liability is limited to the fees you paid in the preceding three months.</p>
<h2>Termination</h2>
<p>You can delete your account at any time. We may suspend accounts that violate these terms.</p>
<h2>Contact</h2>
<p><a href="mailto:<?= h($contact) ?>"><?= h($contact) ?></a></p>
</article>
