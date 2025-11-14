<?php

namespace App\Http\Controllers;

use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Notifications\GeneralNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::orderBy('schedule_date', 'desc')
            ->orderBy('schedule_time', 'desc')
            ->get();

        return response()->json(['announcements' => AnnouncementResource::collection($announcements)]);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([

            'message' => 'required|string',
            'schedule_date' => 'nullable|date',
            'schedule_time' => 'nullable|date_format:H:i',

            'organization_ids' => 'nullable|array',
            'organization_ids.*' => 'integer|exists:organizations,id',
            'admin_ids' => 'nullable|array',
            'admin_ids.*' => 'integer|exists:users,id',
            'group_ids' => 'nullable|array',
            'group_ids.*' => 'integer|exists:groups,id',

            'scheduled_at' => 'nullable|date',
        ]);


        if (!empty($validated['scheduled_at']) && (empty($validated['schedule_date']) || empty($validated['schedule_time']))) {
            try {
                $dt = Carbon::parse($validated['scheduled_at']);
                $validated['schedule_date'] = $validated['schedule_date'] ?? $dt->toDateString();
                $validated['schedule_time'] = $validated['schedule_time'] ?? $dt->format('H:i');
            } catch (\Exception $e) {

            }
        }

        $announcement = Announcement::create([
        'message' => $validated['message'],
        'schedule_date' => $validated['schedule_date'] ?? null,
        'schedule_time' => $validated['schedule_time'] ?? null,

        'sender_id' => $request->user()?->id ?? null,
        ]);


        if (!empty($validated['organization_ids'])) {
            $announcement->organizations()->syncWithoutDetaching($validated['organization_ids']);
        }
        if (!empty($validated['admin_ids'])) {
            $announcement->admins()->syncWithoutDetaching($validated['admin_ids']);
        }
        if (!empty($validated['group_ids'])) {
            $announcement->groups()->syncWithoutDetaching($validated['group_ids']);
        }


        try {
            $recipients = $this->getRecipientsForAnnouncement($announcement);
            $notification = new GeneralNotification($announcement);


            if (!empty($announcement->schedule_date) && !empty($announcement->schedule_time)) {
                $dtString = $announcement->schedule_date . ' ' . $announcement->schedule_time;
                try {
                    $when = Carbon::parse($dtString);
                    $notification->delay($when);
                } catch (\Exception $e) {

                }
            }

            if ($recipients->count() > 0) {
                Notification::send($recipients, $notification);

                $announcement->sent_at = now();
                $announcement->save();
            }
        } catch (\Exception $e) {
            Log::error('[AnnouncementController@store] Failed to dispatch announcement', [
                'announcement_id' => $announcement->id,
                'error' => $e->getMessage(),
            ]);

        }

        return response()->json([
            'announcement' => new AnnouncementResource($announcement),
            'message' => 'Announcement created and dispatched (if recipients provided)'
        ], 201);
    }


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


    private function getRecipientsForAnnouncement(Announcement $announcement)
    {

        try {
            $announcement->load(['organizations.members', 'groups.users', 'admins']);

            $adminUsers = $announcement->admins ?? collect();
            $orgUsers = $announcement->organizations->flatMap(function ($org) {
                return $org->members ?? collect();
            });

            $groupUsers = $announcement->groups->flatMap(function ($group) {
                return $group->users ?? collect();
            });


            return $adminUsers->merge($orgUsers)->merge($groupUsers)->unique('id');
        } catch (\Throwable $e) {
            Log::warning('[getRecipientsForAnnouncement] failed to load recipients', ['announcement_id' => $announcement->id ?? null, 'error' => $e->getMessage()]);
            return collect();
        }
    }
}
