<?php
/** @var string $channelName */
/** @var string $channelUrl */
/** @var array $messages */
/** @var int $additionalMessagesCount */
?>
AirSend

Channel - <?= $channelName ?>
Channel URL: <?= $channelUrl ?>
==============================================

Messages:
-------------------------------------------------------------------------------
<?php foreach ($messages as $message): ?>
From: <?= $message['user_name'] ?> - <?= $message['time'] ?>
--
<?= $message['text'] ?>
<?= $message['truncated'] ? "<truncated>" : '' ?>
-------------------------------------------------------------------------------
<?php endforeach; ?>


<?php if ($additionalMessagesCount > 0): ?>
There are <?= $additionalMessagesCount ?>  more messages in this channel.
<?php endif; ?>

You can reply directly to this email or access the channel URL.

-------------------------------------------------------------------------------

You are getting this message because your AirSend account is connected to this email address.
Useful links:
Manage your settings: #
Unsubscribe: #
Report this email: #

-------------------------------------------------------------------------------
CodeLathe Technologies Inc
13785 Research Blvd, Suite 125
Austin TX 78750, USA

Phone: +1 (888) 571-6480
Fax: +1 (866) 824-9584
