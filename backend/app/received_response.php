<?php
require 'email_responce.php';

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Dashboard</title>
    <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"> -->
    <link rel="stylesheet" href="./assets/main.css">

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="script.js"></script>
    <link rel="stylesheet" href="style.css">

</head>

<body class="bg-gray-100 mt-8">
    <?php require "navbar.php"; ?>

    <div class="container mx-auto px-4 py-6 max-w-7xl">
        <!-- Header with Stats -->
        <div class="mb-8">
            <div class="p-6 text-black mb-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h1 class="text-2xl font-bold flex items-center gap-2">
                            <i class="fas fa-envelope"></i> Email Dashboard
                        </h1>
                        <p class="text-sm text-blue-600 mt-1">Manage your email accounts and communications</p>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="window.location.reload()"
                            class="flex items-center gap-2 bg-white  hover:bg-opacity-30 text-black px-4 py-2 rounded-lg border border-white border-opacity-20 transition">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>

            <!-- Account Tabs -->
            <div class="mb-6">
                <div class="flex overflow-x-auto pb-2 gap-2">
                    <?php foreach ($smtps as $index => $smtp):
                        $replies = fetchReplies($smtp, $db);
                        $unreadCount = count($replies['regular'] ?? []) + count($replies['unsubscribes'] ?? []) + count($replies['bounces'] ?? []);
                        ?>
                        <button onclick="switchAccount(<?= $index ?>)"
                            class="account-tab whitespace-nowrap px-4 py-2 rounded-lg border border-gray-200 hover:bg-gray-100 transition flex items-center gap-2 <?= $index === 0 ? 'active' : '' ?>">
                            <i class="fas fa-envelope"></i> <?= htmlspecialchars($smtp['name']) ?>
                            <?php if ($unreadCount > 0): ?>
                                <!-- <span class="bg-blue-500 text-white text-xs px-2 py-1 rounded-full"><?= $unreadCount ?></span> -->
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Account Content -->
            <?php foreach ($smtps as $index => $smtp):
                $replies = fetchReplies($smtp, $db);
                $accountStats = [
                    'total' => count($replies['regular'] ?? []) + count($replies['unsubscribes'] ?? []) + count($replies['bounces'] ?? []),
                    'replies' => count($replies['regular'] ?? []),
                    'unsubscribes' => count($replies['unsubscribes'] ?? []),
                    'bounces' => count($replies['bounces'] ?? [])
                ];
                ?>
                <div class="account-content <?= $index === 0 ? 'block' : 'hidden' ?>" id="account-<?= $index ?>">
                    <!-- Account Info -->
                    <div
                        class="flex flex-col md:flex-row gap-4 items-start md:items-center mb-6 bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-800 mb-1"><?= htmlspecialchars($smtp['name']) ?></h3>
                            <p class="text-gray-600 text-sm">
                                <i class="fas fa-server mr-1 text-blue-500"></i> <?= htmlspecialchars($smtp['host']) ?>
                                | <i class="fas fa-user mr-1 text-blue-500"></i> <?= htmlspecialchars($smtp['email']) ?>
                            </p>
                        </div>
                        <div class="flex gap-4">
                            <div class="text-center">
                                <div class="text-sm text-gray-500">Total</div>
                                <div class="font-bold text-blue-600"><?= $accountStats['total'] ?></div>
                            </div>
                            <div class="text-center">
                                <div class="text-sm text-gray-500">Replies</div>
                                <div class="font-bold text-green-600"><?= $accountStats['replies'] ?></div>
                            </div>
                            <div class="text-center">
                                <div class="text-sm text-gray-500">Unsubs</div>
                                <div class="font-bold text-red-600"><?= $accountStats['unsubscribes'] ?></div>
                            </div>
                            <div class="text-center">
                                <div class="text-sm text-gray-500">Bounces</div>
                                <div class="font-bold text-yellow-600"><?= $accountStats['bounces'] ?></div>
                            </div>
                        </div>
                    </div>

                    <?php if (isset($replies['error'])): ?>
                        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                <strong>Connection Error:</strong> <?= htmlspecialchars($replies['error']) ?>
                            </div>
                        </div>
                    <?php elseif (empty($replies['regular']) && empty($replies['unsubscribes']) && empty($replies['bounces'])): ?>
                        <div class="text-center py-12 bg-white rounded-lg shadow-sm border border-gray-200">
                            <i class="fas fa-inbox text-gray-300 text-5xl mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-700 mb-1">No new emails</h3>
                            <p class="text-gray-500">Your inbox is empty</p>
                        </div>
                    <?php else: ?>
                        <!-- Email Lists -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                            <!-- Regular Messages -->
                            <div class="lg:col-span-2">
                                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                                    <div class="p-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                                        <h3 class="text-lg font-semibold text-gray-700 flex items-center gap-2">
                                            <i class="fas fa-inbox text-blue-500"></i> Messages
                                            (<?= count($replies['regular']) ?>)
                                        </h3>
                                        <div class="flex gap-2">
                                            <button
                                                class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1 rounded transition">
                                                <i class="fas fa-filter mr-1"></i> Filter
                                            </button>
                                            <button
                                                class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1 rounded transition">
                                                <i class="fas fa-sort mr-1"></i> Sort
                                            </button>
                                        </div>
                                    </div>
                                    <div class="email-list divide-y divide-gray-200">
                                        <?php foreach ($replies['regular'] as $reply): ?>
                                            <div class="email-item p-4 cursor-pointer hover:bg-gray-50 transition relative"
                                                onclick="toggleEmailBody(this, <?= $index ?>, '<?= $reply['uid'] ?>')">
                                                <div class="flex items-start gap-3">
                                                    <div
                                                        class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-medium text-sm">
                                                        <?= getInitials($reply['from'] ?: $reply['from_email']) ?>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex justify-between items-baseline">
                                                            <p class="text-sm font-medium text-gray-900 truncate">
                                                                <?= htmlspecialchars($reply['from'] ?: $reply['from_email']) ?>
                                                            </p>
                                                            <p class="text-xs text-gray-500 ml-2">
                                                                <?= formatDate($reply['date']) ?>
                                                            </p>
                                                        </div>
                                                        <p class="text-sm font-medium text-gray-700 truncate mb-1">
                                                            <?= htmlspecialchars($reply['subject']) ?>
                                                        </p>
                                                        <p class="text-xs text-gray-500 email-preview">
                                                            <?= htmlspecialchars(substr(strip_tags($reply['body']), 0, 200)) ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="email-actions absolute right-4 top-4 flex gap-1">
                                                    <button
                                                        onclick="event.stopPropagation(); markAsRead(<?= $index ?>, '<?= $reply['uid'] ?>')"
                                                        class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 p-1 rounded transition">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button
                                                        onclick="event.stopPropagation(); archiveEmail(<?= $index ?>, '<?= $reply['uid'] ?>')"
                                                        class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 p-1 rounded transition">
                                                        <i class="fas fa-archive"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <!-- Email Body -->
                                            <div class="email-body hidden" id="email-body-<?= $index ?>-<?= $reply['uid'] ?>">
                                                <div class="p-4 border-b border-gray-200 bg-gray-50">
                                                    <div class="flex justify-between items-center">
                                                        <div>
                                                            <h4 class="text-sm font-medium text-gray-900">
                                                                <?= htmlspecialchars($reply['subject']) ?>
                                                            </h4>
                                                            <p class="text-xs text-gray-500">
                                                                From:
                                                                <?= htmlspecialchars($reply['from'] ?: $reply['from_email']) ?>
                                                            </p>
                                                        </div>
                                                        <div class="flex gap-2">
                                                            <button
                                                                onclick="replyToEmail('<?= $reply['from_email'] ?>', '<?= htmlspecialchars(addslashes($reply['subject'])) ?>')"
                                                                class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded transition">
                                                                <i class="fas fa-reply mr-1"></i> Reply
                                                            </button>
                                                            <p class="text-xs text-gray-500">
                                                                <?= formatDate($reply['date'], true) ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-4">
                                                    <div class="email-body-content bg-gray-50 p-3 rounded text-sm text-gray-700">
                                                        <?= nl2br(htmlspecialchars($reply['body'])) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>




                            <!-- Sidebar with Unsubscribes and Bounces -->
                            <div class="space-y-6">
                                <!-- Unsubscribe Requests -->
                                <?php if (!empty($replies['unsubscribes'])): ?>
                                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                                        <div class="p-4 border-b border-gray-200 bg-red-50 flex justify-between items-center">
                                            <h3 class="text-lg font-semibold text-red-700 flex items-center gap-2">
                                                <i class="fas fa-user-slash text-red-500"></i> Unsubscribes
                                                (<?= count($replies['unsubscribes']) ?>)
                                            </h3>
                                            <!-- <button onclick="processAllUnsubscribes(<?= $index ?>)"
                                                class="text-xs bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded transition">
                                                Process All
                                            </button> -->
                                        </div>
                                        <div class="divide-y divide-gray-200">
                                            <?php foreach ($replies['unsubscribes'] as $reply): ?>
                                                <div class="p-4 hover:bg-red-50 transition">
                                                    <div class="flex items-start gap-3">
                                                        <div
                                                            class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center">
                                                            <i class="fas fa-ban"></i>
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <div class="flex justify-between items-baseline">
                                                                <p class="text-sm font-medium text-gray-900 truncate">
                                                                    <?= htmlspecialchars($reply['from_email']) ?>
                                                                </p>
                                                                <span class="text-xs px-2 py-1 rounded-full unsubscribe-badge">
                                                                    Unsubscribe
                                                                </span>
                                                            </div>
                                                            <p class="text-xs text-gray-500 mt-1">
                                                                <?= htmlspecialchars($reply['unsubscribe_method']) ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <!-- <div class="mt-2 flex justify-end gap-2">
                                                        <button
                                                            onclick="event.stopPropagation(); viewEmail(<?= $index ?>, '<?= $reply['uid'] ?>')"
                                                            class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1 rounded transition">
                                                            View
                                                        </button>
                                                        <button
                                                            onclick="event.stopPropagation(); processUnsubscribe('<?= $reply['from_email'] ?>', <?= $index ?>, '<?= $reply['uid'] ?>')"
                                                            class="text-xs bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded transition">
                                                            Process
                                                        </button>
                                                    </div> -->
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Bounced Emails -->
                                <?php if (!empty($replies['bounces'])): ?>
                                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                                        <div class="p-4 border-b border-gray-200 bg-yellow-50 flex justify-between items-center">
                                            <h3 class="text-lg font-semibold text-yellow-700 flex items-center gap-2">
                                                <i class="fas fa-exclamation-triangle text-yellow-500"></i> Bounces
                                                (<?= count($replies['bounces']) ?>)
                                            </h3>
                                            <!-- <button onclick="processAllBounces(<?= $index ?>)"
                                                class="text-xs bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1 rounded transition">
                                                Process All
                                            </button> -->
                                        </div>
                                        <div class="divide-y divide-gray-200">
                                            <?php foreach ($replies['bounces'] as $reply): ?>
                                                <div class="p-4 hover:bg-yellow-50 transition">
                                                    <div class="flex items-start gap-3">
                                                        <div
                                                            class="flex-shrink-0 w-10 h-10 rounded-full bg-yellow-100 text-yellow-600 flex items-center justify-center">
                                                            <i class="fas fa-exclamation"></i>
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <div class="flex justify-between items-baseline">
                                                                <p class="text-sm font-medium text-gray-900 truncate">
                                                                    <?= htmlspecialchars($reply['from_email']) ?>
                                                                </p>
                                                                <span class="text-xs px-2 py-1 rounded-full bounce-badge">
                                                                    Bounced
                                                                </span>
                                                            </div>
                                                            <p class="text-xs text-gray-500 mt-1">
                                                                <?= htmlspecialchars($reply['bounce_reason']) ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <!-- <div class="mt-2 flex justify-end gap-2">
                                                        <button
                                                            onclick="event.stopPropagation(); viewEmail(<?= $index ?>, '<?= $reply['uid'] ?>')"
                                                            class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1 rounded transition">
                                                            View
                                                        </button>
                                                        <button
                                                            onclick="event.stopPropagation(); processBounce('<?= $reply['from_email'] ?>', <?= $index ?>, '<?= $reply['uid'] ?>')"
                                                            class="text-xs bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1 rounded transition">
                                                            Remove
                                                        </button>
                                                    </div> -->
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Loading Overlay -->
        <div id="loadingOverlay" class="progress-overlay hidden">
            <div class="circle-loader">
                <svg viewBox="0 0 36 36">
                    <circle class="circle-bg" cx="18" cy="18" r="16" stroke-width="2"></circle>
                    <circle class="circle-progress" cx="18" cy="18" r="16" stroke-width="2" stroke-dasharray="100 100"
                        stroke-dashoffset="100"></circle>
                </svg>
                <div class="loader-text">0%</div>
            </div>
            <div class="progress-label">Processing request...</div>
        </div>

        <!-- Reply Modal -->
        <div id="replyModal"
            class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">
                <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">Reply to Email</h3>
                    <button onclick="document.getElementById('replyModal').classList.add('hidden')"
                        class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="p-4 overflow-y-auto flex-1">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">To:</label>
                        <input type="text" id="replyTo" class="w-full px-3 py-2 border border-gray-300 rounded-md"
                            readonly>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subject:</label>
                        <input type="text" id="replySubject" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Message:</label>
                        <textarea id="replyMessage" rows="10"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                    </div>
                </div>
                <div class="p-4 border-t border-gray-200 flex justify-end gap-2">
                    <button onclick="document.getElementById('replyModal').classList.add('hidden')"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button onclick="sendReply()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        <i class="fas fa-paper-plane mr-2"></i> Send
                    </button>
                </div>
            </div>
        </div>

</body>

</html>