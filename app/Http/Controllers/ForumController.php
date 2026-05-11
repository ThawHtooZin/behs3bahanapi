<?php

namespace App\Http\Controllers;

use App\Models\ForumComment;
use App\Models\ForumCommentMention;
use App\Models\ForumPost;
use App\Models\ForumPostView;
use App\Models\User;
use Illuminate\Http\Request;

class ForumController extends Controller
{
    private const ALLOWED_CATEGORIES = ['ပညာရေး', 'ကျန်းမာရေး', 'လူမှုရေး', 'စီးပွားရေး', 'အခြား'];

    public function index(Request $request)
    {
        $category = $request->query('category');
        $posts = ForumPost::with(['user:id,name'])
            ->withCount('comments')
            ->when($category, fn ($query) => $query->where('category', $category))
            ->latest()
            ->paginate(10);

        return response()->json($posts);
    }

    public function show(Request $request, string $postId)
    {
        $post = ForumPost::with([
            'user:id,name',
            'rootComments.user:id,name',
            'rootComments.mentionedUser:id,name',
            'rootComments.replies.user:id,name',
            'rootComments.replies.mentionedUser:id,name',
            'rootComments.replies.replies.user:id,name',
            'rootComments.replies.replies.mentionedUser:id,name',
        ])->withCount('comments')->findOrFail($postId);

        $viewRecord = ForumPostView::firstOrCreate([
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
        ]);
        if ($viewRecord->wasRecentlyCreated) {
            $post->increment('views_count');
            $post->refresh();
        }

        return response()->json([
            'post' => $post,
        ]);
    }

    public function storePost(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|string|in:'.implode(',', self::ALLOWED_CATEGORIES),
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:5000',
        ]);

        $post = ForumPost::create([
            'user_id' => $request->user()->id,
            'category' => $validated['category'],
            'title' => $validated['title'],
            'content' => $validated['content'],
        ]);

        return response()->json([
            'message' => 'Post created successfully.',
            'post' => $post->load('user:id,name'),
        ], 201);
    }

    public function storeComment(Request $request, string $postId)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:5000',
            'parent_id' => 'nullable|integer|exists:forum_comments,id',
            'mentioned_user_id' => 'nullable|integer|exists:users,id',
        ]);

        $post = ForumPost::findOrFail($postId);
        $parentId = $validated['parent_id'] ?? null;

        if ($parentId) {
            $parent = ForumComment::findOrFail($parentId);
            if ((int) $parent->post_id !== (int) $post->id) {
                return response()->json([
                    'message' => 'Parent comment does not belong to this post.',
                ], 422);
            }
        }

        $comment = ForumComment::create([
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
            'parent_id' => $parentId,
            'mentioned_user_id' => $validated['mentioned_user_id'] ?? $this->resolveMentionedUserId($validated['content']),
            'content' => $validated['content'],
        ]);

        $mentionedUserIds = $this->resolveMentionedUserIds($validated['content']);
        if (!empty($comment->mentioned_user_id)) {
            $mentionedUserIds[] = (int) $comment->mentioned_user_id;
        }
        $mentionedUserIds = array_values(array_unique($mentionedUserIds));
        foreach ($mentionedUserIds as $mentionedUserId) {
            ForumCommentMention::firstOrCreate([
                'comment_id' => $comment->id,
                'mentioned_user_id' => $mentionedUserId,
            ]);
        }

        return response()->json([
            'message' => 'Comment created successfully.',
            'comment' => $comment->load(['user:id,name', 'mentionedUser:id,name']),
        ], 201);
    }

    public function updateComment(Request $request, string $commentId)
    {
        if ((int) $request->user()->role_id !== 1) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $comment = ForumComment::findOrFail($commentId);
        $comment->content = $validated['content'];
        $comment->mentioned_user_id = $this->resolveMentionedUserId($validated['content']);
        $comment->save();

        return response()->json([
            'message' => 'Comment updated successfully.',
            'comment' => $comment->load(['user:id,name', 'mentionedUser:id,name']),
        ]);
    }

    public function destroyComment(Request $request, string $commentId)
    {
        if ((int) $request->user()->role_id !== 1) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $comment = ForumComment::findOrFail($commentId);
        $comment->delete();

        return response()->json([
            'message' => 'Comment deleted successfully.',
        ]);
    }

    private function resolveMentionedUserId(string $content): ?int
    {
        return $this->resolveMentionedUserIds($content)[0] ?? null;
    }

    /**
     * @return array<int>
     */
    private function resolveMentionedUserIds(string $content): array
    {
        preg_match_all('/@([^\s]+)/u', $content, $matches);
        $tokens = $matches[1] ?? [];
        if (empty($tokens)) {
            return [];
        }

        $ids = [];
        foreach ($tokens as $token) {
            $raw = trim((string) $token);
            if ($raw === '') {
                continue;
            }
            $user = User::whereRaw('LOWER(REPLACE(name, " ", "")) = ?', [mb_strtolower(str_replace(' ', '', $raw))])->first();
            if ($user) {
                $ids[] = (int) $user->id;
            }
        }

        return array_values(array_unique($ids));
    }
}
