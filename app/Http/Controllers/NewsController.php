<?php

namespace App\Http\Controllers;

use App\Models\NewsItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NewsController extends Controller
{
    private const IMAGE_RULE = 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120';

    public function publicIndex()
    {
        $items = NewsItem::query()
            ->where('is_published', true)
            ->latest('id')
            ->get();

        return response()->json(['news' => $items]);
    }

    public function publicShow(string $id)
    {
        $item = NewsItem::where('id', $id)->where('is_published', true)->firstOrFail();

        return response()->json(['news' => $item]);
    }

    public function index()
    {
        $items = NewsItem::query()->latest('id')->get();

        return response()->json(['news' => $items]);
    }

    public function show(string $id)
    {
        $item = NewsItem::findOrFail($id);

        return response()->json(['news' => $item]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'image' => self::IMAGE_RULE,
            'is_published' => 'sometimes|boolean',
        ]);

        $data = collect($validated)->except(['image'])->all();
        $data['is_published'] = array_key_exists('is_published', $data) ? (bool) $data['is_published'] : true;

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('news', 'public');
        }

        $item = NewsItem::create($data);

        return response()->json(['message' => 'News created', 'news' => $item], 201);
    }

    public function update(Request $request, string $id)
    {
        $item = NewsItem::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'image' => self::IMAGE_RULE,
            'is_published' => 'sometimes|boolean',
        ]);

        $data = collect($validated)->except(['image'])->all();
        if (array_key_exists('is_published', $data)) {
            $data['is_published'] = (bool) $data['is_published'];
        }

        if ($request->hasFile('image')) {
            if ($item->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }
            $data['image_path'] = $request->file('image')->store('news', 'public');
        }

        $item->update($data);

        return response()->json(['message' => 'News updated', 'news' => $item->fresh()]);
    }

    public function destroy(string $id)
    {
        $item = NewsItem::findOrFail($id);
        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }
        $item->delete();

        return response()->json(['message' => 'News deleted']);
    }
}
