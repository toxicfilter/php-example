<?php
/** @var \Wall\Comments $comments */
/** @var string $name */
/** @var string $body */
/** @var string|null $error */
$e = fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>The wall</title>
    <link rel="stylesheet" href="/wall.css">
</head>
<body>
    <h1>The wall</h1>

    <?php if (isset($_GET['held'])): ?>
        <p class="notice">Thanks. Your comment is waiting for a moderator.</p>
    <?php endif ?>

    <form method="post" action="/comments">
        <label>Name <input name="name" value="<?= $e($name) ?>" required></label>
        <label>Comment <textarea name="body" rows="3" required><?= $e($body) ?></textarea></label>
        <?php if ($error): ?><p class="error"><?= $e($error) ?></p><?php endif ?>
        <button type="submit">Post</button>
    </form>

    <?php foreach ($comments->published() as $comment): ?>
        <article>
            <strong><?= $e($comment['name']) ?></strong>
            <p><?= nl2br($e($comment['body'])) ?></p>
        </article>
    <?php endforeach ?>
</body>
</html>
