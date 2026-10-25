<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\MealAwayModel;
use App\Models\UserModel;
use App\Support\MealAttendance;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MealAwayController extends Controller
{
    use ResolvesMessId;

    public function mealAwaysFetch(Request $request): JsonResponse
    {
        $actor = $request->user();

        if ($blank = $this->blankMessResponse($actor, ['data' => []])) {
            return $blank;
        }

        $messId = $this->resolveMessId($actor, $request);

        $query = MealAwayModel::query()
            ->with(['user:id,name,member_code,role'])
            ->where('mess_id', $messId)
            ->orderByDesc('from_date')
            ->orderByDesc('id');

        if ($actor->role === 'member') {
            $query->where('user_id', $actor->id);
        }

        $rows = $query->get()->map(fn (MealAwayModel $row) => $this->present($row));

        return response()->json(['data' => $rows]);
    }

    public function mealAwaysSave(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'id' => ['sometimes', 'integer'],
            'user_id' => ['required', 'integer'],
            'from_date' => ['required', 'date'],
            'from_meal' => ['required', Rule::in(['lunch', 'dinner'])],
            'to_date' => ['nullable', 'date'],
            'to_meal' => ['nullable', Rule::in(['lunch', 'dinner'])],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $member = UserModel::query()
            ->where('id', $data['user_id'])
            ->where('mess_id', $messId)
            ->whereIn('role', ['president', 'manager', 'member'])
            ->first();

        if (! $member) {
            throw ValidationException::withMessages([
                'user_id' => ['Choose a member of this mess.'],
            ]);
        }

        if ($actor->role === 'member' && (int) $actor->id !== (int) $member->id) {
            abort(403, 'Members can only set their own away.');
        }

        $from = Carbon::parse($data['from_date'])->timezone('Asia/Kolkata')->toDateString();
        $to = empty($data['to_date']) ? null : Carbon::parse($data['to_date'])->timezone('Asia/Kolkata')->toDateString();
        $toMeal = $to ? ($data['to_meal'] ?? null) : null;

        if ($to && ! $toMeal) {
            throw ValidationException::withMessages([
                'to_meal' => ['Choose the meal that starts again.'],
            ]);
        }

        if ($to && $this->point($from, $data['from_meal']) >= $this->point($to, $toMeal)) {
            throw ValidationException::withMessages([
                'to_date' => ['The return meal must be after the away starts.'],
            ]);
        }

        $current = null;

        if (! empty($data['id'])) {
            $current = MealAwayModel::query()
                ->where('mess_id', $messId)
                ->where('id', $data['id'])
                ->first();

            if (! $current) {
                throw ValidationException::withMessages([
                    'id' => ['Away was not found for this mess.'],
                ]);
            }

            if ($actor->role === 'member' && (int) $current->user_id !== (int) $actor->id) {
                abort(403, 'Members can only set their own away.');
            }
        }

        $proposed = [
            'from_date' => $from,
            'from_meal' => $data['from_meal'],
            'to_date' => $to,
            'to_meal' => $toMeal,
        ];

        $this->assertNoOverlap($messId, (int) $member->id, $current?->id, $proposed);

        $before = MealAwayModel::query()->where('mess_id', $messId)->where('user_id', $member->id)->get();
        $after = $before
            ->reject(fn (MealAwayModel $row) => $current && $row->id === $current->id)
            ->concat([(object) $proposed]);
        MealAttendance::assertUnlocked($before, $after);

        $row = $current ?? new MealAwayModel;
        $row->fill([
            'mess_id' => $messId,
            'user_id' => $member->id,
            'from_date' => $from,
            'from_meal' => $data['from_meal'],
            'to_date' => $to,
            'to_meal' => $toMeal,
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'set_by' => $actor->id,
        ])->save();

        MealAttendance::syncSpan($messId, $from, $to);
        if ($current) {
            MealAttendance::syncSpan($messId, $current->from_date->toDateString(), $current->to_date?->toDateString());
        }

        return response()->json([
            'message' => 'Away saved.',
            'data' => $this->present($row->fresh()->load('user:id,name,member_code,role')),
        ]);
    }

    public function mealAwaysDelete(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $row = MealAwayModel::query()
            ->where('mess_id', $messId)
            ->where('id', $id)
            ->first();

        if (! $row) {
            throw ValidationException::withMessages([
                'id' => ['Away was not found for this mess.'],
            ]);
        }

        if ($actor->role === 'member' && (int) $row->user_id !== (int) $actor->id) {
            abort(403, 'Members can only set their own away.');
        }

        $before = MealAwayModel::query()->where('mess_id', $messId)->where('user_id', $row->user_id)->get();
        $after = $before->reject(fn (MealAwayModel $item) => $item->id === $row->id)->values();
        MealAttendance::assertUnlocked($before, $after);

        $from = $row->from_date->toDateString();
        $to = $row->to_date?->toDateString();
        $row->delete();
        MealAttendance::syncSpan($messId, $from, $to);

        return response()->json([
            'message' => 'Away removed.',
        ]);
    }

    private function assertNoOverlap(int $messId, int $userId, ?int $ignoreId, array $proposed): void
    {
        $rows = MealAwayModel::query()
            ->where('mess_id', $messId)
            ->where('user_id', $userId)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->get();

        $start = $this->point($proposed['from_date'], $proposed['from_meal']);
        $end = $proposed['to_date'] ? $this->point($proposed['to_date'], $proposed['to_meal']) : '9999-12-31 1';

        foreach ($rows as $row) {
            $otherStart = $this->point($row->from_date->toDateString(), $row->from_meal);
            $otherEnd = $row->to_date ? $this->point($row->to_date->toDateString(), $row->to_meal) : '9999-12-31 1';

            if ($start < $otherEnd && $otherStart < $end) {
                throw ValidationException::withMessages([
                    'from_date' => ['This member already has an away in that time.'],
                ]);
            }
        }
    }

    private function present(MealAwayModel $row): array
    {
        return [
            'id' => $row->id,
            'user_id' => $row->user_id,
            'from_date' => $row->from_date?->toDateString(),
            'from_meal' => $row->from_meal,
            'to_date' => $row->to_date?->toDateString(),
            'to_meal' => $row->to_meal,
            'note' => $row->note,
            'user' => $row->user ? [
                'id' => $row->user->id,
                'name' => $row->user->name,
                'member_code' => $row->user->member_code,
            ] : null,
        ];
    }

    private function point(string $date, string $meal): string
    {
        return $date.' '.($meal === 'dinner' ? '1' : '0');
    }
}
