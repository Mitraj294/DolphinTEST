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

        try {
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
        } catch (\Exception $e) {
            Log::error('Failed to create lead note', ['lead_id' => $leadId, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to add note'], 500);
        }
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

        try {
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
        } catch (\Exception $e) {
            Log::error('Failed to update lead note', ['lead_id' => $leadId, 'note_id' => $noteId, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to update note'], 500);
        }
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

        try {
            $note->delete();

            Log::info('Lead note deleted', [
                'lead_id' => $leadId,
                'note_id' => $noteId,
                'deleted_by' => $request->user() ? $request->user()->id : null,
            ]);

            return response()->json([
                'message' => 'Note deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete lead note', ['lead_id' => $leadId, 'note_id' => $noteId, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to delete note'], 500);
        }
    }
}
