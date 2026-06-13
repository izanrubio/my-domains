<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private const SETTING_KEYS = ['cloudflare_api_token', 'expiry_alert_days', 'alert_email'];

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = [];

        foreach (self::SETTING_KEYS as $key) {
            $value = $user->getSetting($key);

            if ($key === 'cloudflare_api_token' && $value !== null) {
                $visible = max(0, strlen($value) - 4);
                $value = str_repeat('*', $visible) . substr($value, -4);
            }

            $settings[$key] = $value;
        }

        return response()->json($settings);
    }

    public function update(UpdateSettingRequest $request): JsonResponse
    {
        $user = $request->user();

        foreach ($request->validated() as $key => $value) {
            $user->setSetting($key, $value);
        }

        return response()->json(['message' => 'Settings updated.']);
    }
}
