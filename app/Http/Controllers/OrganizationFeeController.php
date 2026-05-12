<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MembershipFeeSubmission;
use App\Models\OrganizationFeeSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OrganizationFeeController extends Controller
{
    private const MONTHLY_FEE_MMK = 3000;

    public function me(Request $request)
    {
        $member = $request->user()
            ->member()
            ->with(['feeSubmissions'])
            ->first();

        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found.',
            ], 404);
        }

        return response()->json($this->buildMemberFeePayload($member));
    }

    public function submit(Request $request)
    {
        $member = $request->user()
            ->member()
            ->with(['feeSubmissions'])
            ->first();

        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found.',
            ], 404);
        }

        $request->validate([
            'slip_image' => 'required|image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);

        $now = now();
        $setting = OrganizationFeeSetting::current();
        $orgStart = Carbon::create($setting->start_year, $setting->start_month, 1)->startOfMonth();
        $currentMonthStart = $now->copy()->startOfMonth();

        if ($currentMonthStart->lt($orgStart)) {
            return response()->json([
                'message' => 'Fee collection has not started yet.',
            ], 409);
        }

        // One slip covers every month from the org collection start through the
        // current month that does NOT already have an approved submission.
        $approvedKeys = $member->feeSubmissions
            ->where('status', 'approved')
            ->map(fn ($s) => sprintf('%04d-%02d', $s->fee_year, $s->fee_month))
            ->all();

        $monthsToCover = [];
        $cursor = $orgStart->copy();
        while ($cursor->lte($currentMonthStart)) {
            if (!in_array($cursor->format('Y-m'), $approvedKeys, true)) {
                $monthsToCover[] = [(int) $cursor->year, (int) $cursor->month];
            }
            $cursor->addMonth();
        }

        if (empty($monthsToCover)) {
            return response()->json([
                'message' => 'No outstanding months to pay.',
            ], 409);
        }

        $slipPath = $request->file('slip_image')->store('membership-fees', 'public');
        $claimedDate = $now->toDateString();

        foreach ($monthsToCover as [$y, $m]) {
            $existing = MembershipFeeSubmission::where('member_id', $member->id)
                ->where('fee_year', $y)
                ->where('fee_month', $m)
                ->first();

            $payload = [
                'slip_image' => $slipPath,
                'claimed_payment_date' => $claimedDate,
                'amount_mmk' => self::MONTHLY_FEE_MMK,
                'status' => 'pending',
                'was_late' => false,
                'reviewed_at' => null,
                'reviewed_by' => null,
                'rejection_reason' => null,
            ];

            if ($existing) {
                $existing->update($payload);
            } else {
                MembershipFeeSubmission::create(array_merge([
                    'member_id' => $member->id,
                    'fee_year' => $y,
                    'fee_month' => $m,
                ], $payload));
            }
        }

        $member->load(['feeSubmissions']);

        return response()->json([
            'message' => 'Slip uploaded successfully.',
            'months_covered' => count($monthsToCover),
            ...$this->buildMemberFeePayload($member),
        ]);
    }

    /**
     * ကြိုပေး — pay ahead for future calendar months (one slip).
     * Allowed only when arrears (ပေးရန် လ) are 1 month or fewer.
     */
    public function submitPrepay(Request $request)
    {
        $member = $request->user()
            ->member()
            ->with(['feeSubmissions'])
            ->first();

        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found.',
            ], 404);
        }

        $validated = $request->validate([
            'slip_image' => 'required|image|mimes:jpeg,png,jpg,webp|max:4096',
            'months_ahead' => 'required|integer|min:1',
        ]);

        $now = now();
        $setting = OrganizationFeeSetting::current();
        $orgStart = Carbon::create($setting->start_year, $setting->start_month, 1)->startOfMonth();
        $currentMonthStart = $now->copy()->startOfMonth();

        if ($currentMonthStart->lt($orgStart)) {
            return response()->json([
                'message' => 'Fee collection has not started yet.',
            ], 409);
        }

        $calMonth = (int) $now->month;
        $maxMonthsUntilYearEnd = max(0, 12 - $calMonth);

        if ($maxMonthsUntilYearEnd === 0) {
            return response()->json([
                'message' => 'ဒီနှစ်အတွင်း ကြိုပေး လုပ်ရန် မကျန်တော့ပါ။',
            ], 409);
        }

        $payload = $this->buildMemberFeePayload($member);
        $outstanding = (int) ($payload['outstanding_months'] ?? 0);

        if ($outstanding > 1) {
            return response()->json([
                'message' => 'ကြိုပေး လုပ်နိုင်ရန် ပေးရန်ကျန်သော လ နှစ်လထက် မပိုရပါ။ အရင်ဆုံး နောက်ကျကြေးများ ပြီးပါစေ။',
            ], 409);
        }

        $monthsAhead = (int) $validated['months_ahead'];

        if ($monthsAhead > $maxMonthsUntilYearEnd) {
            return response()->json([
                'message' => sprintf(
                    'ကြိုပေး လုပ်နိုင်သော လ အများဆုံး %d လ သာဖြစ်ပါသည်။',
                    $maxMonthsUntilYearEnd
                ),
            ], 422);
        }
        $monthsToCover = [];
        $cursor = $currentMonthStart->copy()->addMonth();

        for ($i = 0; $i < $monthsAhead; $i++) {
            $monthsToCover[] = [(int) $cursor->year, (int) $cursor->month];
            $cursor->addMonth();
        }

        foreach ($monthsToCover as [$y, $m]) {
            $existing = MembershipFeeSubmission::where('member_id', $member->id)
                ->where('fee_year', $y)
                ->where('fee_month', $m)
                ->first();

            if ($existing && $existing->status === 'approved') {
                return response()->json([
                    'message' => sprintf(
                        'လ %04d-%02d ကို ပေးပြီးသားဟု အတည်ပြုပြီးပါပြီ။ ကြိုပေး မလုပ်နိုင်ပါ။',
                        $y,
                        $m
                    ),
                ], 409);
            }
        }

        $slipPath = $request->file('slip_image')->store('membership-fees', 'public');
        $claimedDate = $now->toDateString();

        foreach ($monthsToCover as [$y, $m]) {
            $existing = MembershipFeeSubmission::where('member_id', $member->id)
                ->where('fee_year', $y)
                ->where('fee_month', $m)
                ->first();

            $rowPayload = [
                'slip_image' => $slipPath,
                'claimed_payment_date' => $claimedDate,
                'amount_mmk' => self::MONTHLY_FEE_MMK,
                'status' => 'pending',
                'was_late' => false,
                'reviewed_at' => null,
                'reviewed_by' => null,
                'rejection_reason' => null,
            ];

            if ($existing) {
                $existing->update($rowPayload);
            } else {
                MembershipFeeSubmission::create(array_merge([
                    'member_id' => $member->id,
                    'fee_year' => $y,
                    'fee_month' => $m,
                ], $rowPayload));
            }
        }

        $member->load(['feeSubmissions']);

        return response()->json([
            'message' => 'ကြိုပေး slip တင်ပြီးပါပြီ။',
            'months_covered' => count($monthsToCover),
            ...$this->buildMemberFeePayload($member),
        ]);
    }

    public function batchReview(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'months_to_approve' => 'required|integer|min:0',
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['ids'])));
        $monthsToApprove = (int) $validated['months_to_approve'];

        if ($monthsToApprove > count($ids)) {
            return response()->json([
                'message' => 'months_to_approve cannot exceed the number of submissions in the batch.',
            ], 422);
        }

        $submissions = MembershipFeeSubmission::whereIn('id', $ids)->get();

        if ($submissions->count() !== count($ids)) {
            return response()->json([
                'message' => 'One or more submissions were not found.',
            ], 404);
        }

        $first = $submissions->first();
        $memberId = $first->member_id;
        $slipImage = $first->slip_image;

        foreach ($submissions as $submission) {
            if ($submission->member_id !== $memberId || $submission->slip_image !== $slipImage) {
                return response()->json([
                    'message' => 'All submissions must belong to the same batch (same member and slip).',
                ], 422);
            }
            if ($submission->status !== 'pending') {
                return response()->json([
                    'message' => 'All submissions must be pending.',
                ], 422);
            }
        }

        $sorted = $submissions->sortBy([
            ['fee_year', 'asc'],
            ['fee_month', 'asc'],
        ])->values();

        $now = now();
        $reviewerId = $request->user()->id;
        $rejectionReason = $validated['rejection_reason'] ?? null;

        $approved = 0;
        $discarded = 0;
        $rejected = 0;

        foreach ($sorted as $index => $submission) {
            if ($index < $monthsToApprove) {
                $submission->status = 'approved';
                $submission->reviewed_at = $now;
                $submission->reviewed_by = $reviewerId;
                $submission->rejection_reason = null;
                $submission->save();
                $approved++;
            } elseif ($rejectionReason !== null && $rejectionReason !== '') {
                $submission->status = 'rejected';
                $submission->reviewed_at = $now;
                $submission->reviewed_by = $reviewerId;
                $submission->rejection_reason = $rejectionReason;
                $submission->save();
                $rejected++;
            } else {
                $submission->delete();
                $discarded++;
            }
        }

        return response()->json([
            'message' => 'Batch reviewed successfully.',
            'approved' => $approved,
            'rejected' => $rejected,
            'discarded' => $discarded,
        ]);
    }

    public function review(Request $request, string $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        $submission = MembershipFeeSubmission::findOrFail($id);
        $submission->status = $validated['status'];
        $submission->reviewed_at = now();
        $submission->reviewed_by = $request->user()->id;
        $submission->rejection_reason = $validated['status'] === 'rejected'
            ? ($validated['rejection_reason'] ?? null)
            : null;
        $submission->save();

        return response()->json([
            'message' => 'Fee submission reviewed successfully.',
            'submission' => $submission,
        ]);
    }

    public function adminOverview(Request $request)
    {
        $year = (int) ($request->query('year', now()->year));
        $months = range(1, 12);

        $setting = OrganizationFeeSetting::current();
        $startYear = (int) $setting->start_year;
        $startMonth = (int) $setting->start_month;

        $now = now();
        $currentYear = (int) $now->year;
        $currentMonth = (int) $now->month;

        $members = Member::with(['feeSubmissions' => function ($query) use ($year) {
            $query->where('fee_year', $year);
        }])->orderBy('name')->get();

        $rows = $members->map(function (Member $member) use ($months, $year, $startYear, $startMonth, $currentYear, $currentMonth) {
            $statuses = [];
            foreach ($months as $month) {
                $isBeforeOrgStart = ($year < $startYear) || ($year === $startYear && $month < $startMonth);
                $isFuture = ($year > $currentYear) || ($year === $currentYear && $month > $currentMonth);

                if ($isBeforeOrgStart) {
                    $statuses[(string) $month] = 'na_org';
                    continue;
                }
                if ($isFuture) {
                    $submission = $member->feeSubmissions->firstWhere('fee_month', $month);
                    if ($submission) {
                        $statuses[(string) $month] = match ($submission->status) {
                            'approved' => 'paid',
                            'pending' => 'pending',
                            default => 'unpaid',
                        };
                    } else {
                        $statuses[(string) $month] = 'na_future';
                    }
                    continue;
                }

                $submission = $member->feeSubmissions->firstWhere('fee_month', $month);
                $statuses[(string) $month] = match ($submission?->status) {
                    'approved' => 'paid',
                    'pending' => 'pending',
                    default => 'unpaid',
                };
            }

            return [
                'member_id' => $member->id,
                'name' => $member->name,
                'statuses' => $statuses,
            ];
        })->values();

        $pendingSubmissions = MembershipFeeSubmission::with('member')
            ->where('status', 'pending')
            ->where('fee_year', $year)
            ->latest()
            ->get()
            ->map(function (MembershipFeeSubmission $submission) {
                return [
                    'id' => $submission->id,
                    'member_name' => $submission->member?->name,
                    'fee_year' => $submission->fee_year,
                    'fee_month' => $submission->fee_month,
                    'claimed_payment_date' => $submission->claimed_payment_date
                        ? (string) $submission->claimed_payment_date
                        : null,
                    'slip_image' => $submission->slip_image,
                ];
            })
            ->values();

        return response()->json([
            'year' => $year,
            'months' => $months,
            'rows' => $rows,
            'pending_submissions' => $pendingSubmissions,
            'collection_start_year' => $startYear,
            'collection_start_month' => $startMonth,
            'current_year' => $currentYear,
            'current_month' => $currentMonth,
        ]);
    }

    public function getSettings(Request $request)
    {
        $setting = OrganizationFeeSetting::current();
        $now = now();

        return response()->json([
            'start_year' => (int) $setting->start_year,
            'start_month' => (int) $setting->start_month,
            'current_year' => (int) $now->year,
            'current_month' => (int) $now->month,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'start_year' => 'required|integer|min:2000|max:2100',
            'start_month' => 'required|integer|min:1|max:12',
        ]);

        $setting = OrganizationFeeSetting::current();
        $setting->update([
            'start_year' => $validated['start_year'],
            'start_month' => $validated['start_month'],
        ]);

        $now = now();

        return response()->json([
            'message' => 'Collection start updated.',
            'start_year' => (int) $setting->start_year,
            'start_month' => (int) $setting->start_month,
            'current_year' => (int) $now->year,
            'current_month' => (int) $now->month,
        ]);
    }

    private function buildMemberFeePayload(Member $member): array
    {
        $current = now();
        $currentKey = $current->format('Y-m');
        $approvedKeys = $member->feeSubmissions
            ->where('status', 'approved')
            ->map(fn ($submission) => sprintf('%04d-%02d', $submission->fee_year, $submission->fee_month))
            ->values()
            ->all();

        $pendingCurrent = $member->feeSubmissions
            ->first(fn ($submission) => $submission->fee_year === (int) $current->year
                && $submission->fee_month === (int) $current->month
                && $submission->status === 'pending');

        $setting = OrganizationFeeSetting::current();
        $orgStart = Carbon::create($setting->start_year, $setting->start_month, 1)->startOfMonth();

        // Every member is billed from the org collection start, regardless of when they joined.
        $startDate = $orgStart->copy();

        $end = $current->copy()->startOfMonth();
        $outstandingMonths = 0;

        // If collection hasn't started yet relative to today, there is nothing owed.
        if ($startDate->lte($end)) {
            $cursor = $startDate->copy();
            while ($cursor->lte($end)) {
                if (!in_array($cursor->format('Y-m'), $approvedKeys, true)) {
                    $outstandingMonths++;
                }
                $cursor->addMonth();
            }
        }

        $collectionActive = $orgStart->lte($end);

        $currentMonthStatus = in_array($currentKey, $approvedKeys, true)
            ? 'paid'
            : ($pendingCurrent ? 'pending' : 'unpaid');

        $pendingMonths = $member->feeSubmissions
            ->where('status', 'pending')
            ->filter(function ($s) use ($startDate, $end) {
                $d = Carbon::create((int) $s->fee_year, (int) $s->fee_month, 1)->startOfMonth();
                return $d->gte($startDate) && $d->lte($end);
            })
            ->count();

        $currentMonthStartForCompare = $current->copy()->startOfMonth();
        $prepayPendingMonths = $member->feeSubmissions
            ->where('status', 'pending')
            ->filter(function ($s) use ($currentMonthStartForCompare) {
                $d = Carbon::create((int) $s->fee_year, (int) $s->fee_month, 1)->startOfMonth();
                return $d->gt($currentMonthStartForCompare);
            })
            ->count();

        $calMonthForPrepay = (int) $current->month;
        $maxPrepayMonthsInYear = max(0, 12 - $calMonthForPrepay);

        $canPrepay = $collectionActive && $outstandingMonths <= 1 && $maxPrepayMonthsInYear > 0;

        return [
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
            ],
            'monthly_fee_mmk' => self::MONTHLY_FEE_MMK,
            'current_month' => [
                'year' => (int) $current->year,
                'month' => (int) $current->month,
                'status' => $currentMonthStatus,
            ],
            'outstanding_months' => $outstandingMonths,
            'outstanding_amount_mmk' => $outstandingMonths * self::MONTHLY_FEE_MMK,
            'pending_months' => $pendingMonths,
            'prepay_pending_months' => $prepayPendingMonths,
            'can_prepay' => $canPrepay,
            'max_prepay_months_in_year' => $maxPrepayMonthsInYear,
            'current_submission' => $pendingCurrent,
            'collection_start_year' => (int) $setting->start_year,
            'collection_start_month' => (int) $setting->start_month,
            'collection_active' => $collectionActive,
            'effective_start_year' => (int) $startDate->year,
            'effective_start_month' => (int) $startDate->month,
        ];
    }
}
