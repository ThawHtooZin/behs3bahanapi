<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessRecordMedia;
use App\Models\Record;
use App\Models\RecordMedia;
use App\Models\RecordReaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RecordController extends Controller
{
    public const REACTION_TYPES = ['like', 'love', 'haha', 'wow', 'sad', 'angry'];

    private const VIDEO_EXTS = ['mp4', 'mov', 'avi', 'webm', 'mkv'];
    private const ALLOWED_EXTS = [
        'jpeg', 'jpg', 'png', 'gif', 'webp',
        'mp4', 'mov', 'avi', 'webm', 'mkv',
    ];

    public function index(Request $request)
    {
        $paginator = Record::with(['user:id,name', 'media'])
            ->latest()
            ->paginate(10);

        $this->decorateRecords($paginator->getCollection(), $request->user());

        return response()->json($paginator);
    }

    public function show(Request $request, string $id)
    {
        $record = Record::with(['user:id,name', 'media'])->findOrFail($id);
        $this->decorateRecords(collect([$record]), $request->user());

        return response()->json(['record' => $record]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'content' => 'nullable|string|max:5000',
            'upload_ids' => 'nullable|array',
            'upload_ids.*' => 'string|max:64|regex:/^[A-Za-z0-9_\-]+$/',
        ]);

        $uploadIds = $validated['upload_ids'] ?? [];
        $hasContent = !empty(trim((string) ($validated['content'] ?? '')));
        if (!$hasContent && empty($uploadIds)) {
            return response()->json([
                'message' => 'A record must have text or at least one uploaded file.',
            ], 422);
        }

        $record = Record::create([
            'user_id' => $request->user()->id,
            'content' => $validated['content'] ?? null,
        ]);

        if (!empty($uploadIds)) {
            try {
                $this->attachUploads($record, $uploadIds, (int) $request->user()->id);
            } catch (\RuntimeException $e) {
                $record->delete();
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        $record->load(['user:id,name', 'media']);
        $this->decorateRecords(collect([$record]), $request->user());

        return response()->json([
            'message' => 'Record created successfully.',
            'record' => $record,
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        $record = Record::with('media')->findOrFail($id);
        $this->ensureCanModify($request, $record);

        $validated = $request->validate([
            'content' => 'nullable|string|max:5000',
            'remove_media_ids' => 'nullable|array',
            'remove_media_ids.*' => 'integer',
            'upload_ids' => 'nullable|array',
            'upload_ids.*' => 'string|max:64|regex:/^[A-Za-z0-9_\-]+$/',
        ]);

        if ($request->exists('content')) {
            $record->content = $validated['content'] ?? null;
        }
        $record->save();

        if (!empty($validated['remove_media_ids'])) {
            $toRemove = RecordMedia::where('record_id', $record->id)
                ->whereIn('id', $validated['remove_media_ids'])
                ->get();
            foreach ($toRemove as $media) {
                if ($media->path) {
                    Storage::disk('public')->delete($media->path);
                }
                $media->delete();
            }
        }

        if (!empty($validated['upload_ids'])) {
            $this->attachUploads($record, $validated['upload_ids'], (int) $request->user()->id);
        }

        $record->load(['user:id,name', 'media']);
        $this->decorateRecords(collect([$record]), $request->user());

        return response()->json([
            'message' => 'Record updated successfully.',
            'record' => $record,
        ]);
    }

    public function destroy(Request $request, string $id)
    {
        $record = Record::with('media')->findOrFail($id);
        $this->ensureCanModify($request, $record);

        $folder = "records/{$record->id}";
        if (Storage::disk('public')->exists($folder)) {
            Storage::disk('public')->deleteDirectory($folder);
        }

        $record->delete();

        return response()->json([
            'message' => 'Record deleted successfully.',
        ]);
    }

    public function react(Request $request, string $id)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(self::REACTION_TYPES)],
        ]);

        $record = Record::findOrFail($id);

        RecordReaction::updateOrCreate(
            ['record_id' => $record->id, 'user_id' => $request->user()->id],
            ['type' => $validated['type']]
        );

        return response()->json($this->reactionPayload($record, $request->user()));
    }

    public function unreact(Request $request, string $id)
    {
        $record = Record::findOrFail($id);

        RecordReaction::where('record_id', $record->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json($this->reactionPayload($record, $request->user()));
    }

    public function reactions(Request $request, string $id)
    {
        $record = Record::findOrFail($id);

        $query = RecordReaction::with('user:id,name')
            ->where('record_id', $record->id);

        $type = $request->query('type');
        if ($type && in_array($type, self::REACTION_TYPES, true)) {
            $query->where('type', $type);
        }

        $paginator = $query->latest()->paginate(50);

        return response()->json($paginator);
    }

    /**
     * Move each finalized upload into records/{record_id}/, create RecordMedia
     * rows, and queue post-processing.
     */
    private function attachUploads(Record $record, array $uploadIds, int $userId): void
    {
        $position = (int) ($record->media()->max('position') ?? 0);

        foreach ($uploadIds as $uploadId) {
            $baseDir = storage_path("app/uploads/{$userId}/{$uploadId}");
            $metaPath = "{$baseDir}/meta.json";
            $finalPath = "{$baseDir}/final";

            if (!is_file($metaPath) || !is_file($finalPath)) {
                throw new \RuntimeException("Upload '{$uploadId}' is not ready or does not exist.");
            }

            $meta = json_decode((string) file_get_contents($metaPath), true) ?: [];

            if (($meta['status'] ?? null) !== 'ready') {
                throw new \RuntimeException("Upload '{$uploadId}' is not ready yet.");
            }
            if ((int) ($meta['user_id'] ?? 0) !== $userId) {
                throw new \RuntimeException("Upload '{$uploadId}' does not belong to you.");
            }

            $originalName = (string) ($meta['original_name'] ?? 'file');
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_EXTS, true)) {
                throw new \RuntimeException("Files with extension .{$ext} are not allowed.");
            }
            $type = in_array($ext, self::VIDEO_EXTS, true) ? 'video' : 'image';

            $generated = Str::random(40).'.'.$ext;
            $targetDir = "records/{$record->id}";
            $targetPath = "{$targetDir}/{$generated}";

            $publicDisk = Storage::disk('public');
            $publicDisk->makeDirectory($targetDir);

            $absoluteTarget = $publicDisk->path($targetPath);
            if (!@rename($finalPath, $absoluteTarget)) {
                // Cross-filesystem fallback: copy then unlink.
                if (!@copy($finalPath, $absoluteTarget)) {
                    throw new \RuntimeException("Failed to finalize upload '{$uploadId}'.");
                }
                @unlink($finalPath);
            }

            $position++;

            $mimeType = $meta['mime_type'] ?? null;
            if (!$mimeType && function_exists('mime_content_type')) {
                $mimeType = @mime_content_type($absoluteTarget) ?: null;
            }

            $media = RecordMedia::create([
                'record_id' => $record->id,
                'type' => $type,
                'path' => $targetPath,
                'mime_type' => $mimeType,
                'size' => @filesize($absoluteTarget) ?: (int) ($meta['total_size'] ?? 0),
                'original_name' => $originalName,
                'position' => $position,
            ]);

            // Clean up the temp upload session dir.
            $this->rmdirRecursive($baseDir);

            ProcessRecordMedia::dispatch($media->id);
        }
    }

    private function decorateRecords($records, $user): void
    {
        if ($records->isEmpty()) {
            return;
        }

        $ids = $records->pluck('id');

        $counts = RecordReaction::whereIn('record_id', $ids)
            ->selectRaw('record_id, type, COUNT(*) as count')
            ->groupBy('record_id', 'type')
            ->get()
            ->groupBy('record_id');

        $mine = RecordReaction::whereIn('record_id', $ids)
            ->where('user_id', $user->id)
            ->pluck('type', 'record_id');

        $records->each(function (Record $record) use ($user, $counts, $mine) {
            $record->can_modify = $this->userCanModify($user, $record);
            $record->reaction_summary = $this->buildReactionSummary($counts->get($record->id, collect()));
            $record->my_reaction = $mine->get($record->id);
        });
    }

    private function reactionPayload(Record $record, $user): array
    {
        $counts = RecordReaction::where('record_id', $record->id)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->get();

        $summary = $this->buildReactionSummary($counts);

        $myReaction = RecordReaction::where('record_id', $record->id)
            ->where('user_id', $user->id)
            ->value('type');

        return [
            'record_id' => (int) $record->id,
            'reaction_summary' => $summary,
            'my_reaction' => $myReaction,
        ];
    }

    private function buildReactionSummary($rows): array
    {
        $byType = array_fill_keys(self::REACTION_TYPES, 0);
        $total = 0;
        foreach ($rows as $row) {
            $type = $row->type ?? null;
            if (!isset($byType[$type])) {
                continue;
            }
            $byType[$type] = (int) $row->count;
            $total += (int) $row->count;
        }

        $sorted = $byType;
        arsort($sorted);
        $topTypes = [];
        foreach ($sorted as $type => $count) {
            if ($count > 0) {
                $topTypes[] = $type;
            }
            if (count($topTypes) >= 3) {
                break;
            }
        }

        return [
            'by_type' => $byType,
            'total' => $total,
            'top_types' => $topTypes,
        ];
    }

    private function ensureCanModify(Request $request, Record $record): void
    {
        if (!$this->userCanModify($request->user(), $record)) {
            abort(response()->json([
                'message' => 'You cannot modify this record.',
            ], 403));
        }
    }

    private function userCanModify($user, Record $record): bool
    {
        if (!$user) {
            return false;
        }
        if ((int) ($user->role_id ?? 0) === 1) {
            return true;
        }
        return (int) $record->user_id === (int) $user->id;
    }

    private function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
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
