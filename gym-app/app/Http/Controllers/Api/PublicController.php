<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Plan;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class PublicController extends Controller
{
    public function gym(): JsonResponse
    {
        $s = Setting::allSettings();

        return response()->json([
            'slug' => \App\Support\Tenancy::gym()?->slug,
            'name' => $s['gym_name'],
            'phone' => $s['gym_phone'],
            'address' => $s['gym_address'],
            'about' => $s['gym_about'],
            'working_hours' => $s['working_hours'],
            'location_url' => $s['gym_location_url'],
            'instagram' => $s['gym_instagram'],
            'facebook' => $s['gym_facebook'],
            'logo_url' => $s['gym_logo'] ? Storage::disk('public')->url($s['gym_logo']) : null,
            'currency' => Setting::currency(),
        ]);
    }

    public function plans(): JsonResponse
    {
        return response()->json(['data' => Plan::where('is_active', true)->orderBy('price')->get()->map(fn (Plan $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'duration_days' => $p->duration_days,
            'price' => (float) $p->price,
            'sessions_count' => $p->sessions_count,
            'freeze_days' => $p->freeze_days,
            'guest_invitations' => $p->guest_invitations,
            'includes_trainer' => $p->includes_trainer,
            'color' => $p->color,
            'description' => $p->description,
        ])]);
    }

    public function announcements(): JsonResponse
    {
        return response()->json(['data' => Announcement::visible()->latest('published_at')->limit(20)->get()->map(fn (Announcement $a) => [
            'id' => $a->id,
            'title' => $a->localizedTitle(),
            'body' => $a->localizedBody(),
            'image_url' => $a->image ? Storage::disk('public')->url($a->image) : null,
            'published_at' => ($a->published_at ?? $a->created_at)->toIso8601String(),
        ])]);
    }
}
