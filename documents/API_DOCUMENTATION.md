# behs3bahan API — Reference

Laravel API in `behs3bahan_api`. JSON over HTTP. Route file: `routes/api.php`.

## Base URL

All paths below are relative to the API prefix (typically `/api`).

Example bases (from UI config):

- Development: `http://localhost:8001/api`
- Production: `https://behs3bahanapi.protechmm.com/api`

## Authentication

Protected routes use **Laravel Sanctum** personal access tokens.

1. `POST /register` or `POST /login` returns a `token` string.
2. Send header: `Authorization: Bearer {token}`
3. `POST /logout` revokes the current token.

Unauthenticated requests to protected routes receive **401**.

## Role and access rules

| Access | Condition |
|--------|-----------|
| Authenticated | Valid Sanctum token |
| Admin | `role_id === 1` |
| Member or admin (forum writes, record writes) | Admin **or** role slug/name case-insensitive `member` |

Default role on register: **User** (`role_id = 2` per `AuthController`).

---

## Public endpoints

### `POST /register`

Create user and return token.

**Body (JSON)**

| Field | Rules |
|-------|--------|
| `name` | required, string, max 255 |
| `email` | required, email, unique |
| `password` | required, min 8, `password_confirmation` must match |

**201** — `{ user, token, message }`

---

### `POST /login`

**Body (JSON)**

| Field | Rules |
|-------|--------|
| `email` | required, email |
| `password` | required |

**200** — `{ user, token, message }`  
**422** — validation / invalid credentials

---

### `GET /public/teachers`

List former teachers (no auth).

**200** — `{ teachers: Teacher[] }`

---

## Authenticated (any logged-in user)

Middleware: `auth:sanctum`.

### `GET /user`

Current user with `role` loaded.

**200** — User object (password hidden).

---

### `POST /logout`

**200** — `{ message }`

---

### `POST /members/enroll`

Submit organization enrollment (`Member`). Multipart or JSON with file fields as supported by Laravel.

**Body**

| Field | Rules |
|-------|--------|
| `name` | required, string, max 255 |
| `nrc_number` | required, unique on `members` |
| `gender` | required, string, max 50 |
| `image` | optional, image jpeg/png/jpg, max 2048 KB |
| `dob` | required, date, before today |
| `address` | required, string, max 2000 |
| `contact_number` | required, string, max 50 |
| `email` | required, email |
| `parent_name` | required, string |
| `parent_occupation` | required, string |
| `agreed_to_rules` | must be accepted (true) |
| `family_members` | optional JSON string: array of `{ name, relation, dob?, nrcNumber? }` |

**201** — `{ message, member, user }`  
**409** — already enrolled

---

### `GET /profiles/{userId}`

User summary + member record + `can_edit` (boolean, true only for self).

**200** — `{ user, member, can_edit }`

---

### `GET /members/profile`

Current user’s `Member` row.

**200** — `{ member }`  
**404** — no member profile

---

### `PATCH /members/profile`

**Body (JSON)**

| Field | Rules |
|-------|--------|
| `name` | required |
| `email` | required, email |
| `contact_number` | required |
| `address` | required |
| `parent_name` | optional |
| `parent_occupation` | optional |

**200** — `{ message, member }`  
**404** — no member profile

---

### `POST /members/profile/avatar`

Multipart: `image` required — image jpeg/png/jpg/webp, max 4096 KB.

**200** — `{ message, member }`

---

### `GET /organization-fee/me`

Fee summary for current member (requires `Member`).

**200** — Payload includes:

- `member` — `{ id, name }`
- `monthly_fee_mmk` — fixed **3000**
- `current_month` — `{ year, month, status }` where `status` is `paid` | `pending` | `unpaid`
- `outstanding_months`, `outstanding_amount_mmk`
- `current_submission` — pending submission for current month if any

**404** — no member profile

---

### `POST /organization-fee/me/submit`

Upload payment slip for **current calendar month**. Multipart: `slip_image` required (image, jpeg/png/jpg/webp, max 4096 KB).

**200** — `{ message, ...same as /organization-fee/me }`  
**404** — no member  
**409** — current month already approved

---

## Forum

**Reads** (`GET`) are available to any **authenticated** user (any role). A unique view per user still bumps the post’s `views_count`.

**Writes** (`POST` / `PATCH` / `DELETE`) require `member_or_admin`. **403** if the caller is neither admin nor member.

### `GET /forum/posts`

Middleware: `auth:sanctum`.

Optional query: `category` (exact match).

**200** — Laravel pagination JSON (default 10 per page): `data`, `current_page`, `last_page`, etc. Post items include `user` (id, name), `comments_count`.

---

### `GET /forum/posts/{postId}`

Middleware: `auth:sanctum`.

Loads post with nested `rootComments` (up to three levels of `replies`), users, `mentionedUser`. Records a unique view per user; may increment `views_count`.

**200** — `{ post }`

---

### `POST /forum/posts`

Middleware: `auth:sanctum`, `member_or_admin`.

**Body (JSON)**

| Field | Rules |
|-------|--------|
| `category` | required, one of: `ပညာရေး`, `ကျန်းမာရေး`, `လူမှုရေး`, `စီးပွားရေး`, `အခြား` |
| `title` | required, max 255 |
| `content` | required, max 5000 |

**201** — `{ message, post }`  
**403** — caller is not a member or admin.

---

### `POST /forum/posts/{postId}/comments`

Middleware: `auth:sanctum`, `member_or_admin`.

**Body (JSON)**

| Field | Rules |
|-------|--------|
| `content` | required, max 5000 |
| `parent_id` | optional, must exist in `forum_comments` and belong to same post |
| `mentioned_user_id` | optional, exists in `users` |

Mentions in content (`@Name`) are parsed; `ForumCommentMention` rows created.

**201** — `{ message, comment }`  
**403** — caller is not a member or admin.  
**422** — parent comment wrong post.

---

### `PATCH /forum/comments/{commentId}`

**Admin only** (`role_id === 1`). Others get **403**.

**Body:** `content` — required, max 5000.

**200** — `{ message, comment }`

---

### `DELETE /forum/comments/{commentId}`

**Admin only**.

**200** — `{ message }`

---

## Records — မှတ်တမ်းများ (Facebook-style feed)

Single feed page. Any **authenticated** user can view and react. Only **members** and **admins** can create/edit/delete posts. Each record can have text and/or media (images and videos, no enforced size or count limit — see [Chunked uploads](#chunked-uploads) below). Files are stored on the `public` disk under `records/{record_id}/` so each record owns a folder. Reactions are Facebook-style: one reaction per user per record, replaceable.

**Reaction types**: `like`, `love`, `haha`, `wow`, `sad`, `angry`.

### Record JSON shape

Every record returned from the API has this shape:

```jsonc
{
  "id": 12,
  "user_id": 3,
  "content": "Some text...",
  "created_at": "...",
  "updated_at": "...",
  "user": { "id": 3, "name": "Aung Aung" },
  "media": [
    {
      "id": 44,
      "record_id": 12,
      "type": "image",          // or "video"
      "path": "records/12/abc.jpg",
      "mime_type": "image/jpeg",
      "size": 102400,
      "original_name": "trip.jpg",
      "position": 1
    }
  ],
  "can_modify": true,
  "reaction_summary": {
    "by_type": { "like": 5, "love": 2, "haha": 0, "wow": 0, "sad": 0, "angry": 0 },
    "total": 7,
    "top_types": ["like", "love"]
  },
  "my_reaction": "love"          // or null
}
```

### `GET /records`

Middleware: `auth:sanctum`. Optional query: `page`. Paginated 10 per page.

**200** — Laravel paginator JSON; each item uses the record shape above.

### `GET /records/{id}`

Middleware: `auth:sanctum`.

**200** — `{ record }`.  
**404** — not found.

### `POST /records`

Middleware: `auth:sanctum`, `member_or_admin`. **JSON** body. Media is attached by reference (see [Chunked uploads](#chunked-uploads)) — not multipart on this endpoint.

| Field | Rules |
|-------|--------|
| `content` | optional, string, max 5000 |
| `upload_ids[]` | optional, list of `upload_id` strings from completed chunked uploads belonging to this user |

At least one of `content` (non-empty) or `upload_ids[]` is required.

**201** — `{ message, record }`.  
**403** — caller is not a member or admin.  
**422** — empty record, missing upload, or disallowed file extension.

### `POST /records/{id}` (update)

Middleware: `auth:sanctum`, `member_or_admin`. **JSON** body.

Allowed only when the caller is the **owner** or an **admin**.

| Field | Rules |
|-------|--------|
| `content` | optional, string, max 5000 (omitted → unchanged; sent empty → cleared) |
| `remove_media_ids[]` | optional, integer ids of existing media to delete |
| `upload_ids[]` | optional, list of completed chunked-upload ids to append (same rules as POST) |

**200** — `{ message, record }`.  
**403** — not the owner and not admin.  
**404** — not found.

### `DELETE /records/{id}`

Middleware: `auth:sanctum`, `member_or_admin`.

Allowed only when the caller is the **owner** or an **admin**. Removes the entire `records/{record_id}/` folder from storage.

**200** — `{ message }`.  
**403** — not the owner and not admin.

---

### Chunked uploads

Records media is uploaded in **chunks** to bypass PHP / web-server post-body limits and tolerate slow mobile networks. The client splits each file into ~5 MB chunks, posts them in order with the same `upload_id`, and then attaches the completed `upload_id`(s) when creating or updating a record. There is **no enforced file size or file count limit**; only the file extension whitelist still applies for security.

**Recommended server config** (each request only carries one ~5 MB chunk):

- `upload_max_filesize ≥ 10M`
- `post_max_size ≥ 12M`
- `memory_limit ≥ 256M`
- Nginx `client_max_body_size 12M;`

**Allowed extensions**: `jpeg, jpg, png, gif, webp, mp4, mov, avi, webm, mkv`.

#### `POST /records/uploads/chunk`

Middleware: `auth:sanctum`, `member_or_admin`. **Multipart** body.

| Field | Rules |
|-------|--------|
| `upload_id` | required, string, ≤64, matches `^[A-Za-z0-9_\-]+$` (UUID recommended) |
| `chunk_index` | required, integer, 0-based |
| `total_chunks` | required, integer, ≥1, ≤10000 |
| `original_name` | required, string, ≤255 |
| `total_size` | required, integer, ≥1 (full file size in bytes) |
| `mime_type` | optional, string, ≤128 |
| `chunk` | required, file (the raw chunk bytes) |

Each chunk is stored under `storage/app/uploads/{user_id}/{upload_id}/chunks/{chunk_index}` and tracked in `meta.json` (with file locking). When the last missing chunk arrives, the server concatenates all chunks into `…/final` and marks the session `ready`.

**200** — progress payload:

```jsonc
{
  "upload_id": "abc-123",
  "received_chunks": 4,
  "total_chunks": 10,
  "completed": false,
  "status": "pending"      // "pending" | "ready"
}
```

When all chunks have been received:

```jsonc
{
  "upload_id": "abc-123",
  "received_chunks": 10,
  "total_chunks": 10,
  "completed": true,
  "status": "ready"
}
```

Chunks are idempotent — sending the same `chunk_index` twice is safe.

#### `DELETE /records/uploads/{uploadId}`

Middleware: `auth:sanctum`, `member_or_admin`.

Cancels and removes the temporary upload directory for the caller (no-op if not found).

**200** — `{ message }`

#### Attaching uploads to a record

Once an upload reports `status: "ready"`, pass its `upload_id` in `upload_ids[]` to:

- `POST /records` — for new records, or
- `POST /records/{id}` — to append to an existing record.

The server then:

1. Verifies the upload session belongs to the caller and is `ready`.
2. Re-checks the original-file extension against the whitelist.
3. Moves the assembled file into `records/{record_id}/{random}.{ext}` on the `public` disk.
4. Creates a `RecordMedia` row (`type` derived from extension).
5. Removes the temporary upload directory.
6. Dispatches `App\Jobs\ProcessRecordMedia` for post-processing (verifies size / MIME on disk; placeholder for thumbnail / poster generation in the future).

Failures during step 1 or 2 return **422**; if step 3 cannot rename across filesystems, the server falls back to copy-then-unlink.

#### Background processing

`ProcessRecordMedia` runs through Laravel's queue. With the default `QUEUE_CONNECTION=sync`, the job executes inline at the end of the create/update request. To run truly asynchronously:

1. Set `QUEUE_CONNECTION=database` in `.env`.
2. The required `jobs` table already exists (`0001_01_01_000002_create_jobs_table.php`).
3. Start a worker: `php artisan queue:work`.

---

### Reactions

Any authenticated user (any role — `User`, `Member`, `Admin`) can react.

#### `POST /records/{id}/reactions`

Middleware: `auth:sanctum`.

**Body (JSON)**

| Field | Rules |
|-------|--------|
| `type` | required, one of `like`, `love`, `haha`, `wow`, `sad`, `angry` |

Behavior: upserts the caller's reaction on the record. Sending a different `type` replaces the existing reaction.

**200** — partial payload:

```jsonc
{
  "record_id": 12,
  "reaction_summary": { "by_type": { ... }, "total": 7, "top_types": ["love", "like"] },
  "my_reaction": "love"
}
```

#### `DELETE /records/{id}/reactions`

Middleware: `auth:sanctum`.

Removes the caller's reaction (no-op if none).

**200** — same shape as POST (with `my_reaction: null`).

#### `GET /records/{id}/reactions`

Middleware: `auth:sanctum`.

Optional query:

| Param | Notes |
|-------|--------|
| `type` | optional filter by one of the 6 reaction types |
| `page` | Laravel pagination (50 per page) |

**200** — Laravel paginator JSON; each item:

```jsonc
{
  "id": 8,
  "record_id": 12,
  "user_id": 3,
  "type": "love",
  "created_at": "...",
  "user": { "id": 3, "name": "Aung Aung" }
}
```

---

## Admin only

Middleware: `auth:sanctum`, `admin` (`role_id === 1`).

**403** — `{ message: "Unauthorized. Admin access required." }`

### `GET /dashboard`

**200** — `{ user, stats }` where `stats` has `total_users`, `total_admins`, `total_users_role`.

---

### `GET /members/pending`

**200** — `{ members: Member[] }` with `user.role`, `familyMembers`.

---

### `PATCH /members/{id}/approve`

Approve enrollment: sets member approved, assigns user role **member** (creates role slug `member` if missing).

**200** — `{ message, member, user }`  
**409** — already approved

---

### `GET /members`

All members (approved list style).

**200** — `{ members }`

---

### `GET /organization-fee/overview`

Query: `year` (optional, default current year).

**200** — `{ year, months, rows, pending_submissions }`

- `rows` — per member monthly status map (`paid` | `pending` | `unpaid`)
- `pending_submissions` — pending slips for that year

---

### `PATCH /organization-fee/submissions/{id}/review`

**Body (JSON)**

| Field | Rules |
|-------|--------|
| `status` | required: `approved` or `rejected` |
| `rejection_reason` | optional, max 1000 (used when rejected) |

**200** — `{ message, submission }`

---

### Users API resource — `/users`

Standard `apiResource` routes:

| Method | Path | Action |
|--------|------|--------|
| GET | `/users` | List users (id, name, email, role_id, created_at) + `role` |
| POST | `/users` | Create user |
| GET | `/users/{id}` | Show user + role |
| PUT/PATCH | `/users/{id}` | Update user |
| DELETE | `/users/{id}` | Delete user |

**POST /users** body: `name`, `email`, `password` (min 8), `role_id` (exists in roles). **201** — `{ message, user }`.

**PATCH /users/{id}`** — partial update; cannot change own `role_id` to a different role (**403**).

**DELETE /users/{id}`** — cannot delete self (**403**).

### `PUT /users/{id}/role`

Body: `role_id` required. Cannot change own role (**403**).

**200** — `{ message, user }`

---

### Roles API resource — `/roles`

| Method | Path | Action |
|--------|------|--------|
| GET | `/roles` | List roles |
| POST | `/roles` | Create role |
| GET | `/roles/{id}` | Show role + `users` |
| PUT/PATCH | `/roles/{id}` | Update role |
| DELETE | `/roles/{id}` | Delete role |

**POST** body: `name`, `slug` (unique), `description` optional, `has_dashboard_access` boolean optional.

**PATCH/DELETE** — roles with id **1** or **2** (default Admin/User) return **403**. Delete blocked if users still assigned (**403**).

---

### Teachers API resource — `/teachers`

| Method | Path | Action |
|--------|------|--------|
| GET | `/teachers` | List |
| POST | `/teachers` | Create |
| GET | `/teachers/{id}` | Show |
| PUT/PATCH | `/teachers/{id}` | Update |
| DELETE | `/teachers/{id}` | Delete |

**POST/PATCH** — `name` required; `phone`, `email`, `address`, `subject`, `position`, `from_year`, `to_year` optional; `photo` optional image (jpeg,png,jpg,gif max 2048 KB). Use **multipart** for file upload.

**DELETE** — removes stored photo if present.

---

## Common HTTP status codes

| Code | Typical cause |
|------|----------------|
| 200 | Success |
| 201 | Created |
| 401 | Missing/invalid token |
| 403 | Forbidden (admin/member/forum rules, role restrictions) |
| 404 | Model not found or no member profile |
| 409 | Conflict (duplicate enrollment, duplicate fee month) |
| 422 | Validation error (Laravel format: `message`, `errors`) |

---

## File storage

Uploaded files are stored on the `public` disk:

- `teachers/...` — teacher photos
- `members/...` — member avatar / enrollment image
- `membership-fees/...` — payment slip images
- `records/{record_id}/...` — record (မှတ်တမ်းများ) media (one folder per record; deleted with the record)
- `app/uploads/{user_id}/{upload_id}/...` — **temporary** chunked-upload sessions on the `local` disk (cleaned up when attached to a record or cancelled)

Build absolute URLs using your app’s public URL + `/storage/` + path (see `STORAGE_BASE_URL` in the UI config).

---

*Generated from `behs3bahan_api` routes and controllers. For behavior details, see source under `app/Http/Controllers` and `app/Models`.*
