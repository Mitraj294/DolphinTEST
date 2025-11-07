<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Carbon\Carbon;

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
        return response()->json(['announcements' => $announcements]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'message' => 'required|string',
            'schedule_date' => 'nullable|date',
            'schedule_time' => 'nullable|date_format:H:i'
        ]);

        $announcement = Announcement::create($validatedData);

        return response()->json([
            'announcement' => $announcement,
            'message' => 'Announcement created successfully'
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $announcement = Announcement::findOrFail($id);
        return response()->json(['announcement' => $announcement]);
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
        $announcement = Announcement::findOrFail($id);
        $announcement->delete();

        return response()->json(['message' => 'Announcement deleted successfully']);
    }

    /**
     * Get scheduled announcements for today
     */
    public function todayScheduled()
    {
        $today = Carbon::today()->format('Y-m-d');
        $announcements = Announcement::whereDate('schedule_date', $today)
            ->orderBy('schedule_time', 'asc')
            ->get();
        return response()->json(['announcements' => $announcements]);
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

        $announcements = Announcement::whereBetween('schedule_date', [
            $request->start_date,
            $request->end_date
        ])
            ->orderBy('schedule_date', 'asc')
            ->orderBy('schedule_time', 'asc')
            ->get();

        return response()->json(['announcements' => $announcements]);
    }
}
