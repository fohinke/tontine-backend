<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $members = Member::query()
            ->when($request->integer('tontine_id'), function ($query, $tontineId) {
                $query->where('tontine_id', $tontineId);
            })
            ->with('tontine')
            ->withCount('payments')
            ->latest()
            ->get();

        return response()->json(['data' => $members]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tontine_id' => ['required', 'exists:tontines,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'status' => ['nullable', 'string', 'max:50'],
            'joined_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $request->file('photo')->store('members', 'public');
        }

        $member = Member::create($validated);

        return response()->json([
            'message' => 'Membre ajoute avec succes.',
            'data' => $member->load('tontine'),
        ], 201);
    }

    public function show(Member $member): JsonResponse
    {
        $member->load(['tontine', 'payments.contributionSession']);

        return response()->json(['data' => $member]);
    }

    public function photo(Member $member): StreamedResponse|JsonResponse
    {
        if (! $member->photo_path || ! Storage::disk('public')->exists($member->photo_path)) {
            return response()->json(['message' => 'Photo introuvable.'], 404);
        }

        return Storage::disk('public')->response($member->photo_path);
    }

    public function update(Request $request, Member $member): JsonResponse
    {
        $validated = $request->validate([
            'tontine_id' => ['sometimes', 'required', 'exists:tontines,id'],
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'remove_photo' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'max:50'],
            'joined_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($request->boolean('remove_photo') && $member->photo_path) {
            Storage::disk('public')->delete($member->photo_path);
            $validated['photo_path'] = null;
        }

        if ($request->hasFile('photo')) {
            if ($member->photo_path) {
                Storage::disk('public')->delete($member->photo_path);
            }

            $validated['photo_path'] = $request->file('photo')->store('members', 'public');
        }

        $member->update($validated);

        return response()->json([
            'message' => 'Membre mis a jour.',
            'data' => $member->fresh('tontine'),
        ]);
    }

    public function destroy(Member $member): JsonResponse
    {
        if ($member->photo_path) {
            Storage::disk('public')->delete($member->photo_path);
        }

        $member->delete();

        return response()->json([
            'message' => 'Membre supprime.',
        ]);
    }
}
