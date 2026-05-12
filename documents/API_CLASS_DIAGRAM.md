# behs3bahan API — Class diagram

This diagram reflects the Laravel Eloquent domain models in `behs3bahan_api/app/Models` and their relationships. Controllers inherit from `App\Http\Controllers\Controller` and orchestrate HTTP; they are summarized in a separate box.

## Domain model (Eloquent)

```mermaid
classDiagram
    direction TB

    class User {
        +int id
        +string name
        +string email
        +string password
        +int role_id
        +isAdmin() bool
        +hasDashboardAccess() bool
    }

    class Role {
        +int id
        +string name
        +string slug
        +string description
        +bool has_dashboard_access
    }

    class Member {
        +int id
        +int user_id
        +string name
        +string nrc_number
        +string gender
        +string image
        +date dob
        +string address
        +string contact_number
        +string email
        +bool agreed_to_rules
        +string status
        +datetime approved_at
        +int approved_by
        +string parent_name
        +string parent_occupation
    }

    class MemberFamilyMember {
        +int id
        +int member_id
        +string name
        +string relation
        +date dob
        +string nrc_number
    }

    class MembershipFeeSubmission {
        +int id
        +int member_id
        +int fee_year
        +int fee_month
        +string slip_image
        +date claimed_payment_date
        +int amount_mmk
        +string status
        +bool was_late
        +datetime reviewed_at
        +int reviewed_by
        +string rejection_reason
    }

    class OrganizationFeeSetting {
        +int id
        +int start_year
        +int start_month
        +datetime created_at
        +datetime updated_at
        +current() OrganizationFeeSetting
    }

    class Teacher {
        +int id
        +string name
        +string phone
        +string email
        +string address
        +string subject
        +string position
        +string photo
        +int from_year
        +int to_year
    }

    class ForumPost {
        +int id
        +int user_id
        +string category
        +string title
        +string content
        +int views_count
    }

    class ForumComment {
        +int id
        +int post_id
        +int user_id
        +int parent_id
        +int mentioned_user_id
        +string content
    }

    class ForumPostView {
        +int id
        +int post_id
        +int user_id
    }

    class ForumCommentMention {
        +int id
        +int comment_id
        +int mentioned_user_id
    }

    class Record {
        +int id
        +int user_id
        +string content
        +datetime created_at
        +datetime updated_at
    }

    class RecordMedia {
        +int id
        +int record_id
        +string type
        +string path
        +string mime_type
        +int size
        +string original_name
        +int position
    }

    class RecordReaction {
        +int id
        +int record_id
        +int user_id
        +string type
        +datetime created_at
    }

    class NewsItem {
        +int id
        +string title
        +string body
        +string image_path
        +bool is_published
        +datetime created_at
        +datetime updated_at
    }

    class Announcement {
        +int id
        +string title
        +string body
        +string image_path
        +bool is_published
        +datetime created_at
        +datetime updated_at
    }

    User "1" --> "0..1" Role : role_id
    Role "1" --> "*" User : users

    User "1" --> "0..1" Member : organization member profile
    Member "*" --> "1" User : user
    Member "*" --> "0..1" User : approver

    Member "1" --> "*" MemberFamilyMember : familyMembers
    MemberFamilyMember "*" --> "1" Member : member

    Member "1" --> "*" MembershipFeeSubmission : feeSubmissions
    MembershipFeeSubmission "*" --> "1" Member : member
    MembershipFeeSubmission "*" --> "0..1" User : reviewer

    User "1" --> "*" ForumPost : forumPosts
    ForumPost "*" --> "1" User : user

    User "1" --> "*" ForumComment : forumComments
    ForumComment "*" --> "1" ForumPost : post
    ForumComment "*" --> "1" User : user
    ForumComment "0..1" --> "0..*" ForumComment : parent and replies
    ForumComment "*" --> "0..1" User : mentionedUser

    ForumPost "1" --> "*" ForumPostView : viewRecords
    ForumPostView "*" --> "1" ForumPost : post
    ForumPostView "*" --> "1" User : user

    ForumComment "1" --> "*" ForumCommentMention : mentions
    ForumCommentMention "*" --> "1" ForumComment : comment
    ForumCommentMention "*" --> "1" User : mentionedUser

    User "1" --> "*" Record : author
    Record "*" --> "1" User : user
    Record "1" --> "*" RecordMedia : media
    RecordMedia "*" --> "1" Record : record
    Record "1" --> "*" RecordReaction : reactions
    RecordReaction "*" --> "1" Record : record
    RecordReaction "*" --> "1" User : user
```

**MembershipFeeSubmission** — one row per `(member_id, fee_year, fee_month)`. Arrears slip upload and **ကြိုပေး** slip upload both create/update multiple rows that **share the same `slip_image`** until admins approve/reject via batch review. **`OrganizationFeeSetting`** holds the single org-wide collection start (`start_year`, `start_month`); `current()` returns the singleton row.

## HTTP layer (controllers)

Controllers map routes to models and validation. They do not add persistent fields beyond request handling.

| Controller | Main responsibilities |
|------------|------------------------|
| `AuthController` | Register, login, logout; issues Sanctum tokens |
| `UserController` | Admin CRUD on `User`; `updateRole` |
| `RoleController` | Admin CRUD on `Role` |
| `TeacherController` | Public list; admin CRUD; photo storage |
| `OrganizationMemberController` | Enroll (`Member`); admin pending list and approve |
| `MemberController` | Admin list approved members |
| `MemberProfileController` | Current member profile, update, avatar; public-ish profile by `userId` |
| `OrganizationFeeController` | **`GET /organization-fee/me`** — arrears + prepay flags (`can_prepay`, `max_prepay_months_in_year`, etc.). **`POST …/submit`** — one slip fans out to pending rows for every unpaid month from org start through current month. **`POST …/submit-prepay`** — ကြိုပေး: one slip for the next N future months (same calendar year only; N ≤ `max_prepay_months_in_year`; gated when `outstanding_months` > 1). **`POST …/submissions/batch-review`** — admin confirms how many months a grouped slip covers (arrears or prepay). **`GET`/`PUT /organization-fee/settings`** — org collection start. **`GET …/overview`** — yearly grid + grouped pending list. Outstanding arrears count from **org collection start** only (not `approved_at`) |
| `ForumController` | Posts and comments; views; mention parsing |
| `RecordController` | မှတ်တမ်းများ feed (text + image/video) with FB-style reactions (6 types), per-record folder storage, owner/admin edit/delete; attaches finalized chunked uploads via `upload_ids[]` and dispatches `ProcessRecordMedia` job |
| `RecordUploadController` | Chunked upload sessions for record media (no size / count caps); writes chunks under `storage/app/uploads/{user}/{upload_id}/chunks/*` with file-locked `meta.json`; concatenates into `final` once complete |
| `NewsController` | Public list + detail of published `NewsItem`s; admin CRUD with optional single image upload (`news/`) |
| `AnnouncementController` | Public list + detail of published `Announcement`s; admin CRUD with optional single image upload (`announcements/`) |
| `DashboardController` | Admin dashboard stats |

## Middleware (cross-cutting)

| Middleware | Effect |
|------------|--------|
| `auth:sanctum` | Requires valid Bearer token for protected routes |
| `EnsureUserIsAdmin` | `role_id === 1` |
| `EnsureUserIsMemberOrAdmin` | Admin or role slug/name `member` (forum **writes**, record **writes**) |

For **end-to-end UI flows** (guest, member, admin, and each domain above), see `documents/ACTIVITY_DIAGRAMS.md`.

---

To render the Mermaid diagram, use GitHub, VS Code with a Mermaid preview extension, or [mermaid.live](https://mermaid.live).
