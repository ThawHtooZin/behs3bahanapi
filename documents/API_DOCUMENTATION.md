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

### `GET /public/news`

List **published** news items (no auth). Newest first by `id`.

**200** — `{ news: NewsItem[] }`

Each item:

```jsonc
{
  "id": 4,
  "title": "Welcome ceremony",
  "body": "Full text here ...",
  "image_path": "news/abc123.jpg",   // or null
  "is_published": true,
  "created_at": "2026-05-12T05:30:00.000000Z",
  "updated_at": "2026-05-12T05:30:00.000000Z"
}
```

### `GET /public/news/{id}`

Single published news item (no auth).

**200** — `{ news: NewsItem }`  
**404** — not found or unpublished.

### `GET /public/announcements`

List **published** announcements (no auth). Newest first by `id`. Same shape as news but the wrapper key is `announcements`.

**200** — `{ announcements: Announcement[] }`

### `GET /public/announcements/{id}`

Single published announcement (no auth).

**200** — `{ announcement: Announcement }`  
**404** — not found or unpublished.

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

### Organization fee — behavior summary

| Topic | Behavior |
|-------|------------|
| **Collection start** | Single org-wide month (`OrganizationFeeSetting`). Arrears run from that month through **today’s** calendar month. |
| **Arrears slip** | `POST /organization-fee/me/submit` — one image; server creates/refreshes **pending** rows for each unpaid month in that window (same `slip_image`). |
| **ကြိုပေး slip** | `POST /organization-fee/me/submit-prepay` — only if `outstanding_months` ≤ 1; `months_ahead` ≤ `max_prepay_months_in_year` (future months through **December of this year** only). |
| **Admin review** | Pending rows are grouped by member + `slip_image`. **`batch-review`** approves the first *N* months chronologically; uncovered rows are deleted or rejected. Same flow for arrears and prepay batches. |
| **Member UI** | `OrganizationFee.jsx`: tabs **နောက်ကျ** vs **ကြိုပေး** (prepay tab follows API `can_prepay`). |

---

### `GET /organization-fee/me`

Fee summary for current member (requires `Member`).

**200** — Payload includes:

- `member` — `{ id, name }`
- `monthly_fee_mmk` — fixed **3000**
- `current_month` — `{ year, month, status }` where `status` is `paid` | `pending` | `unpaid`
- `outstanding_months` — count of months from org start through the current month that are **not approved** (i.e. either `pending` or have no submission)
- `outstanding_amount_mmk` — `outstanding_months × monthly_fee_mmk`
- `pending_months` — subset of `outstanding_months` that already have a pending submission awaiting review
- `prepay_pending_months` — count of `pending` submissions in **future** months (after the current calendar month)
- `can_prepay` — `true` when `outstanding_months` ≤ 1, collection is active, and there is at least one future month left **in the current calendar year** for prepay
- `max_prepay_months_in_year` — maximum value allowed for **ကြိုပေး** `months_ahead`: `12 − current_calendar_month` (months from next month through December of this year; **0** in December)
- `collection_start_year`, `collection_start_month` — org-wide fee collection start
- `effective_start_year`, `effective_start_month` — equal to the org collection start
- `collection_active` — `true` if collection start ≤ current month
- `current_submission` — pending submission for **current** calendar month if any

Outstanding months are computed as: count of months between `effective_start_*` and the current month (inclusive) that have **no approved** submission. If collection has not yet started, this is `0`.

**404** — no member profile

---

### `POST /organization-fee/me/submit`

Upload **one** payment slip that covers **every outstanding month** from the org collection start through the current month. Members pay the whole arrears in one transfer (e.g. 3 months late ⇒ transfer `3 × monthly_fee` and upload that one slip). Multipart: `slip_image` required (image, jpeg/png/jpg/webp, max 4096 KB).

Server creates / refreshes a **pending** `MembershipFeeSubmission` row for each not-yet-approved month in `[org_start … current_month]`, all pointing at the uploaded slip image and the same `claimed_payment_date`. Admins still review per-row in the existing overview screen.

**200** — `{ message, months_covered, ...same fields as /organization-fee/me }`  
**404** — no member  
**409** — collection start is in the future, **or** there are no outstanding months

---

### `POST /organization-fee/me/submit-prepay`

**ကြိုပေး** — one slip for **future** months only, within the **same calendar year**. Multipart: `slip_image` (required, same image rules) and `months_ahead` (required integer ≥ 1, upper bound = **`max_prepay_months_in_year`** from `GET /organization-fee/me`). The server creates or refreshes **pending** rows for the next `months_ahead` calendar months **after** the current month (stopping at December of this year).

**Gate:** allowed only when **`outstanding_months` ≤ 1**. If `outstanding_months` ≥ 2 → **409**. If there are no months left in the current year after this month (December) → **409**.

If `months_ahead` exceeds the remaining months in the current year → **422**.

If any target month is already **approved**, returns **409** with an error message.

**200** — `{ message, months_covered, ...same fields as /organization-fee/me }`

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

**200** — `{ year, months, rows, pending_submissions, collection_start_year, collection_start_month, current_year, current_month }`

- `rows` — per member monthly status map. Each cell is one of:
  - `paid` — approved submission exists
  - `pending` — submission awaiting review
  - `unpaid` — month is in-range (between org collection start and the current month, inclusive) and not yet paid
  - `na_org` — before the org-wide collection start (not charged yet)
  - `na_future` — calendar month is after “today” **and** there is no submission row yet (**ကြိုပေး** creates rows so future cells become `pending` / `paid` / `unpaid` instead)

Note: every approved member is billed from the **org collection start** only (`approved_at` does not shorten the arrears window).

- `pending_submissions` — flat list of **pending** rows for the selected `year` (admin UI groups these by member + `slip_image` into batches; future-month rows appear here when ကြိုပေး was submitted)

---

### `PATCH /organization-fee/submissions/{id}/review`

Single-row review (rarely needed now — prefer the batch endpoint below).

**Body (JSON)**

| Field | Rules |
|-------|--------|
| `status` | required: `approved` or `rejected` |
| `rejection_reason` | optional, max 1000 (used when rejected) |

**200** — `{ message, submission }`

---

### `POST /organization-fee/submissions/batch-review`

Batch-review one **batch** = same member + same `slip_image` (arrears fan-out or ကြိုပေး fan-out). Members upload **one** slip covering multiple months; the server creates one `pending` row per target month sharing that image. This endpoint reviews **all** ids in that batch in one call (admin picks how many months the payment actually covered).

**Body (JSON)**

| Field | Rules |
|-------|--------|
| `ids` | required, array of submission ids (≥ 1). **All** must belong to the same member and the same `slip_image`, and must be `pending`. |
| `months_to_approve` | required integer ≥ 0, ≤ `ids.length`. Number of months the slip actually covers (e.g. 2 if the member transferred `2 × monthly_fee`). |
| `rejection_reason` | optional string (max 1000). Applies to the **uncovered** rows when `months_to_approve < ids.length`. |

Behavior, after sorting submissions by `(fee_year, fee_month)` ascending:

- **First `months_to_approve` rows** → `status = approved`, reviewer + timestamp recorded.
- **Remaining rows**:
  - If `rejection_reason` is provided → `status = rejected` with that reason.
  - Else → rows are **deleted** so those months return to "unpaid" and the member can re-upload.

**200** — `{ message, approved, rejected, discarded }`  
**404** — some `ids` not found  
**422** — mixed members / slips, non-pending rows, or `months_to_approve > ids.length`

---

### `GET /organization-fee/settings`

Read the org-wide fee collection start. Lazily-created with the current month on first read.

**200** — `{ start_year, start_month, current_year, current_month }`

---

### `PUT /organization-fee/settings`

Update the org-wide fee collection start.

**Body (JSON)**

| Field | Rules |
|-------|--------|
| `start_year` | required, integer 2000–2100 |
| `start_month` | required, integer 1–12 |

**200** — `{ message, start_year, start_month, current_year, current_month }`

Changing this immediately reshapes the admin overview NA cells and recomputes every member's `outstanding_months` on the next `GET /organization-fee/me`.

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

### News (admin) — `/news`

Site content for the public page `/info/news`. **Images only** (no video). Each item has one optional image.

| Method | Path | Action |
|--------|------|--------|
| GET | `/news` | List all news (includes unpublished). Newest first by `id`. |
| POST | `/news` | Create news item. |
| GET | `/news/{id}` | Show one. |
| PATCH | `/news/{id}` | Update fields and / or replace image. |
| DELETE | `/news/{id}` | Delete and remove stored image if present. |

**Body** for `POST` / `PATCH` (use **multipart** when sending `image`):

| Field | Rules |
|-------|--------|
| `title` | required, string, max 255 |
| `body` | required, string |
| `image` | optional, image jpeg/png/jpg/gif/webp, max **5120 KB** |
| `is_published` | optional boolean (defaults to `true` on create) |

On `POST`/`PATCH` with a new `image`, the old image (if any) is deleted from the `public` disk and the new file is stored under `news/`.

**201** (POST) — `{ message, news }`  
**200** (PATCH) — `{ message, news }`  
**200** (DELETE) — `{ message }`

### Announcements (admin) — `/announcements`

Same shape as News but for the public page `/info/announcement`. Image storage path is `announcements/`. List/return wrapper keys use `announcements` (list) and `announcement` (single).

| Method | Path | Action |
|--------|------|--------|
| GET | `/announcements` | List all (includes unpublished). |
| POST | `/announcements` | Create. |
| GET | `/announcements/{id}` | Show one. |
| PATCH | `/announcements/{id}` | Update. |
| DELETE | `/announcements/{id}` | Delete. |

Body fields are identical to `/news` above.

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
- `membership-fees/...` — slip images for **both** arrears (`submit`) and **ကြိုပေး** (`submit-prepay`); multiple `MembershipFeeSubmission` rows can reference the same stored path
- `records/{record_id}/...` — record (မှတ်တမ်းများ) media (one folder per record; deleted with the record)
- `app/uploads/{user_id}/{upload_id}/...` — **temporary** chunked-upload sessions on the `local` disk (cleaned up when attached to a record or cancelled)
- `news/...` — single image per news item (replaced/deleted with the item)
- `announcements/...` — single image per announcement (replaced/deleted with the item)

Build absolute URLs using your app’s public URL + `/storage/` + path (see `STORAGE_BASE_URL` in the UI config).

---

*Generated from `behs3bahan_api` routes and controllers. For behavior details, see source under `app/Http/Controllers` and `app/Models`.*
