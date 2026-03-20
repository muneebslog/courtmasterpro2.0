<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $tournament = null;

        if ($user->role === User::ROLE_ADMIN) {
            $tournament = Tournament::query()
                ->where('admin_id', $user->id)
                ->latest()
                ->first();
        } elseif ($user->role === User::ROLE_UMPIRES) {
            // Umpires can view tournaments they are attached to.
            $tournament = Tournament::query()
                ->whereHas('users', function ($query) use ($user): void {
                    $query->where('users.id', $user->id);
                })
                ->latest()
                ->first();
        }

        return view('dashboard', [
            'tournament' => $tournament,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->role !== User::ROLE_ADMIN) {
            abort(403);
        }

        $hasTournament = Tournament::query()
            ->where('admin_id', $user->id)
            ->exists();

        if ($hasTournament) {
            return redirect()
                ->route('dashboard')
                ->with('status', 'You already have a tournament.');
        }

        $validated = $request->validate([
            'tournament_name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        Tournament::create([
            'tournament_name' => $validated['tournament_name'],
            'location' => $validated['location'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => 'draft',
            'admin_id' => $user->id,
        ]);

        return redirect()
            ->route('dashboard')
            ->with('status', 'Tournament created successfully.');
    }
}
