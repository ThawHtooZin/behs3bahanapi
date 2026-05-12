<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RecordUploadController extends Controller
{
    /**
     * Whitelisted file extensions (kept for security; size has no cap).
     */
    public const ALLOWED_EXTS = [
        'jpeg', 'jpg', 'png', 'gif', 'webp',
        'mp4', 'mov', 'avi', 'webm', 'mkv',
    ];

    private const MAX_CHUNKS = 10000; // 10000 * 5MB = 50 GB safety ceiling

    /**
     * Receive a single chunk of an upload session.
     *
     * Body (multipart):
     *   - upload_id     (string, client-generated, ≤64)
     *   - chunk_index   (int, 0-based)
     *   - total_chunks  (int)
     *   - original_name (string)
     *   - total_size    (int, bytes)
     *   - mime_type     (string, optional)
     *   - chunk         (file)
     */
    public function chunk(Request $request)
    {
        $validated = $request->validate([
            'upload_id' => 'required|string|max:64|regex:/^[A-Za-z0-9_\-]+$/',
            'chunk_index' => 'required|integer|min:0',
            'total_chunks' => 'required|integer|min:1|max:'.self::MAX_CHUNKS,
            'original_name' => 'required|string|max:255',
            'total_size' => 'required|integer|min:1',
            'mime_type' => 'nullable|string|max:128',
            'chunk' => 'required|file',
        ]);

        $userId = (int) $request->user()->id;
        $uploadId = $validated['upload_id'];
        $chunkIndex = (int) $validated['chunk_index'];
        $totalChunks = (int) $validated['total_chunks'];

        if ($chunkIndex >= $totalChunks) {
            return response()->json(['message' => 'chunk_index out of range.'], 422);
        }

        $ext = strtolower(pathinfo($validated['original_name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTS, true)) {
            return response()->json([
                'message' => "Files with extension .{$ext} are not allowed.",
            ], 422);
        }

        $baseDir = storage_path("app/uploads/{$userId}/{$uploadId}");
        $chunksDir = "{$baseDir}/chunks";
        if (!is_dir($chunksDir) && !mkdir($chunksDir, 0755, true) && !is_dir($chunksDir)) {
            return response()->json(['message' => 'Failed to create upload directory.'], 500);
        }

        // Persist this chunk.
        $request->file('chunk')->move($chunksDir, (string) $chunkIndex);

        // Atomically update meta.json with the received chunk index.
        $meta = $this->updateMeta($baseDir, function (array $existing) use (
            $validated,
            $chunkIndex,
            $totalChunks,
            $userId
        ) {
            $meta = $existing + [
                'user_id' => $userId,
                'upload_id' => $validated['upload_id'],
                'original_name' => $validated['original_name'],
                'mime_type' => $validated['mime_type'] ?? null,
                'total_size' => (int) $validated['total_size'],
                'total_chunks' => $totalChunks,
                'received' => [],
                'status' => 'pending',
                'created_at' => $existing['created_at'] ?? now()->toIso8601String(),
            ];
            $meta['received'] = array_values(array_unique(array_merge($meta['received'], [$chunkIndex])));
            sort($meta['received']);
            return $meta;
        });

        $completed = count($meta['received']) === $totalChunks;

        if ($completed && ($meta['status'] ?? null) !== 'ready') {
            $this->assemble($baseDir);
            $meta = $this->updateMeta($baseDir, function (array $existing) {
                $existing['status'] = 'ready';
                $existing['ready_at'] = now()->toIso8601String();
                return $existing;
            });
        }

        return response()->json([
            'upload_id' => $uploadId,
            'received_chunks' => count($meta['received']),
            'total_chunks' => $totalChunks,
            'completed' => $completed,
            'status' => $meta['status'] ?? 'pending',
        ]);
    }

    /**
     * Cancel an in-progress upload (or remove a ready-but-unused upload).
     */
    public function cancel(Request $request, string $uploadId)
    {
        $userId = (int) $request->user()->id;
        $baseDir = storage_path("app/uploads/{$userId}/{$uploadId}");
        if (is_dir($baseDir)) {
            $this->rmdirRecursive($baseDir);
        }

        return response()->json(['message' => 'Upload cancelled.']);
    }

    /**
     * Concatenate chunks/* into final and remove the chunks directory.
     */
    private function assemble(string $baseDir): void
    {
        $metaPath = "{$baseDir}/meta.json";
        if (!is_file($metaPath)) {
            throw new \RuntimeException('Upload session metadata missing.');
        }
        $meta = json_decode((string) file_get_contents($metaPath), true) ?: [];
        $total = (int) ($meta['total_chunks'] ?? 0);

        $finalPath = "{$baseDir}/final";
        $out = fopen($finalPath, 'wb');
        if ($out === false) {
            throw new \RuntimeException('Failed to open final file for writing.');
        }
        try {
            for ($i = 0; $i < $total; $i++) {
                $chunkPath = "{$baseDir}/chunks/{$i}";
                if (!is_file($chunkPath)) {
                    throw new \RuntimeException("Missing chunk {$i}.");
                }
                $in = fopen($chunkPath, 'rb');
                if ($in === false) {
                    throw new \RuntimeException("Could not read chunk {$i}.");
                }
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        } finally {
            fclose($out);
        }

        $chunksDir = "{$baseDir}/chunks";
        if (is_dir($chunksDir)) {
            foreach (glob("{$chunksDir}/*") as $file) {
                @unlink($file);
            }
            @rmdir($chunksDir);
        }
    }

    /**
     * Read / mutate / write meta.json with an exclusive file lock.
     *
     * @param  callable(array): array  $mutator
     */
    private function updateMeta(string $baseDir, callable $mutator): array
    {
        $path = "{$baseDir}/meta.json";
        if (!is_file($path)) {
            file_put_contents($path, json_encode([]));
        }
        $fp = fopen($path, 'r+');
        if ($fp === false) {
            throw new \RuntimeException('Failed to open meta.json.');
        }
        try {
            flock($fp, LOCK_EX);
            $raw = stream_get_contents($fp);
            $existing = $raw ? (json_decode($raw, true) ?: []) : [];
            $next = $mutator($existing);
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($next));
            fflush($fp);
            return $next;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    private function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = "{$dir}/{$item}";
            if (is_dir($path)) {
                $this->rmdirRecursive($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
