<?php

namespace App\Http\Controllers;

use App\Models\ActivityLogModel;
use App\Models\PlatformConfigModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformSettingsController extends Controller
{
    public const THEME_KEY = 'ui.theme';

    /** @var list<string> */
    public const THEMES = ['black-yellow', 'sea-green', 'orange', 'red'];

    public function settingsFetch(): JsonResponse
    {
        return response()->json([
            'data' => $this->present(),
        ]);
    }

    public function settingsSave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'theme' => ['required', 'string', Rule::in(self::THEMES)],
        ]);

        $row = PlatformConfigModel::query()->firstOrNew(['config_key' => self::THEME_KEY]);
        $row->config_value = $data['theme'];
        $row->note = $row->note ?: 'Platform UI theme for public, admin, and mess panels.';
        $row->updated_by = $request->user()->id;
        $row->save();

        ActivityLogModel::record(
            'settings.save',
            'Platform theme set to '.$data['theme'].'.',
            $request->user()->id,
            'platform_config',
            $row->id,
            null,
            ['theme' => $data['theme']],
            $request->ip(),
        );

        return response()->json([
            'message' => 'Settings saved.',
            'data' => $this->present(),
        ]);
    }

    /**
     * @return array{theme: string, contact_email: string}
     */
    private function present(): array
    {
        $value = PlatformConfigModel::query()
            ->where('config_key', self::THEME_KEY)
            ->value('config_value');

        $theme = is_string($value) && in_array($value, self::THEMES, true)
            ? $value
            : 'black-yellow';

        $contact = trim((string) config('services.contact_inbox'));

        return [
            'theme' => $theme,
            'contact_email' => $contact !== '' ? $contact : 'hello@bhuribhoj.com',
        ];
    }
}
