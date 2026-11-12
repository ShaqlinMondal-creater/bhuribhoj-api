<?php

namespace App\Http\Controllers;

use App\Models\ActivityLogModel;
use App\Models\ContactInquiryModel;
use App\Models\ContactInquiryReplyModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

    public function contactSupportReply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'message' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        $inquiry = ContactInquiryModel::query()->with('replies')->find($data['id']);
        if (! $inquiry) {
            throw ValidationException::withMessages([
                'id' => ['That inquiry was not found.'],
            ]);
        }

        $to = trim((string) ($inquiry->email ?? ''));
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'message' => ['This inquiry has no email. Reply by phone instead.'],
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

        if ($inquiry->status !== 'closed') {
            $inquiry->forceFill(['status' => 'replied'])->save();
        }

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
}
