# behs3bahan UI — Activity diagrams

Mermaid **flowchart** diagrams (activity-style) for the React app in `behs3bahan_ui`. Preview in GitHub, VS Code Markdown preview with Mermaid support, or [mermaid.live](https://mermaid.live).

---

## 1. Public website usage

Covers `PublicLayout`, `Navbar` guest rules, `RequireAuth` deep links, login/register modals, and main member vs non-member navigation.

```mermaid
flowchart TB
  Start([Visitor opens site]) --> Init[App loads: AuthProvider restores token from localStorage]
  Init --> LoadUser{Valid token?}
  LoadUser -->|Yes| Session[User session active]
  LoadUser -->|No| Guest[Guest session]

  subgraph GuestPaths["Guest-only pages (no login)"]
    direction TB
    GHome[View home /]
    GContact[View /contact]
    GMap[View /contact/location]
    GNews[View /info/news and /info/news/:id]
    GAnn[View /info/announcement and /info/announcement/:id]
  end

  Guest --> GuestPaths
  Session --> GuestPaths

  Guest --> NavChoice{Navigate via menu or URL}
  Session --> NavChoice

  NavChoice --> PathCheck{Target is /, /contact, /contact/location, /info/news…, /info/announcement…?}
  PathCheck -->|Yes| GuestPaths

  PathCheck -->|No| AuthCheck{User authenticated?}
  AuthCheck -->|No| Block[Navbar blocks link OR deep link hits RequireAuth]
  Block --> Redirect[Navigate to / with state: openLogin + from path]
  Redirect --> OpenModal[PublicLayout opens Login modal]
  OpenModal --> Cred{Login or Register?}
  Cred -->|Register| Reg[Submit register form → API]
  Cred -->|Login| Log[Submit login form → API]
  Reg --> Store[Store token; set user in context]
  Log --> Store
  Store --> Session
  Store --> AfterLogin[Optional: user may revisit intended from path]

  AuthCheck -->|Yes| Protected[Route renders protected page]

  subgraph ProtectedSite["Authenticated public site routes"]
    direction TB
    P1[About: organization chart, former teachers]
    P2[Records feed /records — မှတ်တမ်းများ]
    P3[Organization rules]
    P4[Forum list and post detail]
    P5[User profile by ID /profiles/:userId]
    P6[Organization enrollment OR fee]
    P7[Member profile /profile]
  end

  Protected --> ProtectedSite

  Session --> RoleBranch{Organization menu: member role?}
  RoleBranch -->|Member| ShowFee[Show “member monthly fee” link → /organization-fee]
  RoleBranch -->|Not member| ShowEnroll[Show “apply for membership” link → /organization-enrollment]

  Session --> AdminLink{User is admin?}
  AdminLink -->|Yes| DashLink[Navbar shows link to /dashboard]
  AdminLink -->|No| NoDash[No dashboard link]

  Session --> MemberName{Member role?}
  MemberName -->|Yes| NameToProfile[Name in header links to /profile]
  MemberName -->|No| NamePlain[Name shown without profile link]

  Session --> LogoutChoice{User logs out?}
  LogoutChoice -->|Yes| Clear[Clear token and user; navigate to /]
  Clear --> Guest
  LogoutChoice -->|No| NavChoice
```

### Notes (website)

- **Guest-only URLs** without login: `/`, `/contact`, `/contact/location`, **`/info/news`** (+ `/info/news/:id`), **`/info/announcement`** (+ `/info/announcement/:id`), and `/info/rules-and-regulations` (see `Navbar.jsx` `isGuestAllowedPath`).
- All other public-layout routes are wrapped in `RequireAuth` in `App.jsx`; unauthenticated access redirects to `/` with `openLogin: true` (same as many navbar clicks).
- **Member** vs **non-member** changes the “Organization” submenu (`organization-fee` vs `organization-enrollment`).
- **Forum** (`/forum`) is now **read-open** for any logged-in user (and the view still counts), but writes (posts, comments, replies, edits, deletes) are restricted to members/admins both on the API and in the UI.
- **Records** (`/records`, label **မှတ်တမ်းများ**) is a **single Facebook-style feed page** (no dropdown). Any logged-in user can view AND react; only members and admins can create / edit / delete posts.

---

## 1b. Records feed usage (မှတ်တမ်းများ)

```mermaid
flowchart TB
  GStart([User opens /records]) --> GAuth{Authenticated?}
  GAuth -->|No| GRedirect[RequireAuth → /, open Login modal]
  GAuth -->|Yes| GLoad[GET /records paginated]
  GLoad --> GShow[Render feed cards: author, text, media grid, reaction summary, like button]

  GShow --> GRole{Role member or admin?}
  GRole -->|No| ViewOnly[Composer hidden — can view + react only]
  GRole -->|Yes| Composer[Show composer at top]

  Composer --> Pick{Pick action}
  Pick -->|Write text| Type[Type content]
  Pick -->|Add media| Files[Select any number of images or videos — no size or count limit]
  Type --> Post[Submit: chunked-upload each file then POST /records JSON with upload_ids]
  Files --> Post
  Post --> Stored[Backend moves finalized uploads into records/record_id/ and queues ProcessRecordMedia]
  Stored --> Prepend[Prepend new record to feed]

  GShow --> Card{For each record}
  Card --> CanMod{Author or admin?}
  CanMod -->|No| Read[Read text and media; lightbox for images, inline player for video]
  CanMod -->|Yes| Menu[Open … menu]
  Menu --> Choose{Choose}
  Choose -->|Edit| Edit[Modal: change text, remove existing media, add new media → POST /records/id]
  Choose -->|Delete| Confirm[Confirm dialog → DELETE /records/id]
  Confirm --> RemoveFolder[Backend deletes entire records/record_id/ folder]
  Edit --> Refresh[Replace record in feed]
  RemoveFolder --> Remove[Remove record from feed]

  Prepend --> More
  Refresh --> More
  Remove --> More
  Read --> More

  More{More records to load?} -->|Yes| Next[Click “next” → GET next page, append]
  More -->|No| GEnd([Done])
  Next --> GShow
```

---

## 1c. Reactions on a record (FB-style)

```mermaid
flowchart TB
  RStart([Any authenticated user views a record]) --> Has{Already reacted?}
  Has -->|Yes| Shown[Like button shows current emoji + label colored]
  Has -->|No| Default[Like button shows default 👍 ကြိုက်ရန်]

  Shown --> Input{User input}
  Default --> Input

  Input -->|Desktop hover 200ms| Picker[Reaction picker appears: 👍 ❤️ 😆 😮 😢 😡]
  Input -->|Mobile long-press 400ms| Picker
  Input -->|Single click / tap| Toggle{Currently reacted?}

  Toggle -->|No| LikeFast[Send POST /records/id/reactions type=like — optimistic UI]
  Toggle -->|Yes| Remove[Send DELETE /records/id/reactions — optimistic UI]

  Picker --> Choose{Pick emoji}
  Choose -->|Same as current| Remove
  Choose -->|Different / new| Set[Send POST /records/id/reactions type=chosen — replaces or creates]

  LikeFast --> Update[Server returns updated reaction_summary + my_reaction]
  Set --> Update
  Remove --> Update
  Update --> Reflect[Patch the record in feed state]

  Reflect --> Summary{Open “who reacted”?}
  Summary -->|Yes| Modal[Open modal → GET /records/id/reactions paginated]
  Modal --> Tabs[Tabs: All + non-zero types; lists user with emoji indicator]
  Tabs --> Profile{Click a reactor?}
  Profile -->|Yes| Visit[Navigate to /profiles/user_id]
  Profile -->|No| Close[Close modal]
  Summary -->|No| REnd([Done])
  Visit --> REnd
  Close --> REnd
```

## 1d-info. News & Announcements (public, no auth)

```mermaid
flowchart TB
  IStart([Visitor opens home /]) --> Home[Home shows 2-column “သတင်း နှင့် ကြေငြာချက်များ” panel]
  Home --> HomeFetch[Parallel GET /public/news + GET /public/announcements]
  HomeFetch --> Cards[Render up to 5 newest per column. No images on home preview. Green vs amber accents to distinguish.]
  Cards --> ClickKind{User clicks…}
  ClickKind -->|သတင်းများ → all| ListN[Navigate /info/news]
  ClickKind -->|ကြေငြာချက်များ → all| ListA[Navigate /info/announcement]
  ClickKind -->|One news item| DetN[Navigate /info/news/:id]
  ClickKind -->|One announcement| DetA[Navigate /info/announcement/:id]

  ListN --> FetchListN[GET /public/news]
  ListA --> FetchListA[GET /public/announcements]
  FetchListN --> Horizontal[Horizontal cards: image left, title/desc/“ဆက်ဖတ်ရန် →” right; stacks on mobile]
  FetchListA --> Horizontal

  Horizontal --> Pick{Click a card}
  Pick -->|Yes| Detail[GET /public/{kind}/:id → full body + hero image]

  DetN --> Detail
  DetA --> Detail
  Detail --> Back[Back-to-list link]
  Back --> Horizontal
```

### Notes (info pages)

- `/info/news` and `/info/announcement` (plus `:id` detail) are **fully public** — no login required.
- The old plural `/info/announcements` redirects to `/info/announcement`.
- Server only returns rows with `is_published = true` on public endpoints, newest first by `id`.
- Home page previews call the same public endpoints in parallel, render up to 5 newest per column, and skip images for a compact look. Each column has its own accent (green for news, amber for announcements) so they read as separate things.

---

## 1d. Forum read-only vs member/admin

```mermaid
flowchart TB
  FStart([Authenticated user opens /forum or /forum/postId]) --> FLoad[GET /forum/posts or GET /forum/posts/postId]
  FLoad --> FShow[Render posts / thread]
  FShow --> FView{Opened post detail?}
  FView -->|Yes| Bump[Backend records unique view → views_count++]
  FView -->|No| FRole

  Bump --> FRole{Role member or admin?}

  FRole -->|No — basic User| ReadOnly[Hide “new post”, “မှတ်ချက်”, “စာပြန်ရန်”; show banner: read-only]
  FRole -->|Yes — member or admin| Write{Pick write action}

  Write -->|Create post| NP[POST /forum/posts]
  Write -->|Comment on post| NC[POST /forum/posts/postId/comments]
  Write -->|Reply to comment| NR[POST /forum/posts/postId/comments with parent_id]
  Write -->|Admin: edit comment| EC[PATCH /forum/comments/commentId — admin only]
  Write -->|Admin: delete comment| DC[DELETE /forum/comments/commentId — admin only]

  NP --> Refresh[Refresh feed / thread]
  NC --> Refresh
  NR --> Refresh
  EC --> Refresh
  DC --> Refresh
  ReadOnly --> FEnd([Done])
  Refresh --> FEnd
```

### Notes (forum read-vs-write)

- `GET /forum/posts` and `GET /forum/posts/{postId}` are now only protected by `auth:sanctum`.
- All write endpoints (`POST /forum/posts`, `POST /forum/posts/{postId}/comments`, `PATCH/DELETE /forum/comments/{commentId}`) remain under `member_or_admin`.
- The UI hides the “new post”, “မှတ်ချက်”, and “စာပြန်ရန်” affordances for users without member/admin role, and shows a small banner explaining the read-only state.

---

## 1e. Chunked media upload (for records)

```mermaid
flowchart TB
  UStart([User picks files in composer / edit modal]) --> Loop{For each file}
  Loop --> Gen[Client generates upload_id and splits file into 5MB chunks]
  Gen --> Send[POST /records/uploads/chunk for chunk i]
  Send --> Save[Server saves chunk under uploads/user_id/upload_id/chunks/i, updates meta.json with file lock]
  Save --> Done{All chunks received?}
  Done -->|No| Bar[Server returns received / total — UI updates progress bar]
  Bar --> NextChunk[Send next chunk]
  NextChunk --> Save
  Done -->|Yes| Assemble[Server concatenates chunks into uploads/user_id/upload_id/final, status=ready]
  Assemble --> UploadDone[UI marks tile done ✓]

  UploadDone --> MoreFiles{More files?}
  MoreFiles -->|Yes| Loop
  MoreFiles -->|No| Attach[Client POST /records JSON with upload_ids array]

  Attach --> Verify[Server verifies each upload belongs to user, is ready, has allowed extension]
  Verify --> Move[Server moves final to records/record_id/random.ext on public disk]
  Move --> Row[Create RecordMedia row and dispatch ProcessRecordMedia job]
  Row --> Cleanup[Server removes temp uploads/user_id/upload_id/ folder]
  Cleanup --> Refresh[New record appears at top of feed]

  Refresh --> UEnd([Done])

  Cancel{User clicks ✕ before submit?} -.-> CancelCall[Optional: DELETE /records/uploads/upload_id]
```

### Notes (chunked uploads)

- No size or count caps on the frontend or backend. Only the **file extension whitelist** (`jpeg, jpg, png, gif, webp, mp4, mov, avi, webm, mkv`) is enforced for security.
- Per-request body is tiny (~5 MB), so PHP / Nginx limits can stay modest even for multi-GB videos.
- The chunked endpoint is **idempotent** for the same `(upload_id, chunk_index)`, so the client can safely retry on transient failures.
- `meta.json` writes are guarded with `flock(LOCK_EX)` so out-of-order or concurrent chunk uploads don't lose state.
- After attachment, the server enqueues `App\Jobs\ProcessRecordMedia` which currently re-verifies size / MIME from disk and is the hook point for future thumbnail / poster / virus-scan work.

---

### Notes (records + reactions)

- Backend route prefix is `/records` (no more `/gallery/...`).
- Storage folders are now `records/{record_id}/...`.
- The single navbar entry is labelled **မှတ်တမ်းများ** and points to `/records`.
- Reactions are limited to one per user per record, are immediately replaceable, and use optimistic UI on the client (rolled back on error).
- Mobile UX: long-press the Like button opens the picker; tap toggles like.

---

## 2. Admin dashboard usage

Covers `Dashboard.jsx` admin gate, `DashboardLayout` sidebar, and nested routes under `/dashboard/*`.

```mermaid
flowchart TB
  DStart([Admin chooses Dashboard]) --> DNav[Navigate to /dashboard or child path]
  DNav --> DLoad[Dashboard: wait for auth loading]
  DLoad --> IsAdmin{isAdmin in UI?\nrole_id === 1 or role slug/name admin}
  IsAdmin -->|No| Kick[Redirect to site home /]
  Kick --> DEnd([End])
  IsAdmin -->|Yes| Layout[Render DashboardLayout + Outlet]

  Layout --> Sidebar[Sidebar groups: Overview · User mgmt · School · Site content · Organization]
  Sidebar --> Pick{Select section}

  Pick --> OV[Overview /dashboard]
  Pick --> US[Users /dashboard/users]
  Pick --> RO[Roles /dashboard/roles]
  Pick --> TE[Teachers /dashboard/teachers]
  Pick --> NW[News /dashboard/news]
  Pick --> AN[Announcements /dashboard/announcements]
  Pick --> EN[Enrollments /dashboard/organization-members]
  Pick --> ME[Members /dashboard/members]
  Pick --> FE[Member Fees /dashboard/organization-fee]

  OV --> OVAct[Fetch GET /dashboard; show stats and user summary]
  US --> USAct[CRUD users via API]
  RO --> ROAct[CRUD roles via API]
  TE --> TEAct[CRUD teachers via API]
  NW --> NWAct[CRUD news via API. Form fields: title, body, single image, published toggle. No sorting, no published-at — UX simplified.]
  AN --> ANAct[CRUD announcements via API. Same form/UX as news.]
  EN --> ENAct[List pending enrollments; approve members]
  ME --> MEAct[List approved members]
  FE --> FEAct[Fee overview by year; review submissions]

  OVAct --> Loop{More admin work?}
  USAct --> Loop
  ROAct --> Loop
  TEAct --> Loop
  NWAct --> Loop
  ANAct --> Loop
  ENAct --> Loop
  MEAct --> Loop
  FEAct --> Loop

  Loop -->|Switch section| Pick
  Loop --> Back{Back to public site or logout?}

  Back -->|Back to site| Link[Header link “Back to site” → /]
  Back -->|Log out| Logout[Logout API + clear token; navigate to /]
  Link --> DEnd
  Logout --> DEnd
```

### Notes (dashboard)

- **Admin gate** is client-side in `Dashboard.jsx` (`isAdmin` from `AuthContext`). Non-admins are sent to `/` even if they type `/dashboard` manually.
- Sidebar labels map to routes in `DashboardLayout.jsx` and `App.jsx` nested routes.
- **Overview** loads aggregated data from `GET /api/dashboard` (see `Overview.jsx` / `authService.getDashboard`).
- **Site content** group hosts the **News** and **Announcements** admin screens. Both share `InfoPostsAdmin.jsx`, only the title and underlying service differ (`newsAdminService` vs `announcementAdminService`).

---

## Rendering

Same as `API_CLASS_DIAGRAM.md`: paste fenced blocks into [mermaid.live](https://mermaid.live) or use Markdown preview with Mermaid support.
