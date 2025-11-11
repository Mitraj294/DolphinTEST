<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
use App\Notifications\GeneralNotification;
use App\Http\Resources\AnnouncementResource;

class AnnouncementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $announcements = Announcement::orderBy('schedule_date', 'desc')
            ->orderBy('schedule_time', 'desc')
            ->get();

        return response()->json(['announcements' => AnnouncementResource::collection($announcements)]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            // creation fields
            'message' => 'required|string',
            'schedule_date' => 'nullable|date',
            'schedule_time' => 'nullable|date_format:H:i',
            // recipients (optional)
            'organization_ids' => 'nullable|array',
            'organization_ids.*' => 'integer|exists:organizations,id',
            'admin_ids' => 'nullable|array',
            'admin_ids.*' => 'integer|exists:users,id',
            'group_ids' => 'nullable|array',
            'group_ids.*' => 'integer|exists:groups,id',
            // alternative scheduling input
            'scheduled_at' => 'nullable|date',
        ]);

        // Normalize scheduled_at -> schedule_date/schedule_time when provided
        if (!empty($validated['scheduled_at']) && (empty($validated['schedule_date']) || empty($validated['schedule_time']))) {
            try {
                $dt = Carbon::parse($validated['scheduled_at']);
                $validated['schedule_date'] = $validated['schedule_date'] ?? $dt->toDateString();
                $validated['schedule_time'] = $validated['schedule_time'] ?? $dt->format('H:i');
            } catch (\Exception $e) {
                // ignore parse failure; proceed with any provided schedule_* fields
            }
        }

            $announcement = Announcement::create([
            'message' => $validated['message'],
            'schedule_date' => $validated['schedule_date'] ?? null,
            'schedule_time' => $validated['schedule_time'] ?? null,
            // Use null-safe operator in case request is unauthenticated
            'sender_id' => $request->user()?->id ?? null,
            ]);

        // Attach recipients if provided
        if (!empty($validated['organization_ids'])) {
            $announcement->organizations()->syncWithoutDetaching($validated['organization_ids']);
        }
        if (!empty($validated['admin_ids'])) {
            $announcement->admins()->syncWithoutDetaching($validated['admin_ids']);
        }
        if (!empty($validated['group_ids'])) {
            $announcement->groups()->syncWithoutDetaching($validated['group_ids']);
        }

        // Dispatch notifications immediately or schedule via delay based on schedule_* fields
        try {
            $recipients = $this->getRecipientsForAnnouncement($announcement);
            $notification = new GeneralNotification($announcement);

            // If both schedule_date and schedule_time are present, delay delivery
            if (!empty($announcement->schedule_date) && !empty($announcement->schedule_time)) {
                $dtString = $announcement->schedule_date . ' ' . $announcement->schedule_time;
                try {
                    $when = Carbon::parse($dtString);
                    $notification->delay($when);
                } catch (\Exception $e) {
                    // ignore invalid schedule; send immediately
                }
            }

            if ($recipients->count() > 0) {
                Notification::send($recipients, $notification);
                // Mark announcement as sent now that notifications were dispatched
                $announcement->sent_at = now();
                $announcement->save();
            }
        } catch (\Exception $e) {
            Log::error('[AnnouncementController@store] Failed to dispatch announcement', [
                'announcement_id' => $announcement->id,
                'error' => $e->getMessage(),
            ]);
            // continue; creation still succeeded
        }

        return response()->json([
            'announcement' => new AnnouncementResource($announcement),
            'message' => 'Announcement created and dispatched (if recipients provided)'
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $announcement = Announcement::findOrFail($id);
            return response()->json(['announcement' => new AnnouncementResource($announcement)]);
        } catch (\Exception $e) {
            Log::warning('[AnnouncementController@show] failed to find announcement', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Announcement not found'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $announcement = Announcement::findOrFail($id);

        $validatedData = $request->validate([
            'message' => 'sometimes|string',
            'schedule_date' => 'nullable|date',
            'schedule_time' => 'nullable|date_format:H:i'
        ]);

        $announcement->update($validatedData);

        return response()->json([
            'announcement' => $announcement,
            'message' => 'Announcement updated successfully'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $announcement = Announcement::findOrFail($id);
            $announcement->delete();
            return response()->json(['message' => 'Announcement deleted successfully']);
        } catch (\Exception $e) {
            Log::warning('[AnnouncementController@destroy] failed to delete announcement', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Announcement not found or could not be deleted'], 404);
        }
    }

    /**
     * Get scheduled announcements for today
     */
    public function todayScheduled()
    {
        try {
            $today = Carbon::today()->format('Y-m-d');
            $announcements = Announcement::whereDate('schedule_date', $today)
                ->orderBy('schedule_time', 'asc')
                ->get();
            return response()->json(['announcements' => AnnouncementResource::collection($announcements)]);
        } catch (\Exception $e) {
            Log::warning('[AnnouncementController@todayScheduled] DB query failed', ['error' => $e->getMessage()]);
            return response()->json(['announcements' => []]);
        }
    }

    /**
     * Get announcements by date range
     */
    public function byDateRange(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);
        try {
            $announcements = Announcement::whereBetween('schedule_date', [
                $request->start_date,
                $request->end_date
            ])
                ->orderBy('schedule_date', 'asc')
                ->orderBy('schedule_time', 'asc')
                ->get();

            return response()->json(['announcements' => AnnouncementResource::collection($announcements)]);
        } catch (\Exception $e) {
            Log::warning('[AnnouncementController@byDateRange] DB query failed', ['error' => $e->getMessage()]);
            return response()->json(['announcements' => []]);
        }
    }

    /**
     * Collect unique User recipients for an announcement from attached orgs, groups, and admins.
     */
    private function getRecipientsForAnnouncement(Announcement $announcement)
    {
        // Prefer organization members over legacy users pivot
        try {
            $announcement->load(['organizations.members', 'groups.users', 'admins']);

            $adminUsers = $announcement->admins ?? collect();
            $orgUsers = $announcement->organizations->flatMap(function ($org) {
                return $org->members ?? collect(); // users of the organization via organization_member
            });

            $groupUsers = $announcement->groups->flatMap(function ($group) {
                return $group->users ?? collect();
            });

            // Merge all users and remove duplicates
            return $adminUsers->merge($orgUsers)->merge($groupUsers)->unique('id');
        } catch (\Throwable $e) {
            Log::warning('[getRecipientsForAnnouncement] failed to load recipients', ['announcement_id' => $announcement->id ?? null, 'error' => $e->getMessage()]);
            return collect();
        }
    }
}
