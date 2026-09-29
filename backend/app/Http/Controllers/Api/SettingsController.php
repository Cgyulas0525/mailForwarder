<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\Rules\ScheduleWindow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function show(ScheduleWindow $window): JsonResponse
    {
        return response()->json([
            'check_window_start' => AppSetting::getValue('check_window_start', config('forwarding.window_start')),
            'check_window_end' => AppSetting::getValue('check_window_end', config('forwarding.window_end')),
            'open_now' => $window->isOpen(),
            'timezone' => 'Europe/Budapest',
            'live_smtp' => (bool) config('forwarding.live_smtp'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'check_window_start' => ['nullable', 'date_format:H:i'],
            'check_window_end' => ['nullable', 'date_format:H:i'],
        ]);

        AppSetting::putValue('check_window_start', $data['check_window_start'] ?? null);
        AppSetting::putValue('check_window_end', $data['check_window_end'] ?? null);

        return response()->json(['success' => true, 'message' => 'Időablak mentve. Üres érték a teljes napot jelenti.']);
    }
}
