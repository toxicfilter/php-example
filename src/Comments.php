<?php

declare(strict_types=1);

namespace Wall;

/**
 * The comments, in a JSON file.
 *
 * A file and not a database so the demo has nothing to set up and the only code worth
 * reading is the moderation. Swap it for your own storage: nothing else depends on it.
 */
final class Comments
{
    /**
     * @param string $file
     */
    public function __construct(private string $file)
    {
    }

    /**
     * Published comments, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function published(): array
    {
        $published = array_filter($this->read(), fn (array $c) => $c['status'] === 'published');

        return array_reverse(array_values($published));
    }

    /**
     * The id the next comment will get, so the call to ToxicFilter can carry it.
     *
     * @return int
     */
    public function nextId(): int
    {
        return max([0, ...array_keys($this->read())]) + 1;
    }

    /**
     * @param int $id
     * @param string $name
     * @param string $body
     * @param string $status `published` or `held`.
     * @param string|null $verdictId ToxicFilter's id for the decision.
     * @return void
     */
    public function add(int $id, string $name, string $body, string $status, ?string $verdictId): void
    {
        $this->write(function (array $comments) use ($id, $name, $body, $status, $verdictId) {
            $comments[$id] = [
                'id' => $id,
                'name' => $name,
                'body' => $body,
                'status' => $status,
                'verdict' => $verdictId,
                'created_at' => date(DATE_ATOM),
            ];

            return $comments;
        });
    }

    /**
     * @param int $id
     * @return void
     */
    public function publish(int $id): void
    {
        $this->write(function (array $comments) use ($id) {
            if (isset($comments[$id])) {
                $comments[$id]['status'] = 'published';
            }

            return $comments;
        });
    }

    /**
     * @param int $id
     * @return void
     */
    public function remove(int $id): void
    {
        $this->write(function (array $comments) use ($id) {
            unset($comments[$id]);

            return $comments;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function read(): array
    {
        if (! is_file($this->file)) {
            return [];
        }

        return json_decode((string) file_get_contents($this->file), true) ?: [];
    }

    /**
     * Reads, changes and writes under a lock, so two requests cannot lose each other's comment.
     *
     * @param callable(array<int, array<string, mixed>>): array<int, array<string, mixed>> $change
     * @return void
     */
    private function write(callable $change): void
    {
        $handle = fopen($this->file, 'c+');
        flock($handle, LOCK_EX);

        $current = json_decode((string) stream_get_contents($handle), true) ?: [];
        $next = $change($current);

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string) json_encode($next, JSON_PRETTY_PRINT));
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
