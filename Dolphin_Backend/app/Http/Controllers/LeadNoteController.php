<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LeadNoteController extends Controller
{
    /**
     * Get all notes for a specific lead.
     */
    public function index($leadId)
    {
        $lead = Lead::find($leadId);

        if (!$lead) {
            return response()->json(['message' => 'Lead not found'], 404);
        }

        $notes = $lead->notes()->with('creator:id,first_name,last_name,email')->get();

        return response()->json($notes);
    }

    /**
     * Store a new note for a lead.
     */
    public function store(Request $request, $leadId)
    {
        $lead = Lead::find($leadId);

        if (!$lead) {
            return response()->json(['message' => 'Lead not found'], 404);
        }

        $validated = $request->validate([
            'note' => 'required|string',
            'note_date' => 'nullable|date',
        ]);

        $noteData = [
            'lead_id' => $leadId,
            'note' => $validated['note'],
            'note_date' => $validated['note_date'] ?? now(),
            'created_by' => $request->user() ? $request->user()->id : null,
        ];

        $leadNote = LeadNote::create($noteData);
        $leadNote->load('creator:id,first_name,last_name,email');

        Log::info('Lead note created', [
            'lead_id' => $leadId,
            'note_id' => $leadNote->id,
            'created_by' => $leadNote->created_by,
        ]);

        return response()->json([
            'message' => 'Note added successfully',
            'note' => $leadNote,
        ], 201);
    }

    /**
     * Get a specific note.
     */
    public function show($leadId, $noteId)
    {
        $note = LeadNote::with('creator:id,first_name,last_name,email')
            ->where('lead_id', $leadId)
            ->find($noteId);

        if (!$note) {
            return response()->json(['message' => 'Note not found'], 404);
        }

        return response()->json($note);
    }

    /**
     * Update a note.
     */
    public function update(Request $request, $leadId, $noteId)
    {
        $note = LeadNote::where('lead_id', $leadId)->find($noteId);

        if (!$note) {
            return response()->json(['message' => 'Note not found'], 404);
        }

        $validated = $request->validate([
            'note' => 'sometimes|required|string',
            'note_date' => 'sometimes|nullable|date',
        ]);

        $note->update($validated);
        $note->load('creator:id,first_name,last_name,email');

        Log::info('Lead note updated', [
            'lead_id' => $leadId,
            'note_id' => $noteId,
            'updated_by' => $request->user() ? $request->user()->id : null,
        ]);

        return response()->json([
            'message' => 'Note updated successfully',
            'note' => $note,
        ]);
    }

    /**
     * Delete a note.
     */
    public function destroy(Request $request, $leadId, $noteId)
    {
        $note = LeadNote::where('lead_id', $leadId)->find($noteId);

        if (!$note) {
            return response()->json(['message' => 'Note not found'], 404);
        }

        $note->delete();

        Log::info('Lead note deleted', [
            'lead_id' => $leadId,
            'note_id' => $noteId,
            'deleted_by' => $request->user() ? $request->user()->id : null,
        ]);

        return response()->json([
            'message' => 'Note deleted successfully',
        ]);
    }
}
