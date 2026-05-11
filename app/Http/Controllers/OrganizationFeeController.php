<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MembershipFeeSubmission;
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

        $validated = $request->validate([
            'slip_image' => 'required|image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);

        $now = now();
        $year = (int) $now->year;
        $month = (int) $now->month;

        $existing = MembershipFeeSubmission::where('member_id', $member->id)
            ->where('fee_year', $year)
            ->where('fee_month', $month)
            ->first();

        if ($existing && $existing->status === 'approved') {
            return response()->json([
                'message' => 'Current month is already paid.',
            ], 409);
        }

        $slipPath = $validated['slip_image']->store('membership-fees', 'public');

        if ($existing) {
            $existing->update([
                'slip_image' => $slipPath,
                'claimed_payment_date' => $now->toDateString(),
                'amount_mmk' => self::MONTHLY_FEE_MMK,
                'status' => 'pending',
                'was_late' => false,
                'reviewed_at' => null,
                'reviewed_by' => null,
                'rejection_reason' => null,
            ]);
        } else {
            MembershipFeeSubmission::create([
                'member_id' => $member->id,
                'fee_year' => $year,
                'fee_month' => $month,
                'slip_image' => $slipPath,
                'claimed_payment_date' => $now->toDateString(),
                'amount_mmk' => self::MONTHLY_FEE_MMK,
                'status' => 'pending',
                'was_late' => false,
            ]);
        }

        $member->load(['feeSubmissions']);

        return response()->json([
            'message' => 'Slip uploaded successfully.',
            ...$this->buildMemberFeePayload($member),
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

        $members = Member::with(['feeSubmissions' => function ($query) use ($year) {
            $query->where('fee_year', $year);
        }])->orderBy('name')->get();

        $rows = $members->map(function (Member $member) use ($months) {
            $statuses = [];
            foreach ($months as $month) {
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

        $startDate = $member->approved_at
            ? Carbon::parse($member->approved_at)->startOfMonth()
            : Carbon::parse($member->created_at)->startOfMonth();

        $cursor = $startDate->copy();
        $end = $current->copy()->startOfMonth();
        $outstandingMonths = 0;

        while ($cursor->lte($end)) {
            if (!in_array($cursor->format('Y-m'), $approvedKeys, true)) {
                $outstandingMonths++;
            }
            $cursor->addMonth();
        }

        $currentMonthStatus = in_array($currentKey, $approvedKeys, true)
            ? 'paid'
            : ($pendingCurrent ? 'pending' : 'unpaid');

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
            'current_submission' => $pendingCurrent,
        ];
    }
}
