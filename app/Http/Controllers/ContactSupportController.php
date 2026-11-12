<?php

namespace App\Http\Controllers;

use App\Models\ActivityLogModel;
use App\Models\ContactInquiryModel;
use App\Models\ContactInquiryReplyModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ContactSupportController extends Controller
{
    public function contactSupportFetch(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', Rule::in(['open', 'replied', 'closed'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ]);

        $limit = (int) ($filters['limit'] ?? 10);
        $offset = (int) ($filters['offset'] ?? 0);
        $search = trim((string) ($filters['search'] ?? ''));

        $query = ContactInquiryModel::query()->with([
            'replies.sender:id,name,email',
        ]);

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('name', 'like', $like)
                    ->orWhere('mess', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('message', 'like', $like);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $count = (clone $query)->count();
        $rows = $query
            ->orderByDesc('id')
            ->skip($offset)
            ->take($limit)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (ContactInquiryModel $row) => $this->present($row))->values(),
            'count' => $count,
        ]);
    }

    public function contactSupportStart(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'mess' => ['sometimes', 'nullable', 'string', 'max:160'],
            'members' => ['sometimes', 'integer', 'min:1', 'max:5000'],
            'message' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        $actor = $request->user();
        $to = Str::lower(trim($data['email']));
        $phone = trim((string) ($data['phone'] ?? ''));
        $mess = $this->headerSafe((string) ($data['mess'] ?? ''));
        $name = $this->headerSafe($data['name']);
        $body = trim($data['message']);

        $inquiry = ContactInquiryModel::query()->create([
            'name' => $name,
            'mess' => $mess !== '' ? $mess : 'Direct support',
            'members' => (int) ($data['members'] ?? 1),
            'phone' => $phone !== '' ? $phone : null,
            'email' => $to,
            'message' => 'Outbound thread started by support.',
            'status' => 'replied',
            'ip' => $request->ip(),
        ]);

        try {
            Mail::send('mail.contact-reply', [
                'name' => $inquiry->name,
                'mess' => $inquiry->mess,
                'reply' => $body,
            ], function ($message) use ($to, $inquiry) {
                $message->to($to, $inquiry->name)->subject('Message from BhuriBhoj support');
                $inbox = PlatformSettingsController::contactInbox();
                if ($inbox !== '') {
                    $message->replyTo($inbox, 'BhuriBhoj Support');
                }
            });
        } catch (Throwable $e) {
            $inquiry->delete();
            throw $e;
        }

        $reply = ContactInquiryReplyModel::query()->create([
            'contact_inquiry_id' => $inquiry->id,
            'sent_by' => $actor?->id,
            'body' => $body,
            'emailed_to' => $to,
        ]);

        ActivityLogModel::record(
            'contact.start',
            'Started support email to '.$inquiry->name.'.',
            $actor?->id,
            'contact',
            $inquiry->id,
            null,
            ['emailed_to' => $to, 'reply_id' => $reply->id],
            $request->ip(),
        );

        return response()->json([
            'message' => 'Email sent to '.$to.'.',
            'data' => $this->present($inquiry->fresh(['replies.sender:id,name,email'])),
        ], 201);
    }

    public function contactSupportUpdate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'mess' => ['sometimes', 'string', 'min:2', 'max:160'],
            'members' => ['sometimes', 'integer', 'min:1', 'max:5000'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
        ]);

        $inquiry = ContactInquiryModel::query()->with(['replies.sender:id,name,email'])->find($data['id']);
        if (! $inquiry) {
            throw ValidationException::withMessages([
                'id' => ['That inquiry was not found.'],
            ]);
        }

        $changes = collect($data)->except(['id'])->all();
        if (array_key_exists('name', $changes)) {
            $changes['name'] = $this->headerSafe((string) $changes['name']);
        }
        if (array_key_exists('mess', $changes)) {
            $changes['mess'] = $this->headerSafe((string) $changes['mess']);
        }
        if (array_key_exists('phone', $changes)) {
            $phone = trim((string) ($changes['phone'] ?? ''));
            $changes['phone'] = $phone !== '' ? $phone : null;
        }
        if (array_key_exists('email', $changes)) {
            $email = trim((string) ($changes['email'] ?? ''));
            $changes['email'] = $email !== '' ? $email : null;
        }

        if ($changes === []) {
            throw ValidationException::withMessages([
                'id' => ['No fields to update.'],
            ]);
        }

        $inquiry->fill($changes)->save();

        ActivityLogModel::record(
            'contact.update',
            'Updated contact inquiry #'.$inquiry->id.'.',
            $request->user()?->id,
            'contact',
            $inquiry->id,
            null,
            array_keys($changes),
            $request->ip(),
        );

        return response()->json([
            'message' => 'Visitor details saved.',
            'data' => $this->present($inquiry->fresh(['replies.sender:id,name,email'])),
        ]);
    }

    public function contactSupportReply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'message' => ['required', 'string', 'min:2', 'max:5000'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
        ]);

        $inquiry = ContactInquiryModel::query()->with('replies')->find($data['id']);
        if (! $inquiry) {
            throw ValidationException::withMessages([
                'id' => ['That inquiry was not found.'],
            ]);
        }

        if ($inquiry->status === 'closed') {
            throw ValidationException::withMessages([
                'status' => ['Reopen this thread before sending another reply.'],
            ]);
        }

        if (array_key_exists('name', $data)) {
            $inquiry->name = $this->headerSafe($data['name']);
        }
        if (array_key_exists('email', $data)) {
            $email = trim((string) ($data['email'] ?? ''));
            $inquiry->email = $email !== '' ? $email : null;
        }
        if ($inquiry->isDirty(['name', 'email'])) {
            $inquiry->save();
        }

        $to = trim((string) ($inquiry->email ?? ''));
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => ['Add a valid email in To before sending.'],
            ]);
        }

        $body = trim($data['message']);
        $actor = $request->user();

        Mail::send('mail.contact-reply', [
            'name' => $inquiry->name,
            'mess' => $inquiry->mess,
            'reply' => $body,
        ], function ($message) use ($to, $inquiry) {
            $message->to($to, $inquiry->name)->subject('Reply from BhuriBhoj support');
            $inbox = PlatformSettingsController::contactInbox();
            if ($inbox !== '') {
                $message->replyTo($inbox, 'BhuriBhoj Support');
            }
        });

        $reply = ContactInquiryReplyModel::query()->create([
            'contact_inquiry_id' => $inquiry->id,
            'sent_by' => $actor?->id,
            'body' => $body,
            'emailed_to' => $to,
        ]);

        $inquiry->forceFill(['status' => 'replied'])->save();

        ActivityLogModel::record(
            'contact.reply',
            'Replied to contact from '.$inquiry->name.'.',
            $actor?->id,
            'contact',
            $inquiry->id,
            null,
            ['emailed_to' => $to, 'reply_id' => $reply->id],
            $request->ip(),
        );

        $inquiry->load(['replies.sender:id,name,email']);

        return response()->json([
            'message' => 'Reply emailed to '.$to.'.',
            'data' => $this->present($inquiry->fresh(['replies.sender:id,name,email'])),
        ]);
    }

    public function contactSupportStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'status' => ['required', Rule::in(['open', 'replied', 'closed'])],
        ]);

        $inquiry = ContactInquiryModel::query()->with(['replies.sender:id,name,email'])->find($data['id']);
        if (! $inquiry) {
            throw ValidationException::withMessages([
                'id' => ['That inquiry was not found.'],
            ]);
        }

        $inquiry->forceFill(['status' => $data['status']])->save();

        ActivityLogModel::record(
            'contact.status',
            'Contact inquiry #'.$inquiry->id.' marked '.$data['status'].'.',
            $request->user()?->id,
            'contact',
            $inquiry->id,
            null,
            ['status' => $data['status']],
            $request->ip(),
        );

        return response()->json([
            'message' => 'Status updated.',
            'data' => $this->present($inquiry->fresh(['replies.sender:id,name,email'])),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ContactInquiryModel $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name,
            'mess' => $row->mess,
            'members' => (int) $row->members,
            'phone' => $row->phone,
            'email' => $row->email,
            'message' => $row->message,
            'status' => $row->status,
            'ip' => $row->ip,
            'created_at' => optional($row->created_at)?->toIso8601String(),
            'updated_at' => optional($row->updated_at)?->toIso8601String(),
            'replies' => $row->replies->map(static function (ContactInquiryReplyModel $reply) {
                return [
                    'id' => $reply->id,
                    'body' => $reply->body,
                    'emailed_to' => $reply->emailed_to,
                    'created_at' => optional($reply->created_at)?->toIso8601String(),
                    'sender' => $reply->sender
                        ? ['id' => $reply->sender->id, 'name' => $reply->sender->name]
                        : null,
                ];
            })->values()->all(),
        ];
    }

    private function headerSafe(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }
}
