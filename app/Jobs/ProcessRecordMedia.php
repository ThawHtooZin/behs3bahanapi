<?php

namespace App\Jobs;

use App\Models\RecordMedia;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessRecordMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $mediaId)
    {
    }

    /**
     * Verify the stored file, refresh size/mime if needed.
     *
     * Future hooks (left as TODOs): video poster extraction (ffmpeg),
     * image thumbnail generation (GD/Imagick), virus scanning, etc.
     */
    public function handle(): void
    {
        $media = RecordMedia::find($this->mediaId);
        if (!$media) {
            return;
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($media->path)) {
            Log::warning('ProcessRecordMedia: file missing', [
                'media_id' => $media->id,
                'path' => $media->path,
            ]);
            return;
        }

        $fullPath = $disk->path($media->path);
        $actualSize = @filesize($fullPath) ?: $media->size;
        $actualMime = @mime_content_type($fullPath) ?: $media->mime_type;

        $changes = [];
        if ($media->size !== $actualSize) {
            $changes['size'] = $actualSize;
        }
        if ($media->mime_type !== $actualMime) {
            $changes['mime_type'] = $actualMime;
        }

        if (!empty($changes)) {
            $media->update($changes);
        }

        // TODO: extend with thumbnail / poster generation, virus scan, etc.
    }
}
