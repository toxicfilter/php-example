<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use ToxicFilter\Client;
use ToxicFilter\Exception\ApiError;
use ToxicFilter\Webhooks;
use Wall\Comments;

$comments = new Comments(__DIR__ . '/../data/comments.json');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// A person decided on a held comment in ToxicFilter: publish it or drop it.
if ($method === 'POST' && $path === '/webhooks/toxicfilter') {
    $event = Webhooks::event(
        file_get_contents('php://input'),                 // the RAW body, before any parsing
        $_SERVER['HTTP_X_TOXICFILTER_SIGNATURE'] ?? '',
        getenv('TOXICFILTER_WEBHOOK_SECRET') ?: '',
    );

    if ($event === null) {
        http_response_code(400);
        exit;
    }

    if ($event['event'] === 'moderation.resolved' && preg_match('/^comment_(\d+)$/', $event['data']['reference'] ?? '', $m)) {
        $event['data']['action'] === 'approved'
            ? $comments->publish((int) $m[1])
            : $comments->remove((int) $m[1]);
    }

    http_response_code(204);
    exit;
}

$name = '';
$body = '';
$error = null;

if ($method === 'POST' && $path === '/comments') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));

    if ($name === '' || $body === '') {
        $error = 'Write your name and a comment.';
    } else {
        $id = $comments->nextId();
        $tf = new Client(getenv('TOXICFILTER_KEY'));

        try {
            $verdict = $tf->text($body, [
                'surface' => 'comment',
                'reference' => "comment_{$id}",   // how the webhook finds this comment later
            ]);
        } catch (ApiError $e) {
            // Nobody could judge it: hold it rather than publish it unread.
            $comments->add($id, $name, $body, 'held', null);
            header('Location: /?held=1', true, 303);
            exit;
        }

        if ($verdict->blocked()) {
            $error = $verdict->reason() ?? 'This comment cannot be published.';
        } else {
            $comments->add($id, $name, $body, $verdict->needsReview() ? 'held' : 'published', $verdict->id());
            header('Location: /' . ($verdict->needsReview() ? '?held=1' : ''), true, 303);
            exit;
        }
    }
}

require __DIR__ . '/../views/wall.php';
